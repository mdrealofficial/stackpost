<?php

namespace Modules\AppChannelLinkedinProfiles\Services\Linkedin;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\AppFiles\Models\AppFile;

class LinkedinApiService
{
    protected const API_TIMEOUT = 30;

    protected const MEDIA_TIMEOUT = 300;

    public function integrationState(string $providerKey = 'linkedin_profile'): array
    {
        return integration_item_state($providerKey);
    }

    public function config(string $providerKey = 'linkedin_profile'): array
    {
        $state = $this->integrationState($providerKey);
        $isPageProvider = $providerKey === 'linkedin_page';

        return [
            'provider_key' => $providerKey,
            'ready' => (bool) ($state['ready'] ?? false),
            'app_id' => (string) data_get($state, 'values.app_id', ''),
            'app_secret' => (string) data_get($state, 'values.app_secret', ''),
            'permissions' => (string) data_get($state, 'values.permissions', ''),
            'callback_url' => $isPageProvider ? url('integrations/linkedin/page') : url('integrations/linkedin/profile'),
            'state' => $state,
        ];
    }

    public function ensureReady(string $providerKey = 'linkedin_profile'): array
    {
        $config = $this->config($providerKey);

        if (! $config['ready']) {
            throw LinkedinApiException::integrationNotReady();
        }

        return $config;
    }

    public function buildAuthorizationUrl(array $overrides = [], string $providerKey = 'linkedin_profile'): string
    {
        $config = $this->ensureReady($providerKey);

        $query = http_build_query(array_filter([
            'response_type' => $overrides['response_type'] ?? 'code',
            'client_id' => $config['app_id'],
            'redirect_uri' => $overrides['redirect_uri'] ?? $config['callback_url'],
            'state' => $overrides['state'] ?? null,
            'scope' => $overrides['scope'] ?? $config['permissions'],
        ], fn (mixed $value): bool => $value !== null && $value !== ''));

        return "https://www.linkedin.com/oauth/v2/authorization?{$query}";
    }

    public function exchangeCodeForAccessToken(string $code, string $redirectUri, string $providerKey = 'linkedin_profile'): array
    {
        $config = $this->ensureReady($providerKey);

        $response = Http::timeout(self::API_TIMEOUT)
            ->acceptJson()
            ->asForm()
            ->post('https://www.linkedin.com/oauth/v2/accessToken', [
                'client_id' => $config['app_id'],
                'client_secret' => $config['app_secret'],
                'redirect_uri' => $redirectUri,
                'code' => $code,
                'grant_type' => 'authorization_code',
            ]);

        $payload = $this->json($response);
        $accessToken = trim((string) data_get($payload, 'access_token', ''));

        if (! $response->successful() || $accessToken === '') {
            throw LinkedinApiException::tokenExchangeFailed($this->errorMessage($payload));
        }

        return [
            'access_token' => $accessToken,
            'expires_in' => (int) data_get($payload, 'expires_in', 0),
            'scope' => (string) data_get($payload, 'scope', ''),
            'payload' => $payload,
            'config' => $config,
        ];
    }

    public function getPerson(string $accessToken): array
    {
        $response = Http::timeout(self::API_TIMEOUT)
            ->acceptJson()
            ->withHeaders($this->defaultHeaders($accessToken))
            ->get('https://api.linkedin.com/v2/userinfo');

        $payload = $this->json($response);

        if (! $response->successful() || blank($payload['sub'] ?? null)) {
            throw LinkedinApiException::profilesLoadFailed($this->errorMessage($payload));
        }

        return $payload;
    }

    public function getCompanyPages(string $accessToken): Collection
    {
        $response = Http::timeout(self::API_TIMEOUT)
            ->acceptJson()
            ->withHeaders($this->defaultHeaders($accessToken))
            ->get('https://api.linkedin.com/v2/organizationalEntityAcls', [
                'q' => 'roleAssignee',
                'role' => 'ADMINISTRATOR',
                'projection' => '(elements*(organizationalTarget~(id,localizedName,vanityName,logoV2(original~:playableStreams,cropped~:playableStreams,cropInfo))))',
            ]);

        $payload = $this->json($response);
        $elements = collect((array) ($payload['elements'] ?? []));

        if (! $response->successful()) {
            throw LinkedinApiException::profilesLoadFailed($this->errorMessage($payload));
        }

        return $elements
            ->map(function (array $item): ?array {
                $page = (array) ($item['organizationalTarget~'] ?? []);
                $id = trim((string) ($page['id'] ?? ''));

                if ($id === '') {
                    return null;
                }

                $name = trim((string) ($page['localizedName'] ?? $id));
                $vanityName = trim((string) ($page['vanityName'] ?? ''));

                return [
                    'id' => $id,
                    'name' => $name,
                    'username' => $vanityName,
                    'category' => 'Page',
                    'link' => $vanityName !== '' ? 'https://www.linkedin.com/company/'.$vanityName : 'https://www.linkedin.com/company/'.$id,
                    'avatar_url' => $this->extractLogoUrl($page),
                    'payload' => $page,
                    'author_urn' => 'urn:li:organization:'.$id,
                ];
            })
            ->filter()
            ->values();
    }

