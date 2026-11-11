<?php

namespace Modules\AppChannelInstagramProfiles\Services\Publishing;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\AppChannelInstagramProfiles\Services\Instagram\InstagramApiService;
use Modules\AppChannels\Models\SocialAccount;
use Modules\AppFiles\Models\AppFile;
use Modules\AppPublishing\Contracts\PostPublisher;
use Modules\AppPublishing\Models\PublishingPost;
use Modules\AppPublishing\Support\PublishingMediaUrlResolver;
use RuntimeException;
use Throwable;

class InstagramPostPublisher implements PostPublisher
{
    public function __construct(
        protected InstagramApiService $instagram,
        protected PublishingMediaUrlResolver $mediaUrls,
    ) {}

    public function publish(PublishingPost $post, SocialAccount $account): array
    {
        try {
            $postData = is_array($post->data) ? $post->data : [];
            $options = is_array($postData['options'] ?? null) ? $postData['options'] : [];
            $caption = trim((string) ($postData['caption'] ?? ''));
            $postTo = (string) ($options['post_to'] ?? 'feed');
            $igUserId = $this->resolveInstagramUserId($account);
            $accessToken = $this->resolveAccessToken($account);

            if ($igUserId === '' || $accessToken === '') {
                throw new RuntimeException('The Instagram profile is missing a profile ID or access token.');
            }

            $mediaAssets = $this->resolveMediaAssets((array) ($postData['medias'] ?? []));

            if ($mediaAssets->isEmpty()) {
                return $this->failUnsupported('Instagram publishing requires at least one media item.');
            }

            $creationId = match ($postTo) {
                'stories' => $this->createStoryContainer($igUserId, $accessToken, $mediaAssets),
                'reels' => $this->createReelContainer(
                    $igUserId,
                    $accessToken,
                    $mediaAssets,
                    $caption,
                    $this->resolveCoverUrl($options)
                ),
                default => $this->createFeedContainer($igUserId, $accessToken, $mediaAssets, $caption),
            };

            $isSingleStoryImage = $postTo === 'stories'
                && $mediaAssets->count() === 1
                && (bool) data_get($mediaAssets->first(), 'file.is_image', false);

            // Story images usually do not require long polling like videos/reels.
            if (! $isSingleStoryImage) {
                $this->waitForContainerReady($creationId, $accessToken);
            }

            $publishResponse = Http::asForm()
                ->connectTimeout(15)
                ->timeout(120)
                ->retry(2, 700)
                ->post($this->instagram->graphUrl("{$igUserId}/media_publish"), [
                    'creation_id' => $creationId,
                    'access_token' => $accessToken,
                ]);

            $payload = $publishResponse->json();
            $publishId = trim((string) ($payload['id'] ?? ''));

            if (! $publishResponse->successful() || $publishId === '') {
                return $this->normalizeFailure($payload, 'Instagram could not publish the media container.');
            }

            $permalink = $this->resolvePermalink($publishId, $accessToken);

            return [
                'state' => 'published',
                'error' => null,
                'url' => $permalink,
                'remote_post_id' => $publishId,
                'response' => $payload,
            ];
        } catch (Throwable $exception) {
            $message = trim((string) $exception->getMessage());

            if (Str::contains(Str::lower($message), ['timed out', 'timeout', 'curl error 28'])) {
                $message = 'Instagram request timed out. Please verify the media URL is publicly reachable and retry.';
            }

            return $this->failUnsupported($message !== '' ? $message : 'Instagram publish failed.');
        }
    }

