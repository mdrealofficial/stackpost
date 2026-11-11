<?php

namespace Modules\AppChannelXProfiles\Services\X;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Modules\AppFiles\Models\AppFile;

class XApiService
{
    public function integrationState(string $providerKey = 'x'): array
    {
        return integration_item_state($providerKey);
    }

    public function config(string $providerKey = 'x'): array
    {
        $state = $this->integrationState($providerKey);

        return [
            'provider_key' => $providerKey,
            'ready' => (bool) ($state['ready'] ?? false),
            'client_id' => (string) data_get($state, 'values.client_id', ''),
            'client_secret' => (string) data_get($state, 'values.client_secret', ''),
            'permissions' => $this->normalizePermissions((string) data_get($state, 'values.permissions', '')),
            'callback_url' => url('integrations/x/profile'),
            'state' => $state,
        ];
    }

    public function ensureReady(string $providerKey = 'x'): array
    {
        $config = $this->config($providerKey);

        if (! $config['ready']) {
            throw XApiException::integrationNotReady();
        }

        return $config;
    }

    public function generatePkcePair(): array
    {
        $verifier = rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        return [
            'verifier' => $verifier,
            'challenge' => $challenge,
            'method' => 'S256',
        ];
    }

    public function buildAuthorizationUrl(array $overrides = [], string $providerKey = 'x'): string
    {
        $config = $this->ensureReady($providerKey);
        $pkce = $overrides['pkce'] ?? $this->generatePkcePair();
        $scope = $this->normalizePermissions((string) ($overrides['scope'] ?? $config['permissions']));

        $query = http_build_query(array_filter([
            'response_type' => 'code',
            'client_id' => $config['client_id'],
            'redirect_uri' => $overrides['redirect_uri'] ?? $config['callback_url'],
            'scope' => $scope,
            'state' => $overrides['state'] ?? null,
            'code_challenge' => (string) ($pkce['challenge'] ?? ''),
            'code_challenge_method' => (string) ($pkce['method'] ?? 'S256'),
        ], fn (mixed $value): bool => $value !== null && $value !== ''));

        return 'https://x.com/i/oauth2/authorize?'.$query;
    }

    public function exchangeCodeForAccessToken(string $code, string $codeVerifier, array $overrides = [], string $providerKey = 'x'): array
    {
        $config = $this->ensureReady($providerKey);

        $response = Http::timeout(30)
            ->acceptJson()
            ->asForm()
            ->withHeaders([
                'Authorization' => 'Basic '.$this->basicCredentials($config['client_id'], $config['client_secret']),
            ])
            ->post('https://api.x.com/2/oauth2/token', [
                'code' => $code,
                'grant_type' => 'authorization_code',
                'client_id' => $config['client_id'],
                'redirect_uri' => $overrides['redirect_uri'] ?? $config['callback_url'],
                'code_verifier' => $codeVerifier,
            ]);

        $payload = $this->json($response);
        $accessToken = (string) data_get($payload, 'access_token', '');

        if (! $response->successful() || $accessToken === '') {
            throw XApiException::tokenExchangeFailed($this->errorMessage($payload));
        }

        return [
            'access_token' => $accessToken,
            'refresh_token' => (string) data_get($payload, 'refresh_token', ''),
            'scope' => $this->normalizePermissions((string) data_get($payload, 'scope', '')),
            'token_type' => (string) data_get($payload, 'token_type', ''),
            'expires_in' => (int) data_get($payload, 'expires_in', 0),
            'payload' => $payload,
            'config' => $config,
        ];
    }

    public function refreshAccessToken(string $refreshToken, string $providerKey = 'x'): array
    {
        $config = $this->ensureReady($providerKey);

        $response = Http::timeout(30)
            ->acceptJson()
            ->asForm()
            ->withHeaders([
                'Authorization' => 'Basic '.$this->basicCredentials($config['client_id'], $config['client_secret']),
            ])
            ->post('https://api.x.com/2/oauth2/token', [
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token',
                'client_id' => $config['client_id'],
            ]);

        $payload = $this->json($response);
        $accessToken = (string) data_get($payload, 'access_token', '');

        if (! $response->successful() || $accessToken === '') {
            throw XApiException::tokenExchangeFailed($this->errorMessage($payload));
        }

        return [
            'access_token' => $accessToken,
            'refresh_token' => (string) (data_get($payload, 'refresh_token', '') ?: $refreshToken),
            'scope' => $this->normalizePermissions((string) data_get($payload, 'scope', '')),
            'token_type' => (string) data_get($payload, 'token_type', ''),
            'expires_in' => (int) data_get($payload, 'expires_in', 0),
            'payload' => $payload,
            'config' => $config,
        ];
    }

