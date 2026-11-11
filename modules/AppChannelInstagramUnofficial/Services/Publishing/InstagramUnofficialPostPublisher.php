<?php

namespace Modules\AppChannelInstagramUnofficial\Services\Publishing;

use App\Support\Storage\StorageDriverManager;
use Illuminate\Support\Facades\Storage;
use Modules\AppChannelInstagramUnofficial\Services\InstagramUnofficialService;
use Modules\AppChannels\Models\SocialAccount;
use Modules\AppPublishing\Contracts\PostPublisher;
use Modules\AppPublishing\Models\PublishingPost;
use Modules\AppPublishing\Support\PublishingMediaUrlResolver;

class InstagramUnofficialPostPublisher implements PostPublisher
{
    public function __construct(
        protected InstagramUnofficialService $instagram,
        protected PublishingMediaUrlResolver $mediaUrls,
        protected StorageDriverManager $storageDriverManager,
    ) {}

    public function publish(PublishingPost $post, SocialAccount $account): array
    {
        $authData = is_array($account->auth_data) ? $account->auth_data : [];
        $session = is_array($authData['session'] ?? null) ? $authData['session'] : [];

        if ($session === []) {
            return $this->fail('Instagram Unofficial session data is missing. Please reconnect the account.');
        }

        $this->instagram->setAuthData($session);

        $postData = is_array($post->data) ? $post->data : [];
        $options = is_array($postData['options'] ?? null) ? $postData['options'] : [];
        $caption = trim((string) ($postData['caption'] ?? ''));
        $postTo = (string) ($options['post_to'] ?? 'feed');
        $mediaItems = (array) ($postData['medias'] ?? []);
        $files = $this->mediaUrls->resolveFiles($mediaItems)->values();

        if ($files->isEmpty()) {
            return $this->fail('Instagram Unofficial requires at least one media item.');
        }

        $localizedFiles = [];
        $paths = $files
            ->map(function ($file) use (&$localizedFiles) {
                if (! filled($file->path) || ! Storage::disk($file->disk)->exists($file->path)) {
                    return null;
                }

                $localized = $this->storageDriverManager->resolveLocalPath((string) $file->disk, (string) $file->path, $file->name);
                $localizedFiles[] = $localized;

                return $localized['path'];
            })
            ->filter()
            ->values();

        if ($paths->isEmpty()) {
            return $this->fail('The selected Instagram media file could not be found.');
        }

        try {
            if ($postTo === 'stories') {
                if ($paths->count() !== 1 || ! $this->isImage($files->first()->mime_type)) {
                    return $this->fail('Instagram Unofficial stories currently support exactly one image.');
                }

                $result = $this->instagram->postStoryPhoto((string) $paths->first(), $caption, []);
            } elseif ($postTo === 'reels') {
                if ($paths->count() !== 1 || ! $this->isVideo($files->first()->mime_type)) {
                    return $this->fail('Instagram Unofficial reels require exactly one video.');
                }

                $result = $this->instagram->postReel((string) $paths->first(), $caption, []);
            } elseif ($paths->count() > 1) {
                if ($files->contains(fn ($file) => $this->isVideo($file->mime_type))) {
                    return $this->fail('Instagram Unofficial carousel currently supports images only.');
                }

                $result = $this->instagram->postCarousel($paths->all(), $caption, []);
            } elseif ($this->isVideo($files->first()->mime_type)) {
                $result = $this->instagram->postVideo((string) $paths->first(), $caption, []);
            } else {
                $result = $this->instagram->postPhoto((string) $paths->first(), $caption, []);
            }
        } catch (\Throwable $exception) {
            return $this->fail($exception->getMessage());
        } finally {
            foreach ($localizedFiles as $localizedFile) {
                if (($localizedFile['temporary'] ?? false) && filled($localizedFile['path'] ?? null)) {
                    @unlink((string) $localizedFile['path']);
                }
            }
        }

        $success = (bool) ($result['status'] ?? false);
        $remoteId = (string) data_get($result, 'data.media_id', data_get($result, 'data.id', ''));

        if (! $success) {
            return $this->fail((string) ($result['error'] ?? $result['message'] ?? 'Instagram Unofficial publishing failed.'));
        }

        return [
            'state' => 'published',
            'error' => null,
            'url' => data_get($result, 'data.url', $account->profile_url),
            'remote_post_id' => $remoteId,
            'response' => $result,
        ];
    }

    protected function isImage(?string $mimeType): bool
    {
        return str_starts_with(strtolower((string) $mimeType), 'image/');
    }

    protected function isVideo(?string $mimeType): bool
    {
        return str_starts_with(strtolower((string) $mimeType), 'video/');
    }

    protected function fail(string $message): array
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
