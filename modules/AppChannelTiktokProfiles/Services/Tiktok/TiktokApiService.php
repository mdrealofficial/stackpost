<?php

namespace Modules\AppChannelTiktokProfiles\Services\Tiktok;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Modules\AppChannels\Models\SocialAccount;
use Modules\AppFiles\Models\AppFile;

class TiktokApiService
{
    public function integrationState(string $providerKey = 'tiktok'): array
    {
        return integration_item_state($providerKey);
    }

    public function config(string $providerKey = 'tiktok'): array
    {
        $state = $this->integrationState($providerKey);

        return [
            'provider_key' => $providerKey,
            'ready' => (bool) ($state['ready'] ?? false),
            'client_key' => (string) data_get($state, 'values.client_key', ''),
            'client_secret' => (string) data_get($state, 'values.client_secret', ''),
            'permissions' => (string) data_get($state, 'values.permissions', ''),
            'callback_url' => url('integrations/tiktok/profile'),
            'state' => $state,
        ];
    }

    public function ensureReady(string $providerKey = 'tiktok'): array
    {
        $config = $this->config($providerKey);

        if (! $config['ready']) {
            throw TiktokApiException::integrationNotReady();
        }

        return $config;
    }

    public function buildAuthorizationUrl(array $overrides = [], string $providerKey = 'tiktok'): string
    {
        $config = $this->ensureReady($providerKey);

        $query = http_build_query(array_filter([
            'client_key' => $config['client_key'],
            'redirect_uri' => $overrides['redirect_uri'] ?? $config['callback_url'],
            'scope' => $overrides['scope'] ?? $config['permissions'],
            'response_type' => $overrides['response_type'] ?? 'code',
            'state' => $overrides['state'] ?? null,
        ], fn (mixed $value): bool => $value !== null && $value !== ''));

        return "https://www.tiktok.com/v2/auth/authorize/?{$query}";
    }

    public function exchangeCodeForAccessToken(string $code, array $overrides = [], string $providerKey = 'tiktok'): array
    {
        $config = $this->ensureReady($providerKey);

        $response = $this->asForm()->post($this->openApiUrl('/v2/oauth/token/'), [
            'client_key' => $config['client_key'],
            'client_secret' => $config['client_secret'],
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $overrides['redirect_uri'] ?? $config['callback_url'],
        ]);

        $payload = $this->json($response);
        $data = (array) data_get($payload, 'data', $payload);
        $accessToken = (string) data_get($data, 'access_token', '');

        if (! $response->successful() || $accessToken === '') {
            throw TiktokApiException::tokenExchangeFailed($this->errorMessage($payload) ?: '');
        }

        return [
            'access_token' => $accessToken,
            'refresh_token' => (string) data_get($data, 'refresh_token', ''),
            'open_id' => (string) data_get($data, 'open_id', ''),
            'scope' => (string) data_get($data, 'scope', ''),
            'payload' => is_array($payload) ? $payload : [],
            'config' => $config,
        ];
    }

    public function getCurrentUser(string $accessToken, string $providerKey = 'tiktok'): array
    {
        $this->ensureReady($providerKey);

        $fields = implode(',', [
            'open_id',
            'union_id',
            'avatar_url',
            'avatar_url_100',
            'avatar_large_url',
            'display_name',
            'profile_deep_link',
            'bio_description',
            'username',
            'is_verified',
            'follower_count',
            'following_count',
            'likes_count',
            'video_count',
        ]);

        $response = Http::timeout(30)
            ->acceptJson()
            ->withToken($accessToken)
            ->get($this->openApiUrl('/v2/user/info/'), [
                'fields' => $fields,
            ]);

        $payload = $this->json($response);
        $user = (array) data_get($payload, 'data.user', data_get($payload, 'user', []));
        $openId = (string) data_get($user, 'open_id', '');

        if (! $response->successful() || $openId === '') {
            throw TiktokApiException::profileLoadFailed($this->errorMessage($payload) ?: '');
        }

        return $user + [
            'payload' => is_array($payload) ? $payload : [],
        ];
    }