    protected function createFeedContainer(string $igUserId, string $accessToken, Collection $mediaAssets, string $caption): string
    {
        if ($mediaAssets->count() === 1) {
            /** @var array{file:AppFile,url:string} $asset */
            $asset = $mediaAssets->first();
            $file = $asset['file'];

            if ($file->is_image) {
                return $this->createMediaContainer($igUserId, $accessToken, [
                    'image_url' => $asset['url'],
                    'caption' => $caption,
                ]);
            }

            try {
                // Instagram Content Publishing now commonly expects video-as-reel.
                // share_to_feed=true keeps feed behavior for users choosing "feed".
                return $this->createMediaContainer($igUserId, $accessToken, [
                    'media_type' => 'REELS',
                    'video_url' => $asset['url'],
                    'caption' => $caption,
                    'share_to_feed' => 'true',
                ]);
            } catch (RuntimeException $exception) {
                $message = Str::lower($exception->getMessage());

                if (! Str::contains($message, ['invalid parameter', 'unsupported post request'])) {
                    throw $exception;
                }

                // Backward-compatible fallback for older app/account configs.
                return $this->createMediaContainer($igUserId, $accessToken, [
                    'media_type' => 'VIDEO',
                    'video_url' => $asset['url'],
                    'caption' => $caption,
                ]);
            }
        }

        $children = $mediaAssets->map(function (array $asset) use ($igUserId, $accessToken): string {
            /** @var AppFile $file */
            $file = $asset['file'];

            return $this->createMediaContainer($igUserId, $accessToken, $file->is_image
                ? [
                    'image_url' => $asset['url'],
                    'is_carousel_item' => 'true',
                ]
                : [
                    'media_type' => 'VIDEO',
                    'video_url' => $asset['url'],
                    'is_carousel_item' => 'true',
                ]);
        })->all();

        return $this->createMediaContainer($igUserId, $accessToken, [
            'media_type' => 'CAROUSEL',
            'children' => implode(',', $children),
            'caption' => $caption,
        ]);
    }

    protected function createReelContainer(string $igUserId, string $accessToken, Collection $mediaAssets, string $caption, ?string $coverUrl = null): string
    {
        if ($mediaAssets->count() !== 1) {
            throw new RuntimeException('Instagram Reels publishing requires exactly one video.');
        }

        /** @var array{file:AppFile,url:string} $asset */
        $asset = $mediaAssets->first();

        if ($asset['file']->is_image) {
            throw new RuntimeException('Instagram Reels publishing requires a video file.');
        }

        $payload = [
            'media_type' => 'REELS',
            'video_url' => $asset['url'],
            'caption' => $caption,
            'share_to_feed' => 'true',
        ];

        if ($coverUrl) {
            $payload['cover_url'] = $coverUrl;
        }

        return $this->createMediaContainer($igUserId, $accessToken, $payload);
    }

    protected function createStoryContainer(string $igUserId, string $accessToken, Collection $mediaAssets): string
    {
        if ($mediaAssets->count() !== 1) {
            throw new RuntimeException('Instagram Stories publishing requires exactly one media item.');
        }

        /** @var array{file:AppFile,url:string} $asset */
        $asset = $mediaAssets->first();

        return $this->createMediaContainer($igUserId, $accessToken, $asset['file']->is_image
            ? [
                'media_type' => 'STORIES',
                'image_url' => $asset['url'],
            ]
            : [
                'media_type' => 'STORIES',
                'video_url' => $asset['url'],
            ]);
    }

    protected function createMediaContainer(string $igUserId, string $accessToken, array $payload): string
    {
        $response = Http::asForm()
            ->connectTimeout(15)
            ->timeout(180)
            ->retry(2, 700)
            ->post($this->instagram->graphUrl("{$igUserId}/media"), $payload + [
                'access_token' => $accessToken,
            ]);

        $body = $response->json();
        $creationId = trim((string) ($body['id'] ?? ''));

        if (! $response->successful() || $creationId === '') {
            throw new RuntimeException((string) (data_get($body, 'error.message') ?: 'Instagram media container creation failed.'));
        }

        return $creationId;
    }

