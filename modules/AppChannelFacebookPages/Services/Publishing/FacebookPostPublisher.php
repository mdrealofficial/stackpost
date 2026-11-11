<?php

namespace Modules\AppChannelFacebookPages\Services\Publishing;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Modules\AppChannelFacebookPages\Services\Facebook\FacebookApiService;
use Modules\AppChannels\Models\SocialAccount;
use Modules\AppFiles\Models\AppFile;
use Modules\AppPublishing\Contracts\PostDeletionCapable;
use Modules\AppPublishing\Contracts\PostPublisher;
use Modules\AppPublishing\Models\PublishingPost;
use Modules\AppPublishing\Support\PublishingMediaUrlResolver;
use RuntimeException;
use Throwable;

class FacebookPostPublisher implements PostPublisher, PostDeletionCapable
{
    public function __construct(
        protected FacebookApiService $facebook,
        protected PublishingMediaUrlResolver $mediaUrls,
    ) {}

    public function publish(PublishingPost $post, SocialAccount $account): array
    {
        $postData = is_array($post->data) ? $post->data : [];
        $options = is_array($postData['options'] ?? null) ? $postData['options'] : [];
        $caption = trim((string) ($postData['caption'] ?? ''));
        $postTo = (string) ($options['post_to'] ?? 'feed');

        $pageId = (string) ($account->external_id ?: data_get($account->auth_data, 'page_payload.id', ''));
        $pageToken = (string) ($account->access_token ?: data_get($account->auth_data, 'page_payload.access_token', ''));

        if ($pageId === '' || $pageToken === '') {
            throw new RuntimeException('The Facebook page is missing a page ID or access token.');
        }

        $mediaAssets = $this->resolveMediaAssets((array) ($postData['medias'] ?? []));

        if ($mediaAssets->isEmpty()) {
            if ($postTo !== 'feed') {
                return $this->failUnsupported('Facebook Reels and Stories require media.');
            }

            $response = Http::asForm()
                ->timeout(60)
                ->post($this->facebook->graphUrl("{$pageId}/feed"), [
                    'message' => $caption,
                    'access_token' => $pageToken,
                ]);

            return $this->normalizeFacebookResponse($response->json(), $pageToken);
        }

        return match ($postTo) {
            'reels' => $this->publishReel($pageId, $pageToken, $caption, $mediaAssets),
            'stories' => $this->publishStory($pageId, $pageToken, $caption, $mediaAssets, $account),
            default => $this->publishFeed($pageId, $pageToken, $caption, $mediaAssets),
        };
    }

    protected function publishFeed(string $pageId, string $pageToken, string $caption, Collection $mediaAssets): array
    {
        $imageAssets = $mediaAssets->filter(fn (array $asset) => $asset['file']->is_image)->values();
        $videoAssets = $mediaAssets->reject(fn (array $asset) => $asset['file']->is_image)->values();

        if ($videoAssets->isNotEmpty() && $imageAssets->isNotEmpty()) {
            return $this->failUnsupported('Facebook Feed publishing does not support mixing images and videos in one post.');
        }

        if ($videoAssets->count() > 1) {
            return $this->failUnsupported('Facebook Feed publishing currently supports a single video only.');
        }

        if ($videoAssets->count() === 1) {
            return $this->publishVideoToFeed($pageId, $pageToken, $caption, $videoAssets->first());
        }

        if ($imageAssets->count() === 1) {
            $file = $imageAssets->first()['file'];
            $response = Http::timeout(120)
                ->attach('source', $this->fileContents($file), $file->name)
                ->post($this->facebook->graphUrl("{$pageId}/photos"), [
                    'message' => $caption,
                    'published' => 'true',
                    'access_token' => $pageToken,
                ]);

            return $this->normalizeFacebookResponse($response->json(), $pageToken);
        }

        $attachedMedia = [];

        foreach ($imageAssets as $asset) {
            $file = $asset['file'];
            $uploadResponse = Http::timeout(120)
                ->attach('source', $this->fileContents($file), $file->name)
                ->post($this->facebook->graphUrl("{$pageId}/photos"), [
                    'published' => 'false',
                    'access_token' => $pageToken,
                ]);

            $uploadPayload = $uploadResponse->json();
            $mediaId = (string) ($uploadPayload['id'] ?? '');

            if (! $uploadResponse->successful() || $mediaId === '') {
                throw new RuntimeException((string) (data_get($uploadPayload, 'error.message') ?: 'Facebook media upload failed.'));
            }

            $attachedMedia[] = ['media_fbid' => $mediaId];
        }

        $payload = [
            'message' => $caption,
            'access_token' => $pageToken,
        ];

        foreach ($attachedMedia as $index => $media) {
            $payload["attached_media[{$index}]"] = json_encode($media, JSON_UNESCAPED_SLASHES);
        }

        $response = Http::asForm()
            ->timeout(60)
            ->post($this->facebook->graphUrl("{$pageId}/feed"), $payload);

        return $this->normalizeFacebookResponse($response->json(), $pageToken);
    }