    public function refreshAccessToken(string $refreshToken, string $providerKey = 'tiktok'): array
    {
        $config = $this->ensureReady($providerKey);

        $response = $this->asForm()->post($this->openApiUrl('/v2/oauth/token/'), [
            'client_key' => $config['client_key'],
            'client_secret' => $config['client_secret'],
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);

        $payload = $this->json($response);
        $data = (array) data_get($payload, 'data', $payload);
        $accessToken = (string) data_get($data, 'access_token', '');

        if (! $response->successful() || $accessToken === '') {
            throw TiktokApiException::tokenExchangeFailed($this->errorMessage($payload) ?: '');
        }

        return [
            'access_token' => $accessToken,
            'refresh_token' => (string) data_get($data, 'refresh_token', ''),
            'open_id' => (string) data_get($data, 'open_id', ''),
            'scope' => (string) data_get($data, 'scope', ''),
            'payload' => is_array($payload) ? $payload : [],
            'config' => $config,
        ];
    }

    public function refreshAccountAccessToken(SocialAccount $account, string $providerKey = 'tiktok'): array
    {
        $refreshToken = trim((string) ($account->refresh_token ?: data_get($account->auth_data, 'refresh_token', '')));

        if ($refreshToken === '') {
            return [
                'access_token' => trim((string) $account->access_token),
                'refresh_token' => '',
                'scope' => trim((string) $account->scopes),
                'payload' => [],
            ];
        }

        $token = $this->refreshAccessToken($refreshToken, $providerKey);
        $accessToken = (string) ($token['access_token'] ?? '');
        $nextRefreshToken = (string) ($token['refresh_token'] ?? $refreshToken);

        $account->forceFill([
            'access_token' => $accessToken,
            'refresh_token' => $nextRefreshToken,
            'scopes' => (string) ($token['scope'] ?? $account->scopes),
            'auth_data' => [
                ...(is_array($account->auth_data) ? $account->auth_data : []),
                'refresh_token' => $nextRefreshToken,
                'refresh_payload' => $token['payload'] ?? [],
            ],
        ])->save();

        return [
            'access_token' => $accessToken,
            'refresh_token' => $nextRefreshToken,
            'scope' => (string) ($token['scope'] ?? ''),
            'payload' => is_array($token['payload'] ?? null) ? $token['payload'] : [],
        ];
    }

    public function queryCreatorInfo(string $accessToken, string $providerKey = 'tiktok'): array
    {
        $this->ensureReady($providerKey);

        $response = Http::timeout(30)
            ->acceptJson()
            ->withToken($accessToken)
            ->withHeaders(['Content-Type' => 'application/json; charset=UTF-8'])
            ->post($this->openApiUrl('/v2/post/publish/creator_info/query/'), new \stdClass());

        $payload = $this->json($response);

        $this->throwIfApiFailed($response, $payload, fn (string $message) => TiktokApiException::creatorInfoFailed($message));

        $data = (array) data_get($payload, 'data', []);

        if (! array_key_exists('privacy_level_options', $data)) {
            throw TiktokApiException::creatorInfoFailed();
        }

        return $data + [
            'payload' => $payload,
        ];
    }

    public function initializeDirectVideoPost(string $accessToken, array $postInfo, array $sourceInfo, string $providerKey = 'tiktok'): array
    {
        return $this->initializePublishRequest(
            $accessToken,
            '/v2/post/publish/video/init/',
            [
                'post_info' => $postInfo,
                'source_info' => $sourceInfo,
            ],
            $providerKey
        );
    }

    public function initializePhotoPost(string $accessToken, array $postInfo, array $sourceInfo, string $providerKey = 'tiktok'): array
    {
        return $this->initializePublishRequest(
            $accessToken,
            '/v2/post/publish/content/init/',
            [
                'post_info' => $postInfo,
                'source_info' => $sourceInfo,
                'post_mode' => 'DIRECT_POST',
                'media_type' => 'PHOTO',
            ],
            $providerKey
        );
    }

    public function uploadVideoFile(string $uploadUrl, AppFile $file): void
    {
        if (! filled($file->disk) || ! filled($file->path) || ! Storage::disk($file->disk)->exists($file->path)) {
            throw TiktokApiException::publishFailed(__('The selected TikTok video file could not be found.'));
        }

        $body = Storage::disk($file->disk)->get($file->path);
        $size = strlen($body);

        if ($size <= 0) {
            throw TiktokApiException::publishFailed(__('The selected TikTok video file is empty.'));
        }

        $response = Http::timeout(120)
            ->withHeaders([
                'Content-Type' => (string) ($file->mime_type ?: 'video/mp4'),
                'Content-Range' => 'bytes 0-'.($size - 1).'/'.$size,
            ])
            ->send('PUT', $uploadUrl, [
                'body' => $body,
            ]);

        if (! $response->successful()) {
            throw TiktokApiException::publishFailed(__('TikTok rejected the video upload.'));
        }
    }

    public function fetchPublishStatus(string $accessToken, string $publishId, string $providerKey = 'tiktok'): array
    {
        $this->ensureReady($providerKey);

        $response = Http::timeout(30)
            ->acceptJson()
            ->withToken($accessToken)
            ->withHeaders(['Content-Type' => 'application/json; charset=UTF-8'])
            ->post($this->openApiUrl('/v2/post/publish/status/fetch/'), [
                'publish_id' => $publishId,
            ]);

        $payload = $this->json($response);

        $this->throwIfApiFailed($response, $payload, fn (string $message) => TiktokApiException::publishFailed($message));

        return (array) data_get($payload, 'data', []);
    }

    public function getVideoList(string $accessToken, int $maxCount = 20): array
    {
        $videos = [];
        $cursor = 0;
        $hasMore = true;
        $fields = implode(',', [
            'id',
            'create_time',
            'cover_image_url',
            'share_url',
            'video_description',
            'duration',
            'height',
            'width',
            'title',
            'embed_html',
            'embed_link',
            'like_count',
            'comment_count',
            'share_count',
            'view_count',
        ]);

        while ($hasMore) {
            $response = Http::timeout(30)
                ->acceptJson()
                ->withToken($accessToken)
                ->post($this->openApiUrl('/v2/video/list/?fields='.$fields), [
                    'cursor' => $cursor,
                    'max_count' => $maxCount,
                ]);

            $payload = $this->json($response);

            if (! $response->successful()) {
                throw TiktokApiException::profileLoadFailed($this->errorMessage($payload) ?: '');
            }

            $videos = array_merge($videos, (array) data_get($payload, 'data.videos', []));
            $cursor = (int) data_get($payload, 'data.cursor', 0);
            $hasMore = (bool) data_get($payload, 'data.has_more', false);

            if (count($videos) >= 200) {
                break;
            }
        }

        return $videos;
    }

    protected function asForm()
    {
        return Http::timeout(30)->acceptJson()->asForm();
    }

    protected function json(Response $response): array
    {
        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }

    protected function errorMessage(array $payload): string
    {
        return (string) (data_get($payload, 'error_description')
            ?: data_get($payload, 'description')
            ?: data_get($payload, 'message')
            ?: data_get($payload, 'error.message')
            ?: data_get($payload, 'data.description')
            ?: data_get($payload, 'data.error_description')
            ?: '');
    }

    protected function errorCode(array $payload): string
    {
        return trim((string) (data_get($payload, 'error.code')
            ?: data_get($payload, 'code')
            ?: data_get($payload, 'error_code')
            ?: ''));
    }

    protected function initializePublishRequest(string $accessToken, string $path, array $body, string $providerKey = 'tiktok'): array
    {
        $this->ensureReady($providerKey);

        $response = Http::timeout(30)
            ->acceptJson()
            ->withToken($accessToken)
            ->withHeaders(['Content-Type' => 'application/json; charset=UTF-8'])
            ->post($this->openApiUrl($path), $body);

        $payload = $this->json($response);

        $this->throwIfApiFailed($response, $payload, fn (string $message) => TiktokApiException::publishFailed($message));

        $data = (array) data_get($payload, 'data', []);

        if (trim((string) ($data['publish_id'] ?? '')) === '') {
            throw TiktokApiException::publishFailed(__('TikTok did not return a publish ID.'));
        }

        return $data + [
            'payload' => $payload,
        ];
    }

    protected function throwIfApiFailed(Response $response, array $payload, callable $exceptionFactory): void
    {
        $code = $this->errorCode($payload);
        $message = $this->errorMessage($payload);

        if ($response->successful() && ($code === '' || $code === 'ok')) {
            return;
        }

        if ($message === '') {
            $message = __('TikTok returned an unexpected response.');
        }

        if ($code !== '' && $code !== 'ok' && ! str_contains($message, $code)) {
            $message .= ' ('.$code.')';
        }

        throw $exceptionFactory($message);
    }

    protected function openApiUrl(string $path): string
    {
        return 'https://open.tiktokapis.com/'.ltrim($path, '/');
    }
}
