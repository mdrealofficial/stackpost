<?php

namespace App\Support\Http;

final readonly class OutboundUrlTarget
{
    /**
     * @param  array<int, string>  $addresses
     */
    public function __construct(
        public string $url,
        public string $host,
        public int $port,
        public array $addresses,
    ) {}

    /**
     * Return Guzzle options that keep redirects disabled and pin the connection
     * to the public address that was checked by OutboundUrlPolicy.
     */
    public function requestOptions(bool $stream = false): array
    {
        if (! defined('CURLOPT_RESOLVE') && ! filter_var($this->host, FILTER_VALIDATE_IP)) {
            throw new \RuntimeException('Secure outbound DNS pinning is not available on this server.');
        }

        $options = [
            'allow_redirects' => false,
            'stream' => $stream,
        ];

        if (defined('CURLOPT_RESOLVE') && $this->addresses !== []) {
            $address = $this->addresses[0];
            $pinnedAddress = str_contains($address, ':') ? '['.$address.']' : $address;
            $options['curl'] = [
                CURLOPT_RESOLVE => [sprintf('%s:%d:%s', $this->host, $this->port, $pinnedAddress)],
            ];

            if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
                $options['curl'][CURLOPT_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
            }
        }

        return $options;
    }
}