    protected function publishVideoToFeed(string $pageId, string $pageToken, string $caption, array $asset): array
    {
        /** @var AppFile $file */
        $file = $asset['file'];

        $response = Http::timeout(180)
            ->attach('source', $this->fileContents($file), $file->name)
            ->post($this->facebook->graphUrl("{$pageId}/videos"), [
                'description' => $caption,
                'access_token' => $pageToken,
            ]);

        return $this->normalizeFacebookResponse($response->json(), $pageToken);
    }

    protected function publishReel(string $pageId, string $pageToken, string $caption, Collection $mediaAssets): array
    {
        if ($mediaAssets->count() !== 1) {
            return $this->failUnsupported('Facebook Reels publishing requires exactly one video.');
        }

        /** @var array{file:AppFile,url:string} $asset */
        $asset = $mediaAssets->first();

        if ($asset['file']->is_image) {
            return $this->failUnsupported('Facebook Reels publishing requires a video file.');
        }

        [$videoId] = $this->startFacebookVideoSession("{$pageId}/video_reels", $pageToken);
        $this->uploadHostedFacebookVideo($videoId, $pageToken, $asset['url']);

        $response = Http::asForm()
            ->timeout(120)
            ->post($this->facebook->graphUrl("{$pageId}/video_reels"), [
                'access_token' => $pageToken,
                'video_id' => $videoId,
                'upload_phase' => 'finish',
                'video_state' => 'PUBLISHED',
                'description' => $caption,
            ]);

        $payload = $response->json();

        if (! $response->successful() || ! ((bool) ($payload['success'] ?? false))) {
            return $this->normalizeFailure($payload, 'Facebook could not publish the reel.');
        }

        return [
            'state' => 'published',
            'error' => null,
            'url' => $this->resolveFacebookPermalink($videoId, $pageToken),
            'remote_post_id' => $videoId,
            'response' => $payload,
        ];
    }