    protected function waitForContainerReady(string $creationId, string $accessToken, int $attempts = 90): void
    {
        $lastKnownStatus = '';

        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            try {
                $response = Http::connectTimeout(10)
                    ->timeout(40)
                    ->retry(1, 400)
                    ->get($this->instagram->graphUrl($creationId), [
                        'fields' => 'status_code,status',
                        'access_token' => $accessToken,
                    ]);
            } catch (Throwable) {
                usleep(1000000);
                continue;
            }

            $payload = $response->json();
            $status = strtoupper((string) (data_get($payload, 'status_code') ?: data_get($payload, 'status') ?: ''));
            $lastKnownStatus = $status;

            if ($status === '' || in_array($status, ['FINISHED', 'PUBLISHED'], true)) {
                return;
            }

            if (in_array($status, ['ERROR', 'EXPIRED'], true)) {
                throw new RuntimeException((string) (data_get($payload, 'error.message') ?: 'Instagram media processing failed.'));
            }

            usleep(1000000);
        }

        throw new RuntimeException('Instagram media processing timed out before publishing.'.($lastKnownStatus !== '' ? ' Last status: '.$lastKnownStatus : ''));
    }

    protected function resolvePermalink(string $publishId, string $accessToken): ?string
    {
        $response = Http::timeout(30)->get($this->instagram->graphUrl($publishId), [
            'fields' => 'permalink',
            'access_token' => $accessToken,
        ]);

        if (! $response->successful()) {
            return null;
        }

        $permalink = trim((string) data_get($response->json(), 'permalink', ''));

        return $permalink !== '' ? $permalink : null;
    }

    protected function resolveInstagramUserId(SocialAccount $account): string
    {
        return trim((string) ($account->external_id
            ?: data_get($account->auth_data, 'profile_payload.profile.id', '')
            ?: data_get($account->auth_data, 'profile_payload.id', '')
            ?: data_get($account->metadata, 'ig_id', '')));
    }

    protected function resolveAccessToken(SocialAccount $account): string
    {
        return trim((string) ($account->access_token
            ?: data_get($account->auth_data, 'user_token', '')));
    }

    protected function resolveCoverUrl(array $options): ?string
    {
        $coverUrl = trim((string) (
            $options['cover_url'] ?? data_get($options, 'cover_items.0.url', data_get($options, 'cover_items.0.previewUrl', ''))
        ));

        if ($coverUrl === '') {
            return null;
        }

        if (Str::startsWith($coverUrl, ['http://', 'https://'])) {
            return $coverUrl;
        }

        return url($coverUrl);
    }

    protected function resolveMediaAssets(array $items): Collection
    {
        $files = $this->mediaUrls->resolveFiles($items)->keyBy('id');

        return collect($items)
            ->map(function (array $item) use ($files): ?array {
                $fileId = (int) Arr::get($item, 'id');
                /** @var AppFile|null $file */
                $file = $fileId > 0 ? $files->get($fileId) : null;

                if ($file) {
                    return [
                        'file' => $file,
                        'url' => $this->mediaUrls->publicUrlForFile($file),
                    ];
                }

                $url = $this->mediaUrls->firstAbsoluteUrl($item);
                if (! $url) {
                    return null;
                }

                return [
                    'file' => new AppFile([
                        'id' => 0,
                        'name' => (string) Arr::get($item, 'name', 'media'),
                        'mime_type' => (string) Arr::get($item, 'mimeType', ''),
                        'category' => (string) Arr::get($item, 'category', ''),
                        'is_image' => (bool) Arr::get($item, 'isImage', false),
                    ]),
                    'url' => $url,
                ];
            })
            ->filter()
            ->values();
    }

    protected function normalizeFailure(array $payload, string $fallback): array
    {
        return [
            'state' => 'failed',
            'error' => (string) (data_get($payload, 'error.message') ?: $fallback),
            'url' => null,
            'remote_post_id' => null,
            'response' => $payload,
        ];
    }

    protected function failUnsupported(string $message): array
    {
        return [
            'state' => 'failed',
            'error' => $message,
            'url' => null,
            'remote_post_id' => null,
            'response' => null,
        ];
    }
}
