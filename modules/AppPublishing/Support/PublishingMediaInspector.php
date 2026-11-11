<?php

namespace Modules\AppPublishing\Support;

use Illuminate\Support\Collection;

class PublishingMediaInspector
{
    public static function analyze(array $mediaItems): array
    {
        $items = collect($mediaItems)
            ->filter(fn ($item) => is_array($item))
            ->values();

        $hasVideo = $items->contains(function (array $item): bool {
            $mimeType = strtolower((string) data_get($item, 'mimeType', ''));
            $category = strtolower((string) data_get($item, 'category', ''));

            return str_starts_with($mimeType, 'video/') || $category === 'video';
        });

        $hasImage = $items->contains(fn (array $item): bool => (bool) data_get($item, 'isImage', false));

        return [
            'items' => $items,
            'count' => $items->count(),
            'has_media' => $items->isNotEmpty(),
            'has_video' => $hasVideo,
            'has_image' => $hasImage,
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public static function items(array $mediaItems): Collection
    {
        /** @var \Illuminate\Support\Collection<int, array<string, mixed>> $items */
        $items = static::analyze($mediaItems)['items'];

        return $items;
    }
}
