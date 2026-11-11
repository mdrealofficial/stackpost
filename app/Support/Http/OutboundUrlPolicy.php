<?php

namespace App\Support\Http;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use RuntimeException;

class OutboundUrlPolicy
{
    /**
     * Validate and resolve an outbound HTTP target. Every resolved address must
     * be public so round-robin DNS cannot hide a private fallback address.
     */
    public function inspect(string $url): OutboundUrlTarget
    {
        $url = trim($url);

        if ($url === '' || strlen($url) > 2048 || preg_match('/[\x00-\x20\x7f]/', $url)) {
            throw new RuntimeException('The outbound URL is invalid.');
        }

        $parts = parse_url($url);

        if (! is_array($parts)) {
            throw new RuntimeException('The outbound URL is invalid.');
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new RuntimeException('Only public HTTP and HTTPS URLs are allowed.');
        }

        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            throw new RuntimeException('Outbound URLs cannot contain credentials or fragments.');
        }

        if (! filter_var($host, FILTER_VALIDATE_IP) && ! preg_match('/^[a-z0-9.-]+$/i', $host)) {
            throw new RuntimeException('The outbound URL host is invalid.');
        }

        if ($this->isBlockedHostname($host)) {
            throw new RuntimeException('The outbound URL must use a public host.');
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);

        if ($port < 1 || $port > 65535) {
            throw new RuntimeException('The outbound URL port is invalid.');
        }

        $addresses = $this->resolveAddresses($host);

        if ($addresses === []) {
            throw new RuntimeException('The outbound URL host could not be resolved.');
        }

        foreach ($addresses as $address) {
            if (! $this->isPublicAddress($address)) {
                throw new RuntimeException('The outbound URL must not resolve to a private or reserved network.');
            }
        }

        return new OutboundUrlTarget(
            $this->normalizeUrl($parts, $scheme, $host, $port),
            $host,
            $port,
            $addresses,
        );
    }

    public function resolveRedirect(OutboundUrlTarget $source, string $location): OutboundUrlTarget
    {
        $location = trim($location);

        if ($location === '') {
            throw new RuntimeException('The remote server returned an invalid redirect.');
        }

        try {
            $resolved = (string) UriResolver::resolve(new Uri($source->url), new Uri($location));
        } catch (\Throwable) {
            throw new RuntimeException('The remote server returned an invalid redirect.');
        }

        return $this->inspect($resolved);
    }

    /**
     * @return array<int, string>
     */
    protected function resolveAddresses(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $addresses = [];
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);

        if (is_array($records)) {
            foreach ($records as $record) {
                $address = trim((string) ($record['ip'] ?? $record['ipv6'] ?? ''));

                if ($address !== '' && filter_var($address, FILTER_VALIDATE_IP)) {
                    $addresses[] = $address;
                }
            }
        }

        if ($addresses === []) {
            $ipv4Addresses = @gethostbynamel($host);

            if (is_array($ipv4Addresses)) {
                $addresses = array_merge($addresses, $ipv4Addresses);
            }
        }

        return array_values(array_unique(array_filter(
            $addresses,
            static fn ($address): bool => filter_var($address, FILTER_VALIDATE_IP) !== false,
        )));
    }

    protected function isPublicAddress(string $address): bool
    {
        $blockedCidrs = [
            '0.0.0.0/8',
            '10.0.0.0/8',
            '100.64.0.0/10',
            '127.0.0.0/8',
            '169.254.0.0/16',
            '172.16.0.0/12',
            '192.0.0.0/24',
            '192.0.2.0/24',
            '192.88.99.0/24',
            '192.168.0.0/16',
            '198.18.0.0/15',
            '198.51.100.0/24',
            '203.0.113.0/24',
            '224.0.0.0/4',
            '240.0.0.0/4',
            '::/96',
            '::ffff:0:0/96',
            '64:ff9b::/96',
            '100::/64',
            '2001::/32',
            '2001:2::/48',
            '2001:10::/28',
            '2001:db8::/32',
            '2002::/16',
            'fc00::/7',
            'fe80::/10',
            'ff00::/8',
        ];

        foreach ($blockedCidrs as $cidr) {
            if ($this->addressInCidr($address, $cidr)) {
                return false;
            }
        }

        return filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }

    protected function addressInCidr(string $address, string $cidr): bool
    {
        [$network, $prefix] = explode('/', $cidr, 2);
        $addressBytes = @inet_pton($address);
        $networkBytes = @inet_pton($network);

        if ($addressBytes === false || $networkBytes === false || strlen($addressBytes) !== strlen($networkBytes)) {
            return false;
        }

        $prefix = (int) $prefix;
        $wholeBytes = intdiv($prefix, 8);
        $remainingBits = $prefix % 8;

        if ($wholeBytes > 0 && substr($addressBytes, 0, $wholeBytes) !== substr($networkBytes, 0, $wholeBytes)) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xff << (8 - $remainingBits)) & 0xff;

        return (ord($addressBytes[$wholeBytes]) & $mask) === (ord($networkBytes[$wholeBytes]) & $mask);
    }

    protected function isBlockedHostname(string $host): bool
    {
        if ($host === 'localhost') {
            return true;
        }

        foreach (['.localhost', '.local', '.internal', '.home', '.lan', '.test', '.invalid', '.onion'] as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return true;
            }
        }

        return false;
    }

    protected function normalizeUrl(array $parts, string $scheme, string $host, int $port): string
    {
        $authority = str_contains($host, ':') ? '['.$host.']' : $host;
        $defaultPort = $scheme === 'https' ? 443 : 80;

        if ($port !== $defaultPort) {
            $authority .= ':'.$port;
        }

        $path = (string) ($parts['path'] ?? '');
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        return $scheme.'://'.$authority.($path !== '' ? $path : '/').$query;
    }
}