    protected function publishStory(string $pageId, string $pageToken, string $caption, Collection $mediaAssets, SocialAccount $account): array
    {
        if ($mediaAssets->count() !== 1) {
            return $this->failUnsupported('Facebook Stories publishing requires exactly one media item.');
        }

        if (! $this->accountHasCreateContentTask($account)) {
            return [
                'state' => 'failed',
                'error' => 'Missing required Facebook Page task CREATE_CONTENT. Reconnect this page with a user who can create content on the Page.',
                'url' => null,
                'remote_post_id' => null,
                'response' => null,
            ];
        }

        $eligibilityError = $this->verifyStoryEligibilityWithGraph($pageId, $pageToken);
        if ($eligibilityError !== null) {
            return [
                'state' => 'failed',
                'error' => $eligibilityError,
                'url' => null,
                'remote_post_id' => null,
                'response' => null,
            ];
        }

        /** @var array{file:AppFile,url:string} $asset */
        $asset = $mediaAssets->first();

        if ($asset['file']->is_image) {
            /** @var AppFile $file */
            $file = $asset['file'];

            // Step 1 (Meta doc): upload photo first with published=false to get photo_id.
            $uploadResponse = Http::timeout(120)
                ->attach('source', $this->fileContents($file), $file->name)
                ->post($this->facebook->graphUrl("{$pageId}/photos"), [
                    'access_token' => $pageToken,
                    'published' => 'false',
                ]);

            $uploadPayload = $uploadResponse->json();
            $photoId = trim((string) ($uploadPayload['id'] ?? ''));

            if (! $uploadResponse->successful() || $photoId === '' || data_get($uploadPayload, 'error.message')) {
                return $this->normalizeStoryFailure($uploadPayload, 'Facebook could not upload the photo story media.');
            }

            // Step 2 (Meta doc): publish story using photo_id.
            $response = Http::asForm()
                ->timeout(120)
                ->post($this->facebook->graphUrl("{$pageId}/photo_stories"), [
                    'access_token' => $pageToken,
                    'photo_id' => $photoId,
                ]);

            $payload = $response->json();
            $success = (bool) ($payload['success'] ?? false);
            $storyId = trim((string) ($payload['post_id'] ?? $payload['id'] ?? ''));

            if (! $response->successful() || ! $success || $storyId === '' || data_get($payload, 'error.message')) {
                return $this->normalizeStoryFailure($payload, 'Facebook could not publish the photo story.');
            }

            return [
                'state' => 'published',
                'error' => null,
                'url' => $this->resolveFacebookStoryUrl($pageId, $pageToken, $storyId),
                'remote_post_id' => $storyId,
                'response' => $payload,
            ];
        }

        /** @var AppFile $file */
        $file = $asset['file'];

        // Step 1 (Meta doc): initialize video story upload session.
        try {
            [$videoId, $uploadUrl] = $this->startFacebookVideoSession("{$pageId}/video_stories", $pageToken);

            // Step 2 (Meta doc): upload local file bytes to rupload URL.
            $this->uploadVideoToRupload($uploadUrl, $file);

            // Step 3 (Meta doc): finish/publish with video_id.
            $response = Http::asForm()
                ->timeout(120)
                ->post($this->facebook->graphUrl("{$pageId}/video_stories"), [
                    'access_token' => $pageToken,
                    'video_id' => $videoId,
                    'upload_phase' => 'finish',
                ]);
        } catch (Throwable $exception) {
            return [
                'state' => 'failed',
                'error' => $exception->getMessage(),
                'url' => null,
                'remote_post_id' => null,
                'response' => null,
            ];
        }

        $payload = $response->json();
        $success = (bool) ($payload['success'] ?? false);
        $remoteId = trim((string) ($payload['post_id'] ?? $payload['id'] ?? $videoId));

        if (! $response->successful() || ! $success || $remoteId === '' || data_get($payload, 'error.message')) {
            return $this->normalizeStoryFailure($payload, 'Facebook could not publish the video story.');
        }

        return [
            'state' => 'published',
            'error' => null,
            'url' => $this->resolveFacebookStoryUrl($pageId, $pageToken, $remoteId),
            'remote_post_id' => $remoteId,
            'response' => $payload,
        ];
    }

    protected function normalizeFacebookResponse(array $payload, string $accessToken): array
    {
        $error = data_get($payload, 'error.message');

        if ($error) {
            return [
                'state' => 'failed',
                'error' => (string) $error,
                'url' => null,
                'remote_post_id' => null,
                'response' => $payload,
            ];
        }

        $remotePostId = (string) ($payload['post_id'] ?? $payload['id'] ?? '');
        $permalinkUrl = $remotePostId !== '' ? $this->resolveFacebookPermalink($remotePostId, $accessToken) : null;

        return [
            'state' => 'published',
            'error' => null,
            'url' => $permalinkUrl ?: ($remotePostId !== '' ? 'https://www.facebook.com/'.$remotePostId : null),
            'remote_post_id' => $remotePostId !== '' ? $remotePostId : null,
            'response' => $payload,
        ];
    }

    public function delete(PublishingPost $post, SocialAccount $account): array
    {
        $pageToken = (string) ($account->access_token ?: data_get($account->auth_data, 'page_payload.access_token', ''));
        $remotePostId = trim((string) data_get($post->result, 'remote_post_id', ''));

        if ($pageToken === '' || $remotePostId === '') {
            return [
                'state' => 'failed',
                'error' => 'The published Facebook post is missing a remote ID or page token.',
            ];
        }

        $response = Http::timeout(60)->delete($this->facebook->graphUrl($remotePostId), [
            'access_token' => $pageToken,
        ]);

        $payload = $response->json();

        if (! $response->successful() || ! ((bool) ($payload['success'] ?? false))) {
            return [
                'state' => 'failed',
                'error' => (string) (data_get($payload, 'error.message') ?: 'Facebook could not delete the published post.'),
                'response' => $payload,
            ];
        }

        return [
            'state' => 'deleted',
            'error' => null,
            'response' => $payload,
        ];
    }

