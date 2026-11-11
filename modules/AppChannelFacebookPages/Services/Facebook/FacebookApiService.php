<?php

namespace Modules\AppChannelFacebookPages\Services\Facebook;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class FacebookApiService
{
    public function integrationState(string $providerKey = 'facebook'): array
    {
        return integration_item_state($providerKey);
    }

    public function config(string $providerKey = 'facebook'): array
    {
        $state = $this->integrationState($providerKey);

        return [
            'provider_key' => $providerKey,
            'ready' => (bool) ($state['ready'] ?? false),
            'app_id' => (string) data_get($state, 'values.app_id', ''),
            'app_secret' => (string) data_get($state, 'values.app_secret', ''),
            'graph_version' => (string) (data_get($state, 'values.graph_version', '') ?: 'v25.0'),
            'permissions' => (string) data_get($state, 'values.permissions', ''),
            'callback_url' => url('integrations/facebook/page'),
            'state' => $state,
        ];
    }

    public function ensureReady(string $providerKey = 'facebook'): array
    {
        $config = $this->config($providerKey);

        if (! $config['ready']) {
            throw FacebookApiException::integrationNotReady();
        }

        return $config;
    }

    public function buildAuthorizationUrl(array $overrides = [], string $providerKey = 'facebook'): string
    {
        $config = $this->ensureReady($providerKey);

        $query = http_build_query(array_filter([
            'client_id' => $config['app_id'],
            'redirect_uri' => $overrides['redirect_uri'] ?? $config['callback_url'],
            'scope' => $overrides['scope'] ?? $config['permissions'],
            'response_type' => $overrides['response_type'] ?? 'code',
            'state' => $overrides['state'] ?? null,
        ], fn (mixed $value): bool => $value !== null && $value !== ''));

        return "https://www.facebook.com/{$config['graph_version']}/dialog/oauth?{$query}";
    }

    public function exchangeCodeForAccessToken(string $code, array $overrides = [], string $providerKey = 'facebook'): array
    {
        $config = $this->ensureReady($providerKey);

        $response = $this->get("{$config['graph_version']}/oauth/access_token", [
            'client_id' => $config['app_id'],
            'client_secret' => $config['app_secret'],
            'redirect_uri' => $overrides['redirect_uri'] ?? $config['callback_url'],
            'code' => $code,
        ]);

        if (! $response->successful()) {
            throw FacebookApiException::tokenExchangeFailed();
        }

        $payload = $response->json();
        $accessToken = (string) data_get($payload, 'access_token', '');

        if ($accessToken === '') {
            throw FacebookApiException::missingAccessToken();
        }

        return [
            'access_token' => $accessToken,
            'payload' => is_array($payload) ? $payload : [],
            'config' => $config,
        ];
    }

    public function getUserPages(string $accessToken, array $query = [], string $providerKey = 'facebook'): Collection
    {
        $config = $this->ensureReady($providerKey);

        $response = $this->get("{$config['graph_version']}/me/accounts", $query + [
            'access_token' => $accessToken,
            'fields' => 'id,name,access_token,category,link,picture{url},tasks',
            'limit' => 1000,
        ]);

        if (! $response->successful()) {
            throw FacebookApiException::pagesLoadFailed();
        }

        $pages = (array) data_get($response->json(), 'data', []);
        $nextUrl = (string) data_get($response->json(), 'paging.next', '');
        $requests = 1;

        while ($nextUrl !== '' && $requests < 25) {
            $response = Http::timeout(30)->get($nextUrl);
            $requests++;

            if (! $response->successful()) {
                throw FacebookApiException::pagesLoadFailed();
            }

            $pages = array_merge($pages, (array) data_get($response->json(), 'data', []));
            $nextUrl = (string) data_get($response->json(), 'paging.next', '');
        }

        return $this->normalizePages($pages, $accessToken);
    }

    public function normalizePages(array $pages, string $fallbackAccessToken = ''): Collection
    {
        return collect($pages)
            ->filter(fn (array $page): bool => filled($page['id'] ?? null) && filled($page['name'] ?? null))
            ->map(fn (array $page): array => [
                'id' => (string) $page['id'],
                'name' => (string) $page['name'],
                'category' => (string) ($page['category'] ?? 'Page'),
                'link' => (string) ($page['link'] ?? ''),
                'avatar_url' => (string) data_get($page, 'picture.data.url', ''),
                'access_token' => (string) ($page['access_token'] ?? $fallbackAccessToken),
                'tasks' => array_values((array) ($page['tasks'] ?? [])),
                'payload' => $page,
            ])
            ->values();
    }

    public function get(string $path, array $query = [], int $timeout = 30): Response
    {
        return Http::timeout($timeout)->get($this->graphUrl($path), $query);
    }

    public function graphUrl(string $path): string
    {
        return 'https://graph.facebook.com/'.ltrim($path, '/');
    }
}
