<?php

namespace Modules\AppChannelTiktokProfiles\Services\Publishing;

use App\Support\Storage\StorageDriverManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Modules\AppChannelTiktokProfiles\Services\Tiktok\TiktokApiException;
use Modules\AppChannelTiktokProfiles\Services\Tiktok\TiktokApiService;
use Modules\AppChannels\Models\SocialAccount;
use Modules\AppFiles\Models\AppFile;
use Modules\AppPublishing\Contracts\PostPublisher;
use Modules\AppPublishing\Models\PublishingPost;
use Modules\AppPublishing\Support\PublishingMediaInspector;
use Modules\AppPublishing\Support\PublishingMediaUrlResolver;

class TiktokPostPublisher implements PostPublisher
{
    public function __construct(
        protected TiktokApiService $tiktok,
        protected PublishingMediaUrlResolver $mediaUrls,
        protected StorageDriverManager $storageDriverManager,
    ) {}

    public function publish(PublishingPost $post, SocialAccount $account): array
    {
        $postData = is_array($post->data) ? $post->data : [];
        $options = is_array($postData['options'] ?? null) ? $postData['options'] : [];
        $caption = trim((string) ($postData['caption'] ?? ''));
        $mediaItems = (array) ($postData['medias'] ?? []);
        $analysis = PublishingMediaInspector::analyze($mediaItems);
        $files = $this->mediaUrls->resolveFiles($mediaItems);

        if (! $analysis['has_media'] || $files->isEmpty()) {
            return $this->fail(__('TikTok requires one video or at least one image.'));
        }

        try {
            $accessToken = $this->resolveAccessToken($account);
            $creator = $this->loadCreatorInfo($account, $accessToken);

            if ($analysis['has_video']) {
                return $this->publishVideo($account, $accessToken, $creator, $caption, $options, $files);
            }

            return $this->publishPhotos($account, $accessToken, $creator, $caption, $options, $files);
        } catch (\Throwable $exception) {
            return $this->fail($this->normalizePublishError($exception->getMessage()));
        }
    }

    protected function publishVideo(
        SocialAccount $account,
        string $accessToken,
        array $creator,
        string $caption,
        array $options,
        Collection $files,
    ): array {
        /** @var AppFile|null $file */
        $file = $files->first();

        if (! $file) {
            return $this->fail(__('The selected TikTok video could not be found.'));
        }

        if (array_key_exists('can_post', $creator) && ! $creator['can_post']) {
            return $this->fail(__('This TikTok profile cannot post right now. Please try again later.'));
        }

        $this->assertValidPrivacySelection($options, $creator);
        $this->assertInteractionPolicy($options, $creator, true);
        $this->assertCommercialDisclosure($options);
        $this->assertVideoDurationAllowed($file, $creator);

        $size = $this->resolveFileSize($file);
        $init = $this->tiktok->initializeDirectVideoPost($accessToken, [
            'title' => $caption,
            'privacy_level' => (string) ($options['tt_privacy'] ?? 'SELF_ONLY'),
            'disable_comment' => empty($options['tt_allow_comment']),
            'disable_duet' => empty($options['tt_allow_duet']),
            'disable_stitch' => empty($options['tt_allow_stitch']),
            'brand_content_toggle' => ! empty($options['tt_branded_content']),
            'brand_organic_toggle' => ! empty($options['tt_your_brand']),
        ], [
            'source' => 'FILE_UPLOAD',
            'video_size' => $size,
            'chunk_size' => $size,
            'total_chunk_count' => 1,
        ]);

        $uploadUrl = trim((string) ($init['upload_url'] ?? ''));
        $publishId = trim((string) ($init['publish_id'] ?? ''));

        if ($uploadUrl === '' || $publishId === '') {
            return $this->fail(__('TikTok did not return the upload information for this video.'));
        }

        $this->tiktok->uploadVideoFile($uploadUrl, $file);

        $status = $this->waitForPublishCompletion($accessToken, $publishId);
        $publicPostId = $this->resolvePublicPostId($status);

        return [
            'state' => 'published',
            'error' => null,
            'url' => $publicPostId !== '' ? 'https://www.tiktok.com/@'.$this->tiktokUsername($account).'/video/'.$publicPostId : ($account->profile_url ?: 'https://www.tiktok.com/'),
            'remote_post_id' => $publicPostId !== '' ? $publicPostId : $publishId,
            'response' => [
                'publish_id' => $publishId,
                'status' => $status,
                'creator' => $creator,
                'type' => 'video',
            ],
        ];
    }