    public function createTextPost(string $accessToken, string $authorUrn, string $message, string $visibility = 'PUBLIC'): array
    {
        return $this->createUgcPost($accessToken, [
            'author' => $authorUrn,
            'lifecycleState' => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary' => ['text' => $message],
                    'shareMediaCategory' => 'NONE',
                ],
            ],
            'visibility' => [
                'com.linkedin.ugc.MemberNetworkVisibility' => $visibility,
            ],
        ]);
    }

    public function createLinkPost(string $accessToken, string $authorUrn, string $message, string $linkUrl, string $visibility = 'PUBLIC'): array
    {
        $host = parse_url($linkUrl, PHP_URL_HOST) ?: $linkUrl;

        return $this->createUgcPost($accessToken, [
            'author' => $authorUrn,
            'lifecycleState' => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary' => ['text' => $message],
                    'shareMediaCategory' => 'ARTICLE',
                    'media' => [[
                        'status' => 'READY',
                        'originalUrl' => $linkUrl,
                        'title' => ['text' => Str::limit($host, 200, '')],
                        'description' => ['text' => Str::limit($message, 3000, '')],
                    ]],
                ],
            ],
            'visibility' => [
                'com.linkedin.ugc.MemberNetworkVisibility' => $visibility,
            ],
        ]);
    }

    public function createImagePost(string $accessToken, string $authorUrn, string $message, AppFile $file, string $visibility = 'PUBLIC'): array
    {
        $assetUrn = $this->uploadAsset($accessToken, $authorUrn, $file, 'image');

        return $this->createUgcPost($accessToken, [
            'author' => $authorUrn,
            'lifecycleState' => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary' => ['text' => $message],
                    'shareMediaCategory' => 'IMAGE',
                    'media' => [[
                        'status' => 'READY',
                        'description' => ['text' => Str::limit($message, 3000, '')],
                        'media' => $assetUrn,
                        'title' => ['text' => Str::limit($file->name, 200, '')],
                    ]],
                ],
            ],
            'visibility' => [
                'com.linkedin.ugc.MemberNetworkVisibility' => $visibility,
            ],
        ]);
    }

    public function createMultiImagePost(string $accessToken, string $authorUrn, string $message, Collection $files, string $visibility = 'PUBLIC'): array
    {
        $media = $files
            ->map(function (AppFile $file) use ($accessToken, $authorUrn, $message): array {
                $assetUrn = $this->uploadAsset($accessToken, $authorUrn, $file, 'image');

                return [
                    'status' => 'READY',
                    'description' => ['text' => Str::limit((string) ($file->note ?: $message), 3000, '')],
                    'media' => $assetUrn,
                    'title' => ['text' => Str::limit($file->name, 200, '')],
                ];
            })
            ->values()
            ->all();

        return $this->createUgcPost($accessToken, [
            'author' => $authorUrn,
            'lifecycleState' => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary' => ['text' => $message],
                    'shareMediaCategory' => 'IMAGE',
                    'media' => $media,
                ],
            ],
            'visibility' => [
                'com.linkedin.ugc.MemberNetworkVisibility' => $visibility,
            ],
        ]);
    }

    public function createVideoPost(string $accessToken, string $authorUrn, string $message, AppFile $file, string $visibility = 'PUBLIC'): array
    {
        $assetUrn = $this->uploadAsset($accessToken, $authorUrn, $file, 'video');

        return $this->createUgcPost($accessToken, [
            'author' => $authorUrn,
            'lifecycleState' => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary' => ['text' => $message],
                    'shareMediaCategory' => 'VIDEO',
                    'media' => [[
                        'status' => 'READY',
                        'media' => $assetUrn,
                    ]],
                ],
            ],
            'visibility' => [
                'com.linkedin.ugc.MemberNetworkVisibility' => $visibility,
            ],
        ]);
    }

    public function uploadAsset(string $accessToken, string $ownerUrn, AppFile $file, string $type): string
    {
        $recipe = $type === 'video'
            ? 'urn:li:digitalmediaRecipe:feedshare-video'
            : 'urn:li:digitalmediaRecipe:feedshare-image';

        $prepare = $this->registerUpload($accessToken, $ownerUrn, $recipe);
        $uploadUrl = $this->extractUploadUrl($prepare);
        $assetUrn = (string) data_get($prepare, 'value.asset', '');

        if ($uploadUrl === '' || $assetUrn === '') {
            throw LinkedinApiException::publishingFailed(__('LinkedIn did not return a valid upload destination.'));
        }

        $body = app('filesystem')->disk($file->disk)->get($file->path);

        $response = Http::timeout(self::MEDIA_TIMEOUT)
            ->withHeaders([
                'Authorization' => 'Bearer '.$accessToken,
            ])
            ->withBody($body, (string) ($file->mime_type ?: 'application/octet-stream'))
            ->put($uploadUrl);

        if (! $response->successful()) {
            throw LinkedinApiException::publishingFailed(__('LinkedIn media upload failed.'));
        }

        $this->waitForAsset($accessToken, $assetUrn);

        return $assetUrn;
    }

    protected function registerUpload(string $accessToken, string $ownerUrn, string $recipe): array
    {
        $response = Http::timeout(self::API_TIMEOUT)
            ->acceptJson()
            ->withHeaders($this->defaultHeaders($accessToken, ['Content-Type' => 'application/json']))
            ->post('https://api.linkedin.com/v2/assets?action=registerUpload', [
                'registerUploadRequest' => [
                    'recipes' => [$recipe],
                    'owner' => $ownerUrn,
                    'serviceRelationships' => [[
                        'relationshipType' => 'OWNER',
                        'identifier' => 'urn:li:userGeneratedContent',
                    ]],
                ],
            ]);

        $payload = $this->json($response);

        if (! $response->successful()) {
            throw LinkedinApiException::publishingFailed($this->errorMessage($payload));
        }

        return $payload;
    }

    protected function extractUploadUrl(array $payload): string
    {
        $mechanism = data_get($payload, 'value.uploadMechanism');

        if (! is_array($mechanism)) {
            return '';
        }

        foreach ($mechanism as $config) {
            if (is_array($config) && filled($config['uploadUrl'] ?? null)) {
                return (string) $config['uploadUrl'];
            }
        }

        return '';
    }

    protected function waitForAsset(string $accessToken, string $assetUrn): void
    {
        $assetId = Str::afterLast($assetUrn, ':');

        if ($assetId === '') {
            throw LinkedinApiException::publishingFailed(__('LinkedIn returned an invalid asset identifier.'));
        }

        for ($attempt = 0; $attempt < 12; $attempt++) {
            $response = Http::timeout(self::API_TIMEOUT)
                ->acceptJson()
                ->withHeaders($this->defaultHeaders($accessToken))
                ->get('https://api.linkedin.com/v2/assets/'.$assetId);

            $payload = $this->json($response);

            if (! $response->successful()) {
                throw LinkedinApiException::publishingFailed($this->errorMessage($payload));
            }

            $status = strtoupper((string) (data_get($payload, 'serviceRelationships.0.status') ?: data_get($payload, 'status', '')));

            if (in_array($status, ['AVAILABLE', 'READY', 'ALLOWED'], true)) {
                return;
            }

            if (! in_array($status, ['WAITING_UPLOAD', 'PROCESSING', 'IN_PROGRESS'], true)) {
                throw LinkedinApiException::publishingFailed(__('LinkedIn asset status: :status', ['status' => $status ?: 'UNKNOWN']));
            }

            usleep(2000000);
        }

        throw LinkedinApiException::publishingFailed(__('LinkedIn did not finish processing the uploaded media in time.'));
    }

    protected function createUgcPost(string $accessToken, array $payload): array
    {
        $response = Http::timeout(self::API_TIMEOUT)
            ->acceptJson()
            ->withHeaders($this->defaultHeaders($accessToken, ['Content-Type' => 'application/json']))
            ->post('https://api.linkedin.com/v2/ugcPosts', $payload);

        $body = $this->json($response);

        if (! $response->successful()) {
            throw LinkedinApiException::publishingFailed($this->errorMessage($body));
        }

        return $body + [
            'id' => (string) ($response->header('x-restli-id') ?: data_get($body, 'id', '')),
        ];
    }

    protected function extractLogoUrl(array $page): string
    {
        return (string) (data_get($page, 'logoV2.original~.elements.0.identifiers.0.identifier')
            ?: data_get($page, 'logoV2.cropped~.elements.0.identifiers.0.identifier')
            ?: '');
    }

    protected function defaultHeaders(string $accessToken, array $extra = []): array
    {
        return [
            'Authorization' => 'Bearer '.$accessToken,
            'X-Restli-Protocol-Version' => '2.0.0',
            ...$extra,
        ];
    }

    protected function json(Response $response): array
    {
        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }

    protected function errorMessage(array $payload): string
    {
        return (string) (data_get($payload, 'message')
            ?: data_get($payload, 'error_description')
            ?: data_get($payload, 'serviceErrorMessage')
            ?: data_get($payload, 'error.message')
            ?: __('Unknown error'));
    }
}
