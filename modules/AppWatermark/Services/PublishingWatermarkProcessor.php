<?php

namespace Modules\AppWatermark\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\AdminUser\Models\Team;
use Modules\AdminUser\Models\User;
use Modules\AppFiles\Models\AppFile;
use Modules\AppFiles\Support\FileManager;
use Modules\AppPublishing\Models\PublishingPost;
use Modules\AppWatermark\Models\PublishingWatermark;

class PublishingWatermarkProcessor
{
    protected const SUPPORTED_SOURCE_MIME_TYPES = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/webp',
    ];

    protected const PREVIEW_REFERENCE_WIDTH = 500;

    public function __construct(
        protected FileManager $files,
    ) {}

    public function applyToPostData(PublishingPost $post): array
    {
        $postData = is_array($post->data) ? $post->data : [];
        $mediaItems = collect((array) ($postData['medias'] ?? []))->values();

        if ($mediaItems->isEmpty() || ! function_exists('imagecreatefromstring')) {
            return $postData;
        }

        $owner = $this->resolveWorkspaceOwner($post);
        $account = $post->account;

        if (! $owner || ! $account || ! $this->watermarkFeatureEnabled($owner)) {
            return $postData;
        }

        $watermark = $this->resolveWatermark($owner, (int) $account->id, $post->team_id ? (int) $post->team_id : null);

        if (! $watermark) {
            return $postData;
        }

        $changed = false;
        $resolvedItems = $mediaItems->map(function ($item) use ($owner, $watermark, $post, &$changed) {
            if (! is_array($item)) {
                return $item;
            }

            $updated = $this->applyToMediaItem($item, $owner, $watermark, $post);

            if ($updated !== $item) {
                $changed = true;
            }

            return $updated;
        })->values()->all();

        if (! $changed) {
            return $postData;
        }

        $postData['medias'] = $resolvedItems;
        Arr::set($postData, 'options.metadata.watermark_applied_at', now()->toIso8601String());
        Arr::set($postData, 'options.metadata.watermark_rule_id', (int) $watermark->id);

        return $postData;
    }

    public function cleanupGeneratedMedia(PublishingPost $post): void
    {
        $postData = is_array($post->data) ? $post->data : [];
        $mediaItems = collect((array) ($postData['medias'] ?? []))->values();

        if ($mediaItems->isEmpty()) {
            return;
        }

        $changed = false;
        $restoredItems = $mediaItems->map(function ($item) use (&$changed) {
            if (! is_array($item) || ! (bool) Arr::get($item, 'watermark_applied', false)) {
                return $item;
            }

            $changed = true;
            $generatedFileId = (int) Arr::get($item, 'watermark_generated_file_id', 0);
            $sourceFileId = (int) Arr::get($item, 'watermark_source_id', 0);

            if ($generatedFileId > 0) {
                $generatedFile = AppFile::query()->find($generatedFileId);

                if ($generatedFile) {
                    try {
                        $this->files->deleteTree($generatedFile);
                    } catch (\Throwable $exception) {
                        report($exception);
                    }
                }
            }

            if ($sourceFileId <= 0) {
                return Arr::except($item, [
                    'watermark_applied',
                    'watermark_source_id',
                    'watermark_generated_file_id',
                ]);
            }

            $sourceFile = AppFile::query()->find($sourceFileId);

            if (! $sourceFile) {
                return Arr::except($item, [
                    'watermark_applied',
                    'watermark_source_id',
                    'watermark_generated_file_id',
                ]);
            }

            return array_merge(
                Arr::except($item, [
                    'watermark_applied',
                    'watermark_source_id',
                    'watermark_generated_file_id',
                ]),
                [
                    'id' => (int) $sourceFile->id,
                    'idSecure' => (string) ($sourceFile->id_secure ?? ''),
                    'name' => (string) $sourceFile->name,
                    'url' => route('portal.files.preview', $sourceFile),
                    'previewUrl' => route('portal.files.preview', $sourceFile),
                    'embedUrl' => route('portal.files.preview', $sourceFile),
                    'thumbnail' => route('portal.files.preview', $sourceFile),
                    'mimeType' => (string) $sourceFile->mime_type,
                    'mime_type' => (string) $sourceFile->mime_type,
                    'extension' => (string) $sourceFile->extension,
                    'isImage' => (bool) $sourceFile->is_image,
                    'is_image' => (bool) $sourceFile->is_image,
                ]
            );
        })->values()->all();

        if (! $changed) {
            return;
        }

        $postData['medias'] = $restoredItems;
        Arr::forget($postData, 'options.metadata.watermark_applied_at');
        Arr::forget($postData, 'options.metadata.watermark_rule_id');

        $post->forceFill(['data' => $postData])->save();
        $post->setAttribute('data', $postData);
    }

    protected function applyToMediaItem(array $item, User $owner, PublishingWatermark $watermark, PublishingPost $post): array
    {
        if ((bool) Arr::get($item, 'watermark_applied', false)) {
            return $item;
        }

        $fileId = (int) Arr::get($item, 'id', 0);

        if ($fileId <= 0) {
            return $item;
        }

        /** @var AppFile|null $sourceFile */
        $sourceFile = AppFile::query()->find($fileId);

        if (! $sourceFile || ! $sourceFile->is_image || ! $this->supportsSourceImage($sourceFile)) {
            return $item;
        }

        try {
            $binary = $this->renderWatermarkedBinary($sourceFile, $watermark);

            if ($binary === null) {
                return $item;
            }

            $generatedFile = $this->files->storeBinaryFile(
                $owner,
                $this->generatedFileName($sourceFile, $post),
                $this->normalizedOutputMimeType($sourceFile),
                $binary,
                null,
                'Generated watermark asset for publishing post #'.$post->id
            );

            if ((int) ($sourceFile->team_id ?: $post->team_id) > 0) {
                $generatedFile->forceFill([
                    'team_id' => (int) ($sourceFile->team_id ?: $post->team_id),
                ])->save();
            }

            return array_merge($item, [
                'id' => (int) $generatedFile->id,
                'idSecure' => (string) ($generatedFile->id_secure ?? ''),
                'name' => (string) $generatedFile->name,
                'url' => route('portal.files.preview', $generatedFile),
                'previewUrl' => route('portal.files.preview', $generatedFile),
                'embedUrl' => route('portal.files.preview', $generatedFile),
                'thumbnail' => route('portal.files.preview', $generatedFile),
                'mimeType' => (string) $generatedFile->mime_type,
                'mime_type' => (string) $generatedFile->mime_type,
                'extension' => (string) $generatedFile->extension,
                'isImage' => true,
                'is_image' => true,
                'watermark_applied' => true,
                'watermark_source_id' => (int) $sourceFile->id,
                'watermark_generated_file_id' => (int) $generatedFile->id,
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return $item;
        }
    }

    public function renderWatermarkedBinary(AppFile $sourceFile, PublishingWatermark $watermark): ?string
    {
        $sourceBinary = $this->readFileBinary($sourceFile);

        if ($sourceBinary === null) {
            return null;
        }

        $sourceImage = $this->createImageResource($sourceBinary, (string) $sourceFile->mime_type);

        if (! $sourceImage) {
            return null;
        }

        imagealphablending($sourceImage, true);
        imagesavealpha($sourceImage, true);

        if ($watermark->type === 'image') {
            $this->applyImageWatermark($sourceImage, $watermark);
        } else {
            $this->applyTextWatermark($sourceImage, $watermark);
        }

        $binary = $this->encodeImageResource($sourceImage, $this->normalizedOutputMimeType($sourceFile), (string) $sourceFile->mime_type);
        imagedestroy($sourceImage);

        return $binary;
    }

    protected function applyImageWatermark(\GdImage $sourceImage, PublishingWatermark $watermark): void
    {
        $watermarkFile = $watermark->file;

        if (! $watermarkFile instanceof AppFile) {
            return;
        }

        $watermarkBinary = $this->readFileBinary($watermarkFile);

        if ($watermarkBinary === null) {
            return;
        }

        $overlayImage = $this->createImageResource($watermarkBinary, (string) $watermarkFile->mime_type);

        if (! $overlayImage) {
            return;
        }

        imagealphablending($overlayImage, true);
        imagesavealpha($overlayImage, true);

        $sourceWidth = imagesx($sourceImage);
        $sourceHeight = imagesy($sourceImage);
        $overlayWidth = imagesx($overlayImage);
        $overlayHeight = imagesy($overlayImage);
        $targetWidth = max(24, (int) round($sourceWidth * $this->imageWidthRatio($watermark)));
        $targetHeight = max(24, (int) round($overlayHeight * ($targetWidth / max(1, $overlayWidth))));

        $resizedOverlay = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($resizedOverlay, false);
        imagesavealpha($resizedOverlay, true);
        $transparent = imagecolorallocatealpha($resizedOverlay, 0, 0, 0, 127);
        imagefill($resizedOverlay, 0, 0, $transparent);
        imagecopyresampled($resizedOverlay, $overlayImage, 0, 0, 0, 0, $targetWidth, $targetHeight, $overlayWidth, $overlayHeight);

        [$x, $y] = $this->positionCoordinates(
            $watermark->position ?: 'bottom-right',
            $sourceWidth,
            $sourceHeight,
            $targetWidth,
            $targetHeight
        );

        $opacityPercent = max(5, min(100, (int) ($watermark->opacity_percent ?: 72)));
        $this->copyOverlayWithOpacity($sourceImage, $resizedOverlay, $x, $y, $opacityPercent);

        imagedestroy($overlayImage);
        imagedestroy($resizedOverlay);
    }

    protected function applyTextWatermark(\GdImage $sourceImage, PublishingWatermark $watermark): void
    {
        $text = trim((string) $watermark->text);

        if ($text === '') {
            return;
        }

        $fontPath = $this->fontPathForWeight((string) data_get($watermark->metadata, 'text_weight', 'semibold'));

        if (! function_exists('imagettftext') || ! is_file($fontPath)) {
            return;
        }

        $sourceWidth = imagesx($sourceImage);
        $sourceHeight = imagesy($sourceImage);
        $fontSize = max(14, (int) round($this->previewTextFontSize($watermark) * $this->previewScaleFactor($sourceWidth)));
        $lines = $this->wrapTextLines($text, $fontPath, $fontSize, (int) round($sourceWidth * 0.55));

        if ($lines === []) {
            return;
        }

        $lineHeight = (int) round($fontSize * 1.25);
        $paddingX = max(12, (int) round($this->previewTextPaddingX($watermark) * $this->previewScaleFactor($sourceWidth)));
        $paddingY = max(10, (int) round($this->previewTextPaddingY($watermark) * $this->previewScaleFactor($sourceWidth)));
        $boxWidth = 0;

        foreach ($lines as $line) {
            $box = imagettfbbox($fontSize, 0, $fontPath, $line);
            $boxWidth = max($boxWidth, $box ? ((int) abs($box[2] - $box[0])) : 0);
        }

        $textBlockWidth = $boxWidth + ($paddingX * 2);
        $textBlockHeight = ($lineHeight * count($lines)) + ($paddingY * 2);
        [$x, $y] = $this->positionCoordinates(
            $watermark->position ?: 'bottom-right',
            $sourceWidth,
            $sourceHeight,
            $textBlockWidth,
            $textBlockHeight
        );

        $style = $this->textStyle((string) data_get($watermark->metadata, 'text_preset', 'glass'), (string) data_get($watermark->metadata, 'text_color', 'brand-gradient'));
        $opacityPercent = max(5, min(100, (int) ($watermark->opacity_percent ?: 72)));
        $opacityFactor = $opacityPercent / 100;

        if (($style['background'] ?? null) !== null) {
            [$r, $g, $b, $baseAlpha] = $style['background'];
            $alpha = (int) round(min(127, max(0, $baseAlpha + ((1 - $opacityFactor) * 70))));
            $background = imagecolorallocatealpha($sourceImage, $r, $g, $b, $alpha);
            imagefilledrectangle($sourceImage, $x, $y, $x + $textBlockWidth, $y + $textBlockHeight, $background);
        }

        if (($style['shadow'] ?? null) !== null) {
            [$r, $g, $b, $baseAlpha] = $style['shadow'];
            $shadow = imagecolorallocatealpha(
                $sourceImage,
                $r,
                $g,
                $b,
                (int) round(min(127, max(0, $baseAlpha + ((1 - $opacityFactor) * 50))))
            );

            foreach ($lines as $index => $line) {
                $baselineY = $y + $paddingY + $fontSize + ($index * $lineHeight);
                imagettftext($sourceImage, $fontSize, 0, $x + $paddingX + 2, $baselineY + 2, $shadow, $fontPath, $line);
            }
        }

        [$r, $g, $b, $baseAlpha] = $style['text'];
        $textColor = imagecolorallocatealpha(
            $sourceImage,
            $r,
            $g,
            $b,
            (int) round(min(127, max(0, $baseAlpha + ((1 - $opacityFactor) * 45))))
        );

        foreach ($lines as $index => $line) {
            $baselineY = $y + $paddingY + $fontSize + ($index * $lineHeight);
            imagettftext($sourceImage, $fontSize, 0, $x + $paddingX, $baselineY, $textColor, $fontPath, $line);
        }
    }

    protected function supportsSourceImage(AppFile $file): bool
    {
        return in_array(strtolower((string) $file->mime_type), self::SUPPORTED_SOURCE_MIME_TYPES, true);
    }

    protected function resolveWorkspaceOwner(PublishingPost $post): ?User
    {
        if ((int) $post->team_id > 0) {
            $team = Team::query()->find((int) $post->team_id);

            if ($team?->owner) {
                return $team->owner;
            }
        }

        return $post->owner;
    }

    protected function watermarkFeatureEnabled(User $owner): bool
    {
        return ! $owner->plan || $owner->canUsePlanFeature('watermark');
    }

    protected function resolveWatermark(User $owner, int $accountId, ?int $teamId = null): ?PublishingWatermark
    {
        $specific = PublishingWatermark::query()
            ->ownedBy((int) $owner->id)
            ->where(fn ($q) => $teamId ? $q->where('team_id',$teamId)->orWhereNull('team_id') : $q->whereNull('team_id'))
            ->orderByDesc('team_id')
            ->with('file')
            ->where('status', 'active')
            ->whereHas('accounts', fn ($query) => $query->where('social_accounts.id', $accountId))
            ->latest('id')
            ->first();

        if ($specific) {
            return $specific;
        }

        return PublishingWatermark::query()
            ->ownedBy((int) $owner->id)
            ->where(fn ($q) => $teamId ? $q->where('team_id',$teamId)->orWhereNull('team_id') : $q->whereNull('team_id'))
            ->orderByDesc('team_id')
            ->with('file')
            ->where('status', 'active')
            ->where('is_global', true)
            ->latest('id')
            ->first();
    }

    protected function generatedFileName(AppFile $sourceFile, PublishingPost $post): string
    {
        $baseName = pathinfo((string) $sourceFile->name, PATHINFO_FILENAME);
        $extension = $this->outputExtensionForMime($this->normalizedOutputMimeType($sourceFile));

        return Str::slug($baseName !== '' ? $baseName : 'publish-image').'-wm-post-'.$post->id.'.'.$extension;
    }

    protected function normalizedOutputMimeType(AppFile $sourceFile): string
    {
        $mimeType = strtolower((string) $sourceFile->mime_type);

        return match ($mimeType) {
            'image/jpg' => 'image/jpeg',
            default => $mimeType,
        };
    }

    protected function readFileBinary(AppFile $file): ?string
    {
        if (! filled($file->path) || ! Storage::disk((string) $file->disk)->exists((string) $file->path)) {
            return null;
        }

        return Storage::disk((string) $file->disk)->get((string) $file->path);
    }

    protected function createImageResource(string $binary, string $mimeType): \GdImage|false
    {
        $normalized = strtolower(trim($mimeType));

        return match ($normalized) {
            'image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif' => @imagecreatefromstring($binary),
            default => false,
        };
    }

    protected function encodeImageResource(\GdImage $image, string $outputMimeType, string $fallbackMimeType): ?string
    {
        ob_start();

        $encoded = match (strtolower($outputMimeType)) {
            'image/png' => imagepng($image, null, 6),
            'image/webp' => function_exists('imagewebp') ? imagewebp($image, null, 90) : imagepng($image, null, 6),
            'image/jpeg' => imagejpeg($image, null, 90),
            default => match (strtolower($fallbackMimeType)) {
                'image/png' => imagepng($image, null, 6),
                'image/webp' => function_exists('imagewebp') ? imagewebp($image, null, 90) : imagepng($image, null, 6),
                default => imagejpeg($image, null, 90),
            },
        };

        $binary = ob_get_clean();

        if (! $encoded || ! is_string($binary) || $binary === '') {
            return null;
        }

        return $binary;
    }

    protected function scaleRatio(PublishingWatermark $watermark): float
    {
        $scalePercent = (int) ($watermark->scale_percent ?: 24);

        return max(0.05, min(0.9, $scalePercent / 100));
    }

    protected function imageWidthRatio(PublishingWatermark $watermark): float
    {
        $scalePercent = max(5, min(90, (int) ($watermark->scale_percent ?: 24)));
        $previewWidthPx = max(64, $scalePercent * 0.22 * 16);

        return max(0.05, min(0.4, $previewWidthPx / self::PREVIEW_REFERENCE_WIDTH));
    }

    protected function previewScaleFactor(int $sourceWidth): float
    {
        return max(0.65, min(4, $sourceWidth / self::PREVIEW_REFERENCE_WIDTH));
    }

    protected function previewTextFontSize(PublishingWatermark $watermark): float
    {
        $scalePercent = max(5, min(90, (int) ($watermark->scale_percent ?: 24)));

        return max(14, round(12 + ($scalePercent * 0.42)));
    }

    protected function previewTextPaddingX(PublishingWatermark $watermark): float
    {
        $scalePercent = max(5, min(90, (int) ($watermark->scale_percent ?: 24)));
        $paddingRem = max(0.8, $scalePercent * 0.024);

        return $paddingRem * 16;
    }

    protected function previewTextPaddingY(PublishingWatermark $watermark): float
    {
        $scalePercent = max(5, min(90, (int) ($watermark->scale_percent ?: 24)));
        $paddingRem = max(0.45, $scalePercent * 0.012);

        return $paddingRem * 16;
    }

    protected function positionCoordinates(string $position, int $containerWidth, int $containerHeight, int $itemWidth, int $itemHeight): array
    {
        $padding = max(16, (int) round(min($containerWidth, $containerHeight) * 0.035));

        return match ($position) {
            'top-left' => [$padding, $padding],
            'top-right' => [$containerWidth - $itemWidth - $padding, $padding],
            'center' => [
                (int) round(($containerWidth - $itemWidth) / 2),
                (int) round(($containerHeight - $itemHeight) / 2),
            ],
            'bottom-left' => [$padding, $containerHeight - $itemHeight - $padding],
            default => [$containerWidth - $itemWidth - $padding, $containerHeight - $itemHeight - $padding],
        };
    }

    protected function copyOverlayWithOpacity(\GdImage $sourceImage, \GdImage $overlayImage, int $x, int $y, int $opacityPercent): void
    {
        $width = imagesx($overlayImage);
        $height = imagesy($overlayImage);
        $opacityRatio = max(0.05, min(1, $opacityPercent / 100));

        for ($px = 0; $px < $width; $px++) {
            for ($py = 0; $py < $height; $py++) {
                $rgba = imagecolorsforindex($overlayImage, imagecolorat($overlayImage, $px, $py));

                if (($rgba['alpha'] ?? 127) >= 127) {
                    continue;
                }

                $adjustedAlpha = 127 - (int) round((127 - $rgba['alpha']) * $opacityRatio);
                $color = imagecolorallocatealpha(
                    $sourceImage,
                    (int) $rgba['red'],
                    (int) $rgba['green'],
                    (int) $rgba['blue'],
                    max(0, min(127, $adjustedAlpha))
                );

                imagesetpixel($sourceImage, $x + $px, $y + $py, $color);
            }
        }
    }

    protected function wrapTextLines(string $text, string $fontPath, int $fontSize, int $maxWidth): array
    {
        $words = preg_split('/\s+/u', trim($text)) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = trim($current === '' ? $word : $current.' '.$word);
            $box = imagettfbbox($fontSize, 0, $fontPath, $candidate);
            $candidateWidth = $box ? (int) abs($box[2] - $box[0]) : 0;

            if ($current !== '' && $candidateWidth > $maxWidth) {
                $lines[] = $current;
                $current = $word;
                continue;
            }

            $current = $candidate;
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return collect($lines)
            ->map(fn ($line) => trim((string) $line))
            ->filter()
            ->take(4)
            ->values()
            ->all();
    }

    protected function textStyle(string $preset, string $color): array
    {
        $textColor = match ($color) {
            'dark' => [28, 33, 45, 10],
            'white' => [255, 255, 255, 5],
            'sunset-gradient' => [255, 231, 214, 6],
            'ocean-gradient' => [226, 245, 255, 6],
            default => [255, 255, 255, 5],
        };

        return match ($preset) {
            'solid-dark' => [
                'background' => [20, 24, 38, 36],
                'text' => $color === 'dark' ? [255, 255, 255, 5] : $textColor,
                'shadow' => [0, 0, 0, 85],
            ],
            'solid-light' => [
                'background' => [255, 255, 255, 24],
                'text' => $color === 'white' ? [20, 24, 38, 8] : $textColor,
                'shadow' => [15, 23, 42, 92],
            ],
            'minimal' => [
                'background' => null,
                'text' => $textColor,
                'shadow' => [15, 23, 42, 85],
            ],
            default => [
                'background' => [255, 255, 255, 58],
                'text' => $color === 'dark' ? [28, 33, 45, 10] : $textColor,
                'shadow' => [15, 23, 42, 92],
            ],
        };
    }

    protected function fontPathForWeight(string $weight): string
    {
        return match ($weight) {
            'bold', 'semibold' => base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf'),
            default => base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf'),
        };
    }

    protected function outputExtensionForMime(string $mimeType): string
    {
        return match (strtolower($mimeType)) {
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            default => 'png',
        };
    }
}