    protected function publishPhotos(
        SocialAccount $account,
        string $accessToken,
        array $creator,
        string $caption,
        array $options,
        Collection $files,
    ): array {
        $this->cleanupOldPreparedPhotoAssets();

        $preparedPhotos = $this->preparePhotoAssets(
            $files->filter(fn (AppFile $file): bool => (bool) $file->is_image)->values()
        );
        $imageUrls = collect($preparedPhotos['urls'] ?? [])->filter()->values();

        if ($imageUrls->isEmpty()) {
            return $this->fail(__('TikTok photo posts require one or more images.'));
        }

        if (array_key_exists('can_post', $creator) && ! $creator['can_post']) {
            return $this->fail(__('This TikTok profile cannot post right now. Please try again later.'));
        }

        if (! $this->urlsArePubliclyAccessible($imageUrls->all())) {
            return $this->fail(__('TikTok photo posts require publicly accessible media URLs on a verified domain. Local or private preview URLs are not supported.'));
        }

        $this->assertValidPrivacySelection($options, $creator);
        $this->assertInteractionPolicy($options, $creator, false);
        $this->assertCommercialDisclosure($options);

        $init = $this->tiktok->initializePhotoPost($accessToken, [
            'title' => $this->photoTitle($caption),
            'description' => $caption,
            'privacy_level' => (string) ($options['tt_privacy'] ?? 'SELF_ONLY'),
            'disable_comment' => empty($options['tt_allow_comment']),
            'brand_content_toggle' => ! empty($options['tt_branded_content']),
            'brand_organic_toggle' => ! empty($options['tt_your_brand']),
        ], [
            'source' => 'PULL_FROM_URL',
            'photo_cover_index' => 0,
            'photo_images' => $imageUrls->all(),
        ]);

        $publishId = trim((string) ($init['publish_id'] ?? ''));

        if ($publishId === '') {
            return $this->fail(__('TikTok did not return a publish ID for this photo post.'));
        }

        $status = $this->waitForPublishCompletion($accessToken, $publishId);
        $publicPostId = $this->resolvePublicPostId($status);

        return [
            'state' => 'published',
            'error' => null,
            'url' => $account->profile_url ?: 'https://www.tiktok.com/',
            'remote_post_id' => $publicPostId !== '' ? $publicPostId : $publishId,
            'response' => [
                'publish_id' => $publishId,
                'status' => $status,
                'creator' => $creator,
                'type' => 'photo',
            ],
        ];
    }

    protected function resolveAccessToken(SocialAccount $account): string
    {
        $token = trim((string) $account->access_token);

        if ($token !== '') {
            return $token;
        }

        $refreshed = $this->tiktok->refreshAccountAccessToken($account);

        return trim((string) ($refreshed['access_token'] ?? ''));
    }

    protected function loadCreatorInfo(SocialAccount $account, string &$accessToken): array
    {
        try {
            return $this->tiktok->queryCreatorInfo($accessToken);
        } catch (TiktokApiException $exception) {
            if (! str_contains(strtolower($exception->getMessage()), 'access_token_invalid')) {
                throw $exception;
            }
        }

        $token = $this->tiktok->refreshAccountAccessToken($account);
        $accessToken = trim((string) ($token['access_token'] ?? $accessToken));

        return $this->tiktok->queryCreatorInfo($accessToken);
    }

    protected function waitForPublishCompletion(string $accessToken, string $publishId): array
    {
        $latest = [];

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $latest = $this->tiktok->fetchPublishStatus($accessToken, $publishId);
            $status = strtoupper(trim((string) ($latest['status'] ?? '')));

            if ($status === 'PUBLISH_COMPLETE') {
                return $latest;
            }

            if ($status === 'FAILED') {
                $reason = trim((string) ($latest['fail_reason'] ?? ''));
                throw TiktokApiException::publishFailed($reason !== '' ? __('TikTok publishing failed: :reason', ['reason' => $reason]) : __('TikTok publishing failed.'));
            }

            sleep(3);
        }

