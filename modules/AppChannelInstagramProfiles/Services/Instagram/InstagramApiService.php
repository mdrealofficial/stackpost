<?php

namespace Modules\AppChannelInstagramProfiles\Services\Instagram;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class InstagramApiService
{
    public function integrationState(string $providerKey = 'instagram'): array
    {
        return integration_item_state($providerKey);
    }

    public function config(string $providerKey = 'instagram'): array
    {
        $state = $this->integrationState($providerKey);

        return [
            'provider_key' => $providerKey,
            'ready' => (bool) ($state['ready'] ?? false),
            'app_id' => (string) data_get($state, 'values.app_id', ''),
            'app_secret' => (string) data_get($state, 'values.app_secret', ''),
            'graph_version' => (string) (data_get($state, 'values.graph_version', '') ?: 'v25.0'),
            'permissions' => (string) data_get($state, 'values.permissions', ''),
            'callback_url' => url('integrations/instagram/profile'),
            'state' => $state,
        ];
    }

    public function ensureReady(string $providerKey = 'instagram'): array
    {
        $config = $this->config($providerKey);

        if (! $config['ready']) {
            throw InstagramApiException::integrationNotReady();
        }

        return $config;
    }

    public function buildAuthorizationUrl(array $overrides = [], string $providerKey = 'instagram'): string
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

    public function exchangeCodeForAccessToken(string $code, array $overrides = [], string $providerKey = 'instagram'): array
    {
        $config = $this->ensureReady($providerKey);

        $response = $this->get("{$config['graph_version']}/oauth/access_token", [
            'client_id' => $config['app_id'],
            'client_secret' => $config['app_secret'],
            'redirect_uri' => $overrides['redirect_uri'] ?? $config['callback_url'],
            'code' => $code,
        ]);

        if (! $response->successful()) {
            throw InstagramApiException::tokenExchangeFailed();
        }

        $payload = $response->json();
        $accessToken = (string) data_get($payload, 'access_token', '');

        if ($accessToken === '') {
            throw InstagramApiException::missingAccessToken();
        }

        return [
            'access_token' => $accessToken,
            'payload' => is_array($payload) ? $payload : [],
            'config' => $config,
        ];
    }

    public function getConnectedProfiles(string $accessToken, string $providerKey = 'instagram'): Collection
    {
        $config = $this->ensureReady($providerKey);

        $pagesResponse = $this->get("{$config['graph_version']}/me/accounts", [
            'access_token' => $accessToken,
            'fields' => 'instagram_business_account,id,name,username,fan_count,link,is_verified,picture{url},access_token,category',
            'limit' => 1000,
        ]);

        if (! $pagesResponse->successful()) {
            throw InstagramApiException::profilesLoadFailed();
        }

        $pages = collect((array) data_get($pagesResponse->json(), 'data', []));
        $profiles = collect();

        foreach ($pages as $page) {
            $instagramAccountId = (string) data_get($page, 'instagram_business_account.id', '');

            if ($instagramAccountId === '') {
                continue;
            }

            $profileResponse = $this->get("{$config['graph_version']}/{$instagramAccountId}", [
                'access_token' => $accessToken,
                'fields' => 'id,name,username,profile_picture_url,ig_id',
            ]);

            if (! $profileResponse->successful()) {
                continue;
            }

            $profile = (array) $profileResponse->json();

            if (blank($profile['id'] ?? null) || blank($profile['username'] ?? null)) {
                continue;
            }

            $profiles->push([
                'id' => (string) $profile['id'],
                'name' => (string) ($profile['name'] ?? $profile['username']),
                'username' => (string) $profile['username'],
                'category' => 'Profile',
                'link' => 'https://www.instagram.com/'.ltrim((string) $profile['username'], '@'),
                'avatar_url' => (string) ($profile['profile_picture_url'] ?? ''),
                'access_token' => $accessToken,
                'page_id' => (string) ($page['id'] ?? ''),
                'page_name' => (string) ($page['name'] ?? ''),
                'ig_id' => (string) ($profile['ig_id'] ?? ''),
                'payload' => [
                    'page' => $page,
                    'profile' => $profile,
                ],
            ]);
        }

        return $profiles->values();
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