    public function getCurrentUser(string $accessToken, string $providerKey = 'x'): array
    {
        $this->ensureReady($providerKey);

        $response = Http::timeout(30)
            ->acceptJson()
            ->withToken($accessToken)
            ->get('https://api.x.com/2/users/me', [
                'user.fields' => 'description,entities,id,location,name,pinned_tweet_id,profile_image_url,protected,public_metrics,url,username,verified',
            ]);

        $payload = $this->json($response);
        $user = (array) data_get($payload, 'data', []);
        $id = (string) data_get($user, 'id', '');

        if (! $response->successful() || $id === '') {
            throw XApiException::profileLoadFailed($this->errorMessage($payload));
        }

        return $user + [
            'payload' => $payload,
        ];
    }

    public function createTweet(string $accessToken, string $text, array $mediaIds = []): array
    {
        $payload = [];

        if (trim($text) !== '') {
            $payload['text'] = trim($text);
        }

        if ($mediaIds !== []) {
            $payload['media'] = [
                'media_ids' => array_values(array_filter($mediaIds, fn ($id): bool => is_string($id) && $id !== '')),
            ];
        }

        $response = Http::timeout(60)
            ->acceptJson()
            ->withToken($accessToken)
            ->post('https://api.x.com/2/tweets', $payload);

        $data = $this->json($response);

        if (! $response->successful() || ! filled(data_get($data, 'data.id'))) {
            throw XApiException::publishingFailed($this->errorMessage($data));
        }

        return $data;
    }

    public function uploadMedia(string $accessToken, AppFile $file): string
    {
        if (! filled($file->path) || ! Storage::disk($file->disk)->exists($file->path)) {
            throw XApiException::mediaUploadFailed('The selected media file does not exist in storage.');
        }

        $mimeType = strtolower((string) $file->mime_type);

        return str_starts_with($mimeType, 'video/')
            ? $this->uploadVideoMediaV2($accessToken, $file)
            : $this->uploadImageMediaV2($accessToken, $file);
    }

    protected function uploadImageMediaV2(string $accessToken, AppFile $file): string
    {
        $response = Http::timeout(60)
            ->acceptJson()
            ->withToken($accessToken)
            ->attach('media', Storage::disk($file->disk)->get($file->path), $file->name ?: basename($file->path))
            ->post('https://api.x.com/2/media/upload', [
                'media_category' => 'tweet_image',
            ]);

        $payload = $this->json($response);
        $mediaId = (string) (data_get($payload, 'data.id', '') ?: data_get($payload, 'id', ''));

        if (! $response->successful() || $mediaId === '') {
            throw XApiException::mediaUploadFailed($this->errorMessage($payload));
        }

        return $mediaId;
    }

    protected function uploadVideoMediaV2(string $accessToken, AppFile $file): string
    {
        $size = (int) Storage::disk($file->disk)->size($file->path);
        $mimeType = (string) ($file->mime_type ?: 'video/mp4');
        $mediaCategory = str_contains(strtolower($mimeType), 'gif') ? 'tweet_gif' : 'tweet_video';

        $init = Http::timeout(60)
            ->acceptJson()
            ->withToken($accessToken)
            ->post('https://api.x.com/2/media/upload/initialize', [
                'total_bytes' => $size,
                'media_type' => $mimeType,
                'media_category' => $mediaCategory,
            ]);

        $initPayload = $this->json($init);
        $mediaId = (string) (data_get($initPayload, 'data.id', '') ?: data_get($initPayload, 'id', ''));

        if (! $init->successful() || $mediaId === '') {
            throw XApiException::mediaUploadFailed($this->errorMessage($initPayload));
        }

        $stream = Storage::disk($file->disk)->readStream($file->path);

        if (! is_resource($stream)) {
            throw XApiException::mediaUploadFailed('Could not read the media stream from storage.');
        }

        $segment = 0;
        $chunkSize = 5 * 1024 * 1024;

        try {
            while (! feof($stream)) {
                $chunk = fread($stream, $chunkSize);

                if ($chunk === false || $chunk === '') {
                    continue;
                }

                $append = Http::timeout(120)
                    ->acceptJson()
                    ->withToken($accessToken)
                    ->attach('media', $chunk, $file->name ?: basename($file->path))
                    ->post("https://api.x.com/2/media/upload/{$mediaId}/append", [
                        'segment_index' => $segment,
                    ]);

                if (! $append->successful()) {
                    throw XApiException::mediaUploadFailed($this->errorMessage($this->json($append)));
                }

                $segment++;
            }
        } finally {
            fclose($stream);
        }

        $finalize = Http::timeout(60)
            ->acceptJson()
            ->withToken($accessToken)
            ->post("https://api.x.com/2/media/upload/{$mediaId}/finalize");

        $finalizePayload = $this->json($finalize);

        if (! $finalize->successful()) {
            throw XApiException::mediaUploadFailed($this->errorMessage($finalizePayload));
        }

        $this->waitForMediaProcessing($accessToken, $mediaId, $finalizePayload);

        return $mediaId;
    }

