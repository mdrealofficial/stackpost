<?php

namespace Modules\AppAutomation\Services\GoogleDrive;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GoogleDriveApiService
{
    public const PROVIDER = 'google_drive';

    public function configuration(): array
    {
        $integrationKey = (string) config('modules.appautomation.google_drive.integration_key', 'google_drive_automation');
        $state = function_exists('integration_item_state') ? integration_item_state($integrationKey) : [];
        $values = (array) ($state['values'] ?? []);
        $clientId = trim((string) ($values['client_id'] ?? config('modules.appautomation.google_drive.client_id', '')));
        $clientSecret = trim((string) ($values['client_secret'] ?? config('modules.appautomation.google_drive.client_secret', '')));
        $configuredScopes = trim((string) ($values['permissions'] ?? ''));
        $callbackUrl = trim((string) ($values['callback_url'] ?? config('modules.appautomation.google_drive.callback_url', '')));
        $webhookUrl = trim((string) ($values['webhook_url'] ?? config('modules.appautomation.google_drive.webhook_url', '')));

        if ($callbackUrl === '') {
            $callbackUrl = route('portal.automation.connections.google-drive.callback');
        }
        if ($webhookUrl === '') {
            $webhookUrl = route('api.automation.google-drive.notifications');
        }

        $scopes = $this->normalizeScopes($configuredScopes !== ''
            ? preg_split('/[\s,]+/', $configuredScopes) ?: []
            : (array) config('modules.appautomation.google_drive.scopes', []));
        $missing = collect([
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ])->filter(fn (string $value): bool => $value === '')->keys()->values()->all();
        $integrationEnabled = $state === [] || (bool) ($state['enabled'] ?? false);

        return [
            'integration_key' => $integrationKey,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'callback_url' => $callbackUrl,
            'webhook_url' => $webhookUrl,
            'scopes' => $scopes,
            'missing' => $missing,
            'enabled' => $integrationEnabled,
            'ready' => $integrationEnabled && $missing === [],
            'webhook_public' => $this->isPublicHttpsUrl($webhookUrl),
        ];
    }

    public function ensureReady(): array
    {
        $configuration = $this->configuration();

        if (! $configuration['ready']) {
            throw GoogleDriveException::configurationMissing($configuration['missing']);
        }

        return $configuration;
    }

    public function readiness(): array
    {
        $configuration = $this->configuration();

        return collect($configuration)
            ->only(['ready', 'enabled', 'missing', 'callback_url', 'webhook_url', 'webhook_public'])
            ->all();
    }

