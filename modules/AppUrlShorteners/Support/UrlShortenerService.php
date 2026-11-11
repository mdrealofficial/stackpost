<?php

namespace Modules\AppUrlShorteners\Support;

use Illuminate\Support\Facades\Http;
use Modules\AdminSettings\Support\OptionStore;

class UrlShortenerService
{
    public function __construct(
        protected OptionStore $options,
    ) {}

    public function shorten(string $url, string $platform = ''): ?string
    {
        $url = trim($url);

        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $platform = $platform !== '' ? $platform : $this->defaultPlatform();

        if (! $this->supports($platform) || ! $this->isConfigured($platform)) {
            return null;
        }

        try {
            return match ($platform) {
                'bitly' => $this->shortenWithBitly($url),
                'tinyurl' => $this->shortenWithTinyUrl($url),
                'rebrandly' => $this->shortenWithRebrandly($url),
                'shortio' => $this->shortenWithShortIo($url),
                default => null,
            };
        } catch (\Throwable) {
            return null;
        }
    }

    public function shortenUrlsInContent(?string $content, string $platform = ''): string
    {
        $content = (string) $content;

        if ($content === '') {
            return '';
        }

        preg_match_all('/https?:\/\/[^\s"<>()]+/i', $content, $matches);
        $urls = collect($matches[0] ?? [])->unique()->values();

        foreach ($urls as $url) {
            $shortUrl = $this->shorten((string) $url, $platform);

            if ($shortUrl) {
                $content = str_replace((string) $url, $shortUrl, $content);
            }
        }

        return $content;
    }

    public function platforms(): array
    {
        return [
            'bitly' => __('Bitly'),
            'tinyurl' => __('TinyURL'),
            'rebrandly' => __('Rebrandly'),
            'shortio' => __('Short.io'),
        ];
    }

    public function defaultPlatform(): string
    {
        return (string) $this->options->get('url_shorteners_platform', '0');
    }

    public function supports(string $platform): bool
    {
        return array_key_exists($platform, $this->platforms());
    }

    public function isConfigured(?string $platform = null): bool
    {
        $platform = $platform ?: $this->defaultPlatform();

        if (! $this->supports($platform)) {
            return false;
        }

        $status = (string) $this->options->get($platform.'_status', '0') === '1';

        if (! $status) {
            return false;
        }

        return match ($platform) {
            'bitly' => trim((string) $this->options->get('bitly_api_key', '')) !== '',
            'tinyurl' => trim((string) $this->options->get('tinyurl_api_key', '')) !== '',
            'rebrandly' => trim((string) $this->options->get('rebrandly_api_key', '')) !== '',
            'shortio' => trim((string) $this->options->get('shortio_api_key', '')) !== ''
                && trim((string) $this->options->get('shortio_domain', '')) !== '',
            default => false,
        };
    }

    protected function shortenWithBitly(string $url): ?string
    {
        $token = trim((string) $this->options->get('bitly_api_key', ''));

        $response = Http::timeout(20)
            ->withToken($token)
            ->acceptJson()
            ->post('https://api-ssl.bitly.com/v4/shorten', [
                'long_url' => $url,
            ]);

        if (! $response->successful()) {
            return null;
        }

        return data_get($response->json(), 'link');
    }

    protected function shortenWithTinyUrl(string $url): ?string
    {
        $token = trim((string) $this->options->get('tinyurl_api_key', ''));

        $response = Http::timeout(20)
            ->withToken($token)
            ->acceptJson()
            ->post('https://api.tinyurl.com/create', [
                'url' => $url,
                'domain' => 'tinyurl.com',
            ]);

        if (! $response->successful()) {
            return null;
        }

        return data_get($response->json(), 'data.tiny_url');
    }

    protected function shortenWithRebrandly(string $url): ?string
    {
        $apiKey = trim((string) $this->options->get('rebrandly_api_key', ''));
        $domain = trim((string) $this->options->get('rebrandly_domain', 'rebrand.ly'));

        $response = Http::timeout(20)
            ->withHeaders([
                'apikey' => $apiKey,
                'Content-Type' => 'application/json',
            ])
            ->acceptJson()
            ->post('https://api.rebrandly.com/v1/links', [
                'destination' => $url,
                'domain' => [
                    'fullName' => $domain !== '' ? $domain : 'rebrand.ly',
                ],
            ]);

        if (! $response->successful()) {
            return null;
        }

        $shortUrl = (string) data_get($response->json(), 'shortUrl', '');

        if ($shortUrl === '') {
            return null;
        }

        return str_starts_with($shortUrl, 'http://') || str_starts_with($shortUrl, 'https://')
            ? $shortUrl
            : 'https://'.$shortUrl;
    }

    protected function shortenWithShortIo(string $url): ?string
    {
        $apiKey = trim((string) $this->options->get('shortio_api_key', ''));
        $domain = trim((string) $this->options->get('shortio_domain', ''));

        $response = Http::timeout(20)
            ->withHeaders([
                'authorization' => $apiKey,
                'Content-Type' => 'application/json',
            ])
            ->acceptJson()
            ->post('https://api.short.io/links', [
                'domain' => $domain,
                'originalURL' => $url,
            ]);

        if (! $response->successful()) {
            return null;
        }

        return data_get($response->json(), 'shortURL');
    }
}