    protected function resolveFacebookPermalink(string $postId, string $accessToken): ?string
    {
        $response = Http::timeout(30)->get($this->facebook->graphUrl($postId), [
            'fields' => 'permalink_url',
            'access_token' => $accessToken,
        ]);

        if (! $response->successful()) {
            return null;
        }

        $url = trim((string) data_get($response->json(), 'permalink_url', ''));

        return $url !== '' ? $url : null;
    }

    protected function resolveMediaAssets(array $items): Collection
    {
        $files = $this->mediaUrls->resolveFiles($items)->keyBy('id');

        return collect($items)
            ->map(function (array $item) use ($files): ?array {
                $fileId = (int) Arr::get($item, 'id');
                /** @var AppFile|null $file */
                $file = $fileId > 0 ? $files->get($fileId) : null;

                if (! $file) {
                    return null;
                }

                return [
                    'file' => $file,
                    'url' => $this->mediaUrls->publicUrlForFile($file),
                ];
            })
            ->filter()
            ->values();
    }

    protected function startFacebookVideoSession(string $endpoint, string $pageToken): array
    {
        $response = Http::asForm()
            ->timeout(60)
            ->post($this->facebook->graphUrl($endpoint), [
                'access_token' => $pageToken,
                'upload_phase' => 'start',
            ]);

        $payload = $response->json();
        $videoId = trim((string) ($payload['video_id'] ?? $payload['id'] ?? ''));
        $uploadUrl = trim((string) ($payload['upload_url'] ?? ''));

        if (! $response->successful() || $videoId === '' || $uploadUrl === '') {
            throw new RuntimeException((string) (data_get($payload, 'error.message') ?: 'Facebook could not initialize the video upload session.'));
        }

        return [$videoId, $uploadUrl];
    }

    protected function uploadHostedFacebookVideo(string $videoId, string $pageToken, string $fileUrl): void
    {
        $response = Http::asForm()
            ->timeout(180)
            ->post($this->facebook->graphUrl($videoId), [
                'access_token' => $pageToken,
                'file_url' => $fileUrl,
            ]);

        $payload = $response->json();

        if (! $response->successful() || data_get($payload, 'error.message')) {
            throw new RuntimeException((string) (data_get($payload, 'error.message') ?: 'Facebook could not upload the hosted video.'));
        }
    }

    protected function resolveFacebookStoryUrl(string $pageId, string $accessToken, string $storyPostId): ?string
    {
        $storyPostId = trim($storyPostId);

        if ($storyPostId === '') {
            return null;
        }

        $response = Http::timeout(30)->get($this->facebook->graphUrl("{$pageId}/stories"), [
            'fields' => 'post_id,url,status',
            'limit' => 25,
            'access_token' => $accessToken,
        ]);

        if (! $response->successful()) {
            return null;
        }

        $url = collect((array) data_get($response->json(), 'data', []))
            ->first(function ($row) use ($storyPostId) {
                if (! is_array($row)) {
                    return false;
                }

                return trim((string) ($row['post_id'] ?? '')) === $storyPostId
                    && trim((string) ($row['url'] ?? '')) !== '';
            });

        return is_array($url) ? trim((string) ($url['url'] ?? '')) ?: null : null;
    }

    protected function uploadVideoToRupload(string $uploadUrl, AppFile $file): void
    {
        $contents = $this->fileContents($file);
        $response = Http::withHeaders([
                'offset' => '0',
                'file_size' => (string) strlen($contents),
            ])
            ->timeout(180)
            ->withBody($contents, 'application/octet-stream')
            ->post($uploadUrl);

        $payload = $response->json();
        $message = (string) (data_get($payload, 'error.message') ?: data_get($payload, 'debug_info.message') ?: '');

        if (! $response->successful() || ! ((bool) ($payload['success'] ?? false))) {
            if ($message === '') {
                $message = 'Facebook could not upload the video story media.';
            }

            throw new RuntimeException($message);
        }
    }

    protected function fileContents(AppFile $file): string
    {
        if (! filled($file->path) || ! Storage::disk($file->disk)->exists($file->path)) {
            throw new RuntimeException("Media file [{$file->name}] is no longer available.");
        }

        return (string) Storage::disk($file->disk)->get($file->path);
    }

    protected function accountHasCreateContentTask(SocialAccount $account): bool
    {
        $tasks = collect((array) data_get($account->auth_data, 'page_payload.tasks', []))
            ->map(fn ($task) => strtoupper(trim((string) $task)))
            ->filter()
            ->values();

        if ($tasks->isEmpty()) {
            // Some legacy connections may not persist task info; allow API to decide.
            return true;
        }

        return $tasks->contains('CREATE_CONTENT');
    }