    public function authorizationUrl(string $state): string
    {
        $configuration = $this->ensureReady();

        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => $configuration['client_id'],
            'redirect_uri' => $configuration['callback_url'],
            'scope' => implode(' ', $configuration['scopes']),
            'response_type' => 'code',
            'state' => $state,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
        ]);
    }

    public function exchangeCode(string $code): array
    {
        $configuration = $this->ensureReady();
        $response = Http::timeout(30)->acceptJson()->asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => $configuration['client_id'],
            'client_secret' => $configuration['client_secret'],
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $configuration['callback_url'],
        ]);

        return $this->tokenPayload($response, __('Google Drive authorization'));
    }

    public function refreshToken(string $refreshToken): array
    {
        $configuration = $this->ensureReady();
        $response = Http::timeout(30)->acceptJson()->asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => $configuration['client_id'],
            'client_secret' => $configuration['client_secret'],
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        return $this->tokenPayload($response, __('Google Drive token refresh')) + ['refresh_token' => $refreshToken];
    }

    public function identity(string $accessToken): array
    {
        $response = Http::timeout(30)->acceptJson()->withToken($accessToken)
            ->get('https://www.googleapis.com/oauth2/v2/userinfo');

        return $this->successfulPayload($response, __('Google account verification'));
    }

    public function startPageToken(string $accessToken): string
    {
        $response = Http::timeout(30)->acceptJson()->withToken($accessToken)
            ->get('https://www.googleapis.com/drive/v3/changes/startPageToken', [
                'supportsAllDrives' => 'true',
            ]);
        $payload = $this->successfulPayload($response, __('Google Drive change cursor'));
        $pageToken = trim((string) ($payload['startPageToken'] ?? ''));

        if ($pageToken === '') {
            throw GoogleDriveException::requestFailed(__('Google Drive change cursor'), __('No page token was returned.'));
        }

        return $pageToken;
    }

    public function watchChanges(string $accessToken, string $pageToken, array $channel): array
    {
        $response = Http::timeout(30)->acceptJson()->withToken($accessToken)
            ->post('https://www.googleapis.com/drive/v3/changes/watch?'.http_build_query([
                'pageToken' => $pageToken,
                'supportsAllDrives' => 'true',
                'includeItemsFromAllDrives' => 'true',
            ]), [
                'id' => (string) $channel['id'],
                'type' => 'web_hook',
                'address' => (string) $channel['address'],
                'token' => (string) $channel['token'],
                'expiration' => (string) $channel['expiration'],
            ]);

        return $this->successfulPayload($response, __('Google Drive notification channel'));
    }

    public function stopChannel(string $accessToken, string $channelId, string $resourceId): void
    {
        if ($channelId === '' || $resourceId === '') {
            return;
        }

        $response = Http::timeout(20)->acceptJson()->withToken($accessToken)
            ->post('https://www.googleapis.com/drive/v3/channels/stop', [
                'id' => $channelId,
                'resourceId' => $resourceId,
            ]);

        if (! $response->successful() && ! in_array($response->status(), [404, 410], true)) {
            throw GoogleDriveException::requestFailed(__('Stopping the Google Drive notification channel'), $this->errorMessage($response));
        }
    }

    public function changes(string $accessToken, string $pageToken): array
    {
        $response = Http::timeout(45)->acceptJson()->withToken($accessToken)
            ->get('https://www.googleapis.com/drive/v3/changes', [
                'pageToken' => $pageToken,
                'pageSize' => 1000,
                'spaces' => 'drive',
                'includeRemoved' => 'true',
                'supportsAllDrives' => 'true',
                'includeItemsFromAllDrives' => 'true',
                'fields' => 'nextPageToken,newStartPageToken,changes(time,removed,fileId,file(id,name,mimeType,parents,createdTime,modifiedTime,trashed,webViewLink,driveId,size,md5Checksum,resourceKey))',
            ]);

        return $this->successfulPayload($response, __('Google Drive changes'));
    }

    public function revoke(string $token): void
    {
        if ($token === '') {
            return;
        }

        Http::timeout(20)->asForm()->post('https://oauth2.googleapis.com/revoke', ['token' => $token]);
    }

    public function tokenExpiresAt(array $token): ?CarbonImmutable
    {
        $seconds = max(0, (int) ($token['expires_in'] ?? 0));

        return $seconds > 0 ? CarbonImmutable::now()->addSeconds($seconds) : null;
    }

    protected function tokenPayload(Response $response, string $operation): array
    {
        $payload = $this->successfulPayload($response, $operation);

        if (blank($payload['access_token'] ?? null)) {
            throw GoogleDriveException::requestFailed($operation, __('No access token was returned.'));
        }

        return $payload;
    }

    protected function successfulPayload(Response $response, string $operation): array
    {
        $payload = $response->json();
        $payload = is_array($payload) ? $payload : [];

        if (! $response->successful()) {
            throw GoogleDriveException::requestFailed($operation, $this->errorMessage($response, $payload));
        }

        return $payload;
    }

    protected function errorMessage(Response $response, array $payload = []): string
    {
        return trim((string) (data_get($payload, 'error.message')
            ?: data_get($payload, 'error_description')
            ?: data_get($payload, 'message')
            ?: Str::limit($response->body(), 500)
            ?: __('HTTP :status', ['status' => $response->status()])));
    }

    protected function normalizeScopes(array $scopes): array
    {
        $required = [
            'openid',
            'email',
            'profile',
            'https://www.googleapis.com/auth/userinfo.email',
            'https://www.googleapis.com/auth/drive.readonly',
        ];

        return collect([...$required, ...$scopes])
            ->map(fn (mixed $scope): string => trim((string) $scope))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function isPublicHttpsUrl(string $url): bool
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));

        return strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && $host !== ''
            && ! in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            && ! str_ends_with($host, '.local');
    }
}