        throw TiktokApiException::publishFailed(__('TikTok did not finish processing this post in time. Check the post status again shortly.'));
    }

    protected function assertValidPrivacySelection(array $options, array $creator): void
    {
        $privacy = trim((string) ($options['tt_privacy'] ?? ''));
        $allowed = collect((array) ($creator['privacy_level_options'] ?? []))
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values()
            ->all();

        if ($privacy === '') {
            throw TiktokApiException::publishFailed(__('TikTok requires a privacy setting.'));
        }

        if ($allowed !== [] && ! in_array($privacy, $allowed, true)) {
            throw TiktokApiException::publishFailed(__('The selected TikTok privacy setting is not available for this profile.'));
        }
    }

    protected function assertInteractionPolicy(array $options, array $creator, bool $isVideo): void
    {
        if (! empty($creator['comment_disabled']) && ! empty($options['tt_allow_comment'])) {
            throw TiktokApiException::publishFailed(__('Comments are disabled in this TikTok profile privacy settings.'));
        }

        if (! $isVideo) {
            return;
        }

        if (! empty($creator['duet_disabled']) && ! empty($options['tt_allow_duet'])) {
            throw TiktokApiException::publishFailed(__('Duet is disabled in this TikTok profile privacy settings.'));
        }

        if (! empty($creator['stitch_disabled']) && ! empty($options['tt_allow_stitch'])) {
            throw TiktokApiException::publishFailed(__('Stitch is disabled in this TikTok profile privacy settings.'));
        }
    }

    protected function assertCommercialDisclosure(array $options): void
    {
        $commercialEnabled = ! empty($options['tt_commercial_content']);
        $yourBrand = $commercialEnabled && ! empty($options['tt_your_brand']);
        $brandedContent = $commercialEnabled && ! empty($options['tt_branded_content']);

        if ($commercialEnabled && ! $yourBrand && ! $brandedContent) {
            throw TiktokApiException::publishFailed(__('Select at least one TikTok commercial disclosure option.'));
        }

        if (empty($options['tt_consent'])) {
            throw TiktokApiException::publishFailed(__('You must confirm TikTok music and disclosure consent before posting.'));
        }
    }

    protected function resolveFileSize(AppFile $file): int
    {
        $size = (int) $file->size_bytes;

        if ($size > 0) {
            return $size;
        }

        if (filled($file->disk) && filled($file->path) && Storage::disk($file->disk)->exists($file->path)) {
            return max(1, (int) Storage::disk($file->disk)->size($file->path));
        }

        return 1;
    }

    protected function assertVideoDurationAllowed(AppFile $file, array $creator): void
    {
        $maxDuration = max(0, (int) ($creator['max_video_post_duration_sec'] ?? 0));

        if ($maxDuration <= 0) {
            return;
        }

        $actualDuration = $this->probeVideoDuration($file);

        if ($actualDuration > 0 && $actualDuration > $maxDuration) {
            throw TiktokApiException::publishFailed(__('TikTok video duration exceeds the allowed limit of :seconds seconds.', ['seconds' => $maxDuration]));
        }
    }

    protected function probeVideoDuration(AppFile $file): int
    {
        if (! filled($file->disk) || ! filled($file->path) || ! Storage::disk($file->disk)->exists($file->path)) {
            return 0;
        }

        $localized = $this->storageDriverManager->resolveLocalPath((string) $file->disk, (string) $file->path, $file->name);
        $absolutePath = $localized['path'];
        $ffprobe = getenv('FFPROBE_BINARIES') ?: (function_exists('env') ? env('FFPROBE_BINARIES', null) : null);

        if (! $ffprobe) {
            $ffprobe = str_starts_with(strtoupper(PHP_OS), 'WIN') ? 'C:/ffmpeg/bin/ffprobe.exe' : 'ffprobe';
        }

        $stderr = str_starts_with(strtoupper(PHP_OS), 'WIN') ? '2>NUL' : '2>/dev/null';
        $command = escapeshellarg($ffprobe).' -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 '.escapeshellarg($absolutePath).' '.$stderr;
        $output = @shell_exec($command);

        if ($localized['temporary']) {
            @unlink($absolutePath);
        }

        if (! is_string($output) || trim($output) === '') {
            return 0;
        }

        return (int) ceil((float) trim($output));
    }

    protected function resolvePublicPostId(array $status): string
    {
        $items = collect((array) ($status['publicaly_available_post_id'] ?? []))
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values();

        return (string) ($items->first() ?? '');
    }

    protected function photoTitle(string $caption): string
    {
        $title = trim(preg_replace('/\s+/', ' ', $caption) ?? '');

        return mb_substr($title, 0, 90);
    }

    protected function preparePhotoAssets(Collection $files): array
    {
        $urls = [];
        $paths = [];

        foreach ($files as $file) {
            $prepared = $this->preparePhotoAsset($file);
            $urls[] = $prepared['url'];
            $paths[] = $prepared['path'];
        }

        return [
            'urls' => $urls,
            'paths' => $paths,
        ];
    }

    protected function preparePhotoAsset(AppFile $file): array
    {
        $extension = strtolower((string) $file->extension);

        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            throw TiktokApiException::publishFailed(__('TikTok photo posts only support JPEG or WebP images. PNG files will be converted automatically.'));
        }

        if (! filled($file->disk) || ! filled($file->path) || ! Storage::disk($file->disk)->exists($file->path)) {
            throw TiktokApiException::publishFailed(__('The selected TikTok image file could not be found.'));
        }

        return $extension === 'png'
            ? $this->convertPngToJpegAsset($file)
            : $this->copyImageToPublicCache($file, $extension === 'jpeg' ? 'jpg' : $extension);
    }

    protected function convertPngToJpegAsset(AppFile $file): array
    {
        if (! function_exists('imagecreatefrompng') || ! function_exists('imagejpeg')) {
            throw TiktokApiException::publishFailed(__('PNG to JPEG conversion requires the GD extension in PHP.'));
        }

        $localized = $this->storageDriverManager->resolveLocalPath((string) $file->disk, (string) $file->path, $file->name);
        $sourcePath = $localized['path'];
        $image = @imagecreatefrompng($sourcePath);

        if (! $image) {
            throw TiktokApiException::publishFailed(__('The selected PNG image could not be converted to JPEG for TikTok.'));
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $canvas = imagecreatetruecolor($width, $height);

        if (! $canvas) {
            imagedestroy($image);

            throw TiktokApiException::publishFailed(__('The selected PNG image could not be prepared for TikTok.'));
        }

        $background = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $background);
        imagecopy($canvas, $image, 0, 0, 0, 0, $width, $height);

        $directory = public_path('uploads/tiktok-photo-cache');
        File::ensureDirectoryExists($directory);

        $filename = 'file-'.$file->id.'-'.md5($file->updated_at?->timestamp.'|'.$file->size_bytes.'|'.$file->path).'.jpg';
        $targetPath = $directory.DIRECTORY_SEPARATOR.$filename;

        $saved = imagejpeg($canvas, $targetPath, 92);

        imagedestroy($canvas);
        imagedestroy($image);

        if ($localized['temporary']) {
            @unlink($sourcePath);
        }

        if (! $saved) {
            throw TiktokApiException::publishFailed(__('The selected PNG image could not be converted to JPEG for TikTok.'));
        }

        return [
            'url' => url('uploads/tiktok-photo-cache/'.$filename),
            'path' => $targetPath,
        ];
    }

    protected function copyImageToPublicCache(AppFile $file, string $extension): array
    {
        $localized = $this->storageDriverManager->resolveLocalPath((string) $file->disk, (string) $file->path, $file->name);
        $sourcePath = $localized['path'];
        $directory = public_path('uploads/tiktok-photo-cache');
        File::ensureDirectoryExists($directory);

        $filename = 'file-'.$file->id.'-'.md5($file->updated_at?->timestamp.'|'.$file->size_bytes.'|'.$file->path).'.'.$extension;
        $targetPath = $directory.DIRECTORY_SEPARATOR.$filename;

        if (! File::exists($targetPath)) {
            File::copy($sourcePath, $targetPath);
        }

        if ($localized['temporary']) {
            @unlink($sourcePath);
        }

        return [
            'url' => url('uploads/tiktok-photo-cache/'.$filename),
            'path' => $targetPath,
        ];
    }

    protected function cleanupOldPreparedPhotoAssets(): void
    {
        $directory = public_path('uploads/tiktok-photo-cache');

        if (! File::isDirectory($directory)) {
            return;
        }

        foreach (File::files($directory) as $file) {
            try {
                if ($file->getMTime() < now()->subDay()->getTimestamp()) {
                    File::delete($file->getPathname());
                }
            } catch (\Throwable) {
            }
        }
    }

    protected function urlsArePubliclyAccessible(array $urls): bool
    {
        foreach ($urls as $url) {
            $host = strtolower((string) parse_url((string) $url, PHP_URL_HOST));

            if ($host === '' || in_array($host, ['localhost', '127.0.0.1'], true) || str_ends_with($host, '.local') || str_ends_with($host, '.test')) {
                return false;
            }
        }

        return true;
    }

    protected function tiktokUsername(SocialAccount $account): string
    {
        $username = trim(ltrim((string) $account->username, '@'));

        return $username !== '' ? $username : 'video';
    }

    protected function normalizePublishError(string $message): string
    {
        $normalized = strtolower(trim($message));

        if (str_contains($normalized, 'photo_pull_failed')) {
            return __('TikTok photo posts only work with publicly accessible image URLs on a verified domain. Use a JPEG or WebP image that TikTok can fetch directly.');
        }

        if (str_contains($normalized, 'file_format_check_failed')) {
            return __('TikTok photo posts only support JPEG or WebP images.');
        }

        return $message;
    }

    protected function fail(string $message): array
    {
        return [
            'state' => 'failed',
            'error' => $message,
            'url' => null,
            'remote_post_id' => null,
            'response' => [
                'provider' => 'tiktok',
                'capability' => 'tiktok_profile',
            ],
        ];
    }
}