    protected function verifyStoryEligibilityWithGraph(string $pageId, string $pageToken): ?string
    {
        $response = Http::timeout(30)->get($this->facebook->graphUrl($pageId), [
            'fields' => 'id,name',
            'access_token' => $pageToken,
        ]);

        $payload = $response->json();
        $errorMessage = trim((string) (data_get($payload, 'error.error_user_msg') ?: data_get($payload, 'error.message') ?: ''));
        $errorCode = (int) data_get($payload, 'error.code', 0);
        $errorSubcode = (int) data_get($payload, 'error.error_subcode', 0);

        if (! $response->successful()) {
            if ($errorMessage !== '') {
                return $errorMessage.' [code '.$errorCode.($errorSubcode ? ', subcode '.$errorSubcode : '').']';
            }

            return 'Unable to verify Facebook Page permissions for Stories publishing.';
        }

        // Stories-specific capability check: some pages can post feed but are not eligible for stories API.
        $storiesCheck = Http::timeout(30)->get($this->facebook->graphUrl("{$pageId}/stories"), [
            'fields' => 'post_id,status',
            'limit' => 1,
            'access_token' => $pageToken,
        ]);

        if (! $storiesCheck->successful()) {
            $storiesPayload = $storiesCheck->json();
            $storiesMessage = trim((string) (
                data_get($storiesPayload, 'error.error_user_msg')
                ?: data_get($storiesPayload, 'error.message')
                ?: ''
            ));
            $storiesCode = (int) data_get($storiesPayload, 'error.code', 0);
            $storiesSubcode = (int) data_get($storiesPayload, 'error.error_subcode', 0);

            if ($storiesMessage !== '') {
                return $storiesMessage.' [code '.$storiesCode.($storiesSubcode ? ', subcode '.$storiesSubcode : '').']';
            }

            return 'This Page token can publish Feed but is not eligible for Facebook Stories API yet.';
        }

        return null;
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

    protected function normalizeFailure(array $payload, string $fallback): array
    {
        $errorMessage = trim((string) (
            data_get($payload, 'error.error_user_msg')
            ?: data_get($payload, 'error.message')
            ?: data_get($payload, 'message')
            ?: ''
        ));
        $errorCode = data_get($payload, 'error.code');
        $errorSubcode = data_get($payload, 'error.error_subcode');
        $errorType = trim((string) data_get($payload, 'error.type', ''));

        if ($errorMessage !== '' && $errorCode) {
            $errorMessage .= " [code {$errorCode}".($errorSubcode ? ", subcode {$errorSubcode}" : '').']';
        } elseif ($errorMessage === '' && $errorCode) {
            $errorMessage = "Facebook API error code {$errorCode}".($errorSubcode ? " (subcode {$errorSubcode})" : '');
        }

        if ($errorMessage === '' && $errorType !== '') {
            $errorMessage = $errorType;
        }

        return [
            'state' => 'failed',
            'error' => $errorMessage !== '' ? $errorMessage : $fallback,
            'url' => null,
            'remote_post_id' => null,
            'response' => $payload,
        ];
    }

    protected function normalizeStoryFailure(array $payload, string $fallback): array
    {
        $errorCode = (int) data_get($payload, 'error.code', 0);
        $rawMessage = strtolower(trim((string) (
            data_get($payload, 'error.error_user_msg')
            ?: data_get($payload, 'error.message')
            ?: data_get($payload, 'message')
            ?: ''
        )));

        if ($errorCode === 1) {
            return [
                'state' => 'failed',
                'error' => 'Facebook Stories API is unavailable for this page/app right now. Reconnect the page with full permissions and ensure Stories publishing is enabled in Meta app review.',
                'url' => null,
                'remote_post_id' => null,
                'response' => $payload,
            ];
        }

        if (str_contains($rawMessage, 'not authorized') || str_contains($rawMessage, 'permission')) {
            return [
                'state' => 'failed',
                'error' => 'Không có quyền đăng Facebook Story cho Page này. Hãy reconnect bằng tài khoản quản trị có quyền CREATE_CONTENT.',
                'url' => null,
                'remote_post_id' => null,
                'response' => $payload,
            ];
        }

        return $this->normalizeFailure($payload, $fallback);
    }
}
