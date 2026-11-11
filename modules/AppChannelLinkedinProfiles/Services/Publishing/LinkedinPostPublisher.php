<?php

namespace Modules\AppChannelLinkedinProfiles\Services\Publishing;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Modules\AppChannelLinkedinProfiles\Services\Linkedin\LinkedinApiException;
use Modules\AppChannelLinkedinProfiles\Services\Linkedin\LinkedinApiService;
use Modules\AppChannels\Models\SocialAccount;
use Modules\AppFiles\Models\AppFile;
use Modules\AppPublishing\Contracts\PostPublisher;
use Modules\AppPublishing\Models\PublishingPost;
use Modules\AppPublishing\Support\PublishingMediaUrlResolver;
use RuntimeException;

class LinkedinPostPublisher implements PostPublisher
{
    public function __construct(
        protected LinkedinApiService $linkedin,
        protected PublishingMediaUrlResolver $mediaUrls,
    ) {}

    public function publish(PublishingPost $post, SocialAccount $account): array
    {
        $postData = is_array($post->data) ? $post->data : [];
        $options = is_array($postData['options'] ?? null) ? $postData['options'] : [];
        $caption = trim((string) ($postData['caption'] ?? ''));
        $accessToken = trim((string) $account->access_token);
        $authorUrn = trim((string) (data_get($account->metadata, 'author_urn') ?: $this->inferAuthorUrn($account)));

        if ($accessToken === '' || $authorUrn === '') {
            throw new RuntimeException('The LinkedIn account is missing an access token or author URN.');
        }

        $postType = strtolower(trim((string) ($options['linkedin_post_type'] ?? 'auto')));
        $mediaFiles = $this->resolveMediaFiles((array) ($postData['medias'] ?? []));
        $hasImages = $mediaFiles->contains(fn (AppFile $file): bool => (bool) $file->is_image);
        $hasVideos = $mediaFiles->contains(fn (AppFile $file): bool => str_starts_with(strtolower((string) $file->mime_type), 'video/'));
        $firstUrl = $this->firstUrlFromCaption($caption);

        if ($postType === 'auto') {
            $postType = $hasVideos ? 'video' : ($hasImages ? 'media' : ($firstUrl ? 'link' : 'text'));
        }

        try {
            $response = match ($postType) {
                'text' => $this->linkedin->createTextPost($accessToken, $authorUrn, $caption),
                'link' => $this->linkedin->createLinkPost($accessToken, $authorUrn, $caption, $firstUrl ?: ''),
                'video' => $this->publishVideo($accessToken, $authorUrn, $caption, $mediaFiles),
                'media', 'image' => $this->publishImages($accessToken, $authorUrn, $caption, $mediaFiles),
                default => throw new RuntimeException("Unsupported LinkedIn post type [{$postType}]."),
            };
        } catch (LinkedinApiException|RuntimeException $exception) {
            return [
                'state' => 'failed',
                'error' => $exception->getMessage(),
                'url' => null,
                'remote_post_id' => null,
                'response' => null,
            ];
        }

        $remoteId = trim((string) ($response['id'] ?? ''));

        return [
            'state' => 'published',
            'error' => null,
            'url' => $remoteId !== '' ? 'https://www.linkedin.com/feed/update/'.$remoteId : 'https://www.linkedin.com/feed/',
            'remote_post_id' => $remoteId !== '' ? $remoteId : null,
            'response' => $response,
        ];
    }

    protected function publishVideo(string $accessToken, string $authorUrn, string $caption, Collection $mediaFiles): array
    {
        if ($mediaFiles->count() !== 1) {
            throw new RuntimeException('LinkedIn video posts require exactly one video file.');
        }

        /** @var AppFile $file */
        $file = $mediaFiles->first();

        if ($file->is_image || ! str_starts_with(strtolower((string) $file->mime_type), 'video/')) {
            throw new RuntimeException('LinkedIn video posts require a video file.');
        }

        return $this->linkedin->createVideoPost($accessToken, $authorUrn, $caption, $file);
    }

    protected function publishImages(string $accessToken, string $authorUrn, string $caption, Collection $mediaFiles): array
    {
        if ($mediaFiles->isEmpty()) {
            throw new RuntimeException('LinkedIn image posts require at least one image.');
        }

        if ($mediaFiles->contains(fn (AppFile $file): bool => ! $file->is_image)) {
            throw new RuntimeException('LinkedIn image posts support image files only.');
        }

        if ($mediaFiles->count() === 1) {
            /** @var AppFile $file */
            $file = $mediaFiles->first();

            return $this->linkedin->createImagePost($accessToken, $authorUrn, $caption, $file);
        }

        return $this->linkedin->createMultiImagePost($accessToken, $authorUrn, $caption, $mediaFiles);
    }

    protected function resolveMediaFiles(array $items): Collection
    {
        $files = $this->mediaUrls->resolveFiles($items)->keyBy('id');

        return collect($items)
            ->map(function (array $item) use ($files): ?AppFile {
                $fileId = (int) Arr::get($item, 'id');
                /** @var AppFile|null $file */
                $file = $fileId > 0 ? $files->get($fileId) : null;

                if (! $file || ! filled($file->path) || ! Storage::disk($file->disk)->exists($file->path)) {
                    return null;
                }

                return $file;
            })
            ->filter()
            ->values();
    }

    protected function firstUrlFromCaption(string $caption): ?string
    {
        if (preg_match('#https?://[^\s]+#i', $caption, $matches) !== 1) {
            return null;
        }

        return trim((string) ($matches[0] ?? '')) ?: null;
    }

    protected function inferAuthorUrn(SocialAccount $account): string
    {
        return match ((string) $account->capability_key) {
            'linkedin_page' => 'urn:li:organization:'.$account->external_id,
            default => 'urn:li:person:'.$account->external_id,
        };
    }
}