    protected function waitForMediaProcessing(string $accessToken, string $mediaId, array $payload): void
    {
        $state = (string) (data_get($payload, 'data.processing_info.state', '') ?: data_get($payload, 'processing_info.state', ''));

        while (in_array($state, ['pending', 'in_progress'], true)) {
            $checkAfter = max(1, (int) (data_get($payload, 'data.processing_info.check_after_secs', 1) ?: data_get($payload, 'processing_info.check_after_secs', 1)));
            sleep($checkAfter);

            $status = Http::timeout(30)
                ->acceptJson()
                ->withToken($accessToken)
                ->get("https://api.x.com/2/media/upload/{$mediaId}");

            $payload = $this->json($status);

            if (! $status->successful()) {
                throw XApiException::mediaUploadFailed($this->errorMessage($payload));
            }

            $state = (string) (data_get($payload, 'data.processing_info.state', '') ?: data_get($payload, 'processing_info.state', ''));

            if ($state === 'failed') {
                throw XApiException::mediaUploadFailed($this->errorMessage($payload) ?: 'X failed to process the uploaded media.');
            }
        }
    }

    protected function normalizePermissions(string $permissions): string
    {
        $allowed = [
            'tweet.read',
            'tweet.write',
            'tweet.moderate.write',
            'users.read',
            'follows.read',
            'follows.write',
            'offline.access',
            'space.read',
            'mute.read',
            'mute.write',
            'like.read',
            'like.write',
            'list.read',
            'list.write',
            'block.read',
            'block.write',
            'bookmark.read',
            'bookmark.write',
            'media.write',
            'users.email',
        ];

        $scopes = collect(preg_split('/[\s,]+/', trim($permissions)) ?: [])
            ->map(fn (mixed $scope): string => trim((string) $scope))
            ->filter(fn (string $scope): bool => $scope !== '')
            ->filter(fn (string $scope): bool => in_array($scope, $allowed, true))
            ->values();

        foreach (['tweet.read', 'tweet.write', 'users.read', 'offline.access'] as $required) {
            $scopes->prepend($required);
        }

        return $scopes->unique()->implode(' ');
    }

    protected function basicCredentials(string $clientId, string $clientSecret): string
    {
        return base64_encode($clientId.':'.$clientSecret);
    }

    protected function json(Response $response): array
    {
        $payload = $response->json();

        if (is_array($payload)) {
            return $payload + [
                '_http_status' => $response->status(),
            ];
        }

        $body = trim((string) $response->body());

        return array_filter([
            '_http_status' => $response->status(),
            '_raw_body' => $body !== '' ? mb_substr(trim(strip_tags($body)), 0, 500) : null,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    protected function errorMessage(array $payload): string
    {
        return (string) (data_get($payload, 'error')
            ?: data_get($payload, 'error_description')
            ?: data_get($payload, 'detail')
            ?: data_get($payload, 'title')
            ?: data_get($payload, 'errors.0.message')
            ?: data_get($payload, 'errors.0.detail')
            ?: data_get($payload, 'reason')
            ?: data_get($payload, 'processing_info.error.message')
            ?: data_get($payload, '_raw_body')
            ?: (filled(data_get($payload, '_http_status'))
                ? 'X request failed with HTTP '.data_get($payload, '_http_status')
                : 'Unknown error'));
    }
}
