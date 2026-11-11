@props([
    'label' => null,
    'description' => null,
    'error' => null,
    'value' => [],
    'wireModel' => null,
    'emptyLabel' => null,
    'mobileButtonLabel' => null,
    'mobileOpenEvent' => 'attached-media:open-mobile',
])

@php
    $basePath = rtrim((string) request()->getBaseUrl(), '/');
    $normalizeUrl = static function (?string $url) use ($basePath): ?string {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        if (\Illuminate\Support\Str::startsWith($url, ['http://', 'https://'])) {
            $path = parse_url($url, PHP_URL_PATH) ?: '';
            $query = parse_url($url, PHP_URL_QUERY);

            if ($path !== '') {
                $relative = $query ? $path.'?'.$query : $path;

                if ($basePath !== '' && str_starts_with($relative, '/') && ! str_starts_with($relative, $basePath.'/') && $relative !== $basePath) {
                    return $basePath.$relative;
                }

                return $relative;
            }
        }

        if ($basePath !== '' && str_starts_with($url, '/') && ! str_starts_with($url, $basePath.'/') && $url !== $basePath) {
            return $basePath.$url;
        }

        return $url;
    };

    $normalizedValue = collect(is_array($value) ? $value : [])
        ->map(fn ($item) => is_array($item) ? array_merge($item, [
            'url' => $normalizeUrl($item['url'] ?? null),
            'previewUrl' => $normalizeUrl($item['previewUrl'] ?? ($item['url'] ?? null)),
            'embedUrl' => $normalizeUrl($item['embedUrl'] ?? ($item['url'] ?? null)),
        ]) : [])
        ->values()
        ->all();
@endphp

<div
    data-no-loading
    x-data="{
        attachedMedia: @js($normalizedValue),
        attachedMediaRenderKey: 0,
        wireModel: @js($wireModel),
        mobileOpenEvent: @js($mobileOpenEvent),
        dragOver: false,
        imageLoadStates: {},
        isRenderableImage(mediaItem) {
            if (!mediaItem) return false;

            if (mediaItem.isImage === true) {
                return true;
            }

            const mime = String(mediaItem.mimeType || '').toLowerCase();
            const category = String(mediaItem.category || '').toLowerCase();
            const extension = String(mediaItem.extension || '').toLowerCase();
            const preview = String(mediaItem.previewUrl || mediaItem.url || '').toLowerCase();

            return mime.startsWith('image/')
                || category === 'image'
                || ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'svg'].includes(extension)
                || /\.(jpg|jpeg|png|webp|gif|bmp|svg)(\?.*)?$/.test(preview);
        },
        isRenderableVideo(mediaItem) {
            if (!mediaItem) return false;

            const mime = String(mediaItem.mimeType || '').toLowerCase();
            const category = String(mediaItem.category || '').toLowerCase();
            const extension = String(mediaItem.extension || '').toLowerCase();
            const preview = String(mediaItem.previewUrl || mediaItem.url || '').toLowerCase();

            return mime.startsWith('video/')
                || category === 'video'
                || ['mp4', 'mov', 'webm', 'm4v', 'avi', 'mkv'].includes(extension)
                || /\.(mp4|mov|webm|m4v|avi|mkv)(\?.*)?$/.test(preview);
        },
        mediaSource(mediaItem) {
            return mediaItem?.previewUrl || mediaItem?.url || '';
        },
        imageStateKey(mediaItem) {
            const mediaKey = String(mediaItem?.idSecure || mediaItem?.id || mediaItem?.previewUrl || mediaItem?.url || mediaItem?.name || '');

            return `attached:${mediaKey}`;
        },
        isImageLoaded(mediaItem) {
            if (!this.isRenderableImage(mediaItem)) {
                return true;
            }

            return this.imageLoadStates[this.imageStateKey(mediaItem)] === true;
        },
        syncImageLoadFromElement(mediaItem, element) {
            if (!this.isRenderableImage(mediaItem)) {
                return;
            }

            const key = this.imageStateKey(mediaItem);

            if (this.imageLoadStates[key] !== true) {
                this.imageLoadStates[key] = false;
            }

            if (element?.complete && element?.naturalWidth > 0) {
                this.imageLoadStates[key] = true;
            }
        },
        markImageLoaded(mediaItem) {
            this.imageLoadStates[this.imageStateKey(mediaItem)] = true;
        },
        markImageError(mediaItem) {
            this.imageLoadStates[this.imageStateKey(mediaItem)] = true;
        },
        syncAttachedMedia() {
            if (this.wireModel && this.$wire) {
                this.$wire.set(this.wireModel, this.attachedMedia, false);
            }

            window.dispatchEvent(new CustomEvent('media-browser:change', {
                detail: {
                    model: this.wireModel,
                    items: this.attachedMedia,
                },
            }));

            window.dispatchEvent(new CustomEvent('media-browser:set-selected', {
                detail: {
                    model: this.wireModel,
                    items: this.attachedMedia,
                },
            }));
        },
        addDroppedMedia(mediaItem) {
            if (!mediaItem || !mediaItem.id) {
                return;
            }

            if (this.attachedMedia.some((item) => String(item.id) === String(mediaItem.id))) {
                return;
            }

            this.setAttachedMedia([...this.attachedMedia, mediaItem]);
            this.syncAttachedMedia();
        },
        removeMedia(mediaItem) {
            if (!mediaItem) {
                return;
            }

            const targetId = String(mediaItem.id || '');

            this.setAttachedMedia(this.attachedMedia.filter((item) => String(item.id || '') !== targetId));
            this.syncAttachedMedia();
        },
        handleDrop(event) {
            this.dragOver = false;

            const payload = event?.dataTransfer?.getData('application/json')
                || event?.dataTransfer?.getData('text/plain');

            if (!payload) {
                return;
            }

            try {
                this.addDroppedMedia(JSON.parse(payload));
            } catch (error) {
            }
        },
        openMobilePicker() {
            window.dispatchEvent(new CustomEvent(this.mobileOpenEvent));
        },
        normalizeItems(items) {
            return (Array.isArray(items) ? items : [])
                .filter((item) => item && typeof item === 'object')
                .filter((item, index, collection) => {
                    const key = String(item.idSecure || item.id || item.name || '');

                    return key !== '' && collection.findIndex((candidate) => String(candidate.idSecure || candidate.id || candidate.name || '') === key) === index;
                });
        },
        setAttachedMedia(items) {
            this.attachedMedia = this.normalizeItems(items).map((item) => ({ ...item }));
            this.attachedMediaRenderKey += 1;
        }
    }"
    x-init="setAttachedMedia(attachedMedia)"
    x-on:media-browser:change.window="if ($event.detail.model === wireModel) setAttachedMedia($event.detail.items || [])"
    x-on:publishing-media-updated.window="if ($event.detail?.model === wireModel) setAttachedMedia($event.detail.items || [])"
    class="rounded-[1rem] border"
    style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-surface-base-rgb,255,255,255),0.96);"
>
    <div class="border-b px-4 py-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68);">
        <div class="flex items-center justify-between gap-3">
            <div class="min-w-0">
                @if ($label)
                    <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $label }}</p>
                @endif
                @if ($description)
                    <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ $description }}</p>
                @endif
            </div>
            <span class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em]" style="background-color: rgba(var(--theme-accent-rgb), 0.1); color: var(--theme-accent);" x-text="`${attachedMedia.length} {{ __('selected') }}`"></span>
        </div>
    </div>

    <div class="px-4 py-4">
        <div
            class="rounded-[0.95rem] border border-dashed transition"
            x-on:dragover.prevent="dragOver = true"
            x-on:dragleave="dragOver = false"
            x-on:drop.prevent="handleDrop($event)"
            x-bind:style="dragOver
                ? 'border-color: rgba(var(--theme-accent-rgb), 0.45); background-color: rgba(var(--theme-accent-rgb), 0.06);'
                : 'border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-border-color-rgb), 0.02);'"
            x-bind:class="attachedMedia.length ? 'px-3 py-3' : 'px-4 py-8 text-center'"
        >
            <template x-if="attachedMedia.length">
                <div class="flex flex-wrap gap-3">
                    <template x-for="mediaItem in attachedMedia" :key="`attached-${attachedMediaRenderKey}-${mediaItem.id || mediaItem.idSecure || mediaItem.name}`">
                        <div class="relative h-20 w-20 overflow-hidden rounded-[0.95rem] border" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-border-color-rgb), 0.04);">
                            <button
                                type="button"
                                class="absolute right-1 top-1 z-10 inline-flex h-5 w-5 items-center justify-center rounded-full border text-[10px] transition hover:scale-105"
                                style="border-color: rgba(var(--theme-surface-base-rgb,255,255,255),0.9); background-color: rgba(var(--theme-surface-base-rgb,255,255,255),0.94); color: var(--theme-header-text-color);"
                                x-on:click.stop="removeMedia(mediaItem)"
                                aria-label="{{ __('Remove media') }}"
                            >
                                <i class="fa-light fa-xmark"></i>
                            </button>
                            <template x-if="isRenderableImage(mediaItem) && mediaSource(mediaItem)">
                                <div class="relative h-full w-full">
                                    <div
                                        x-show="!isImageLoaded(mediaItem)"
                                        x-transition.opacity.duration.150ms
                                        class="absolute inset-0 flex items-center justify-center"
                                        style="background-color: rgba(var(--theme-border-color-rgb), 0.10); color: var(--theme-muted-text-color);"
                                    >
                                        <i class="fa-light fa-loader animate-spin text-sm"></i>
                                    </div>
                                    <img
                                        x-bind:src="mediaSource(mediaItem)"
                                        x-bind:alt="mediaItem.name || 'media'"
                                        x-init="syncImageLoadFromElement(mediaItem, $el)"
                                        x-on:load="markImageLoaded(mediaItem)"
                                        x-on:error="markImageError(mediaItem)"
                                        x-bind:class="isImageLoaded(mediaItem) ? 'opacity-100' : 'opacity-0'"
                                        class="h-full w-full object-cover transition-opacity duration-300"
                                        loading="lazy"
                                        decoding="async"
                                    >
                                </div>
                            </template>
                            <template x-if="!isRenderableImage(mediaItem) && isRenderableVideo(mediaItem) && mediaSource(mediaItem)">
                                <video x-bind:src="mediaSource(mediaItem)" preload="metadata" muted playsinline class="h-full w-full object-cover"></video>
                            </template>
                            <template x-if="(!isRenderableImage(mediaItem) && !isRenderableVideo(mediaItem)) || !mediaSource(mediaItem)">
                                <div class="flex h-full w-full items-center justify-center">
                                    <i class="fa-light fa-circle-play text-lg" style="color: var(--theme-muted-text-color);"></i>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </template>

            <template x-if="!attachedMedia.length">
                <p class="text-sm" style="color: var(--theme-muted-text-color);">{{ $emptyLabel ?: __('Drag media here or choose files from the media library.') }}</p>
            </template>
        </div>

        <div class="mt-4 xl:hidden">
            <x-ui.button type="button" variant="primary" class="w-full" x-on:click="openMobilePicker()">
                <i class="fa-light fa-photo-film"></i>
                {{ $mobileButtonLabel ?: __('Select media') }}
            </x-ui.button>
        </div>
    </div>

    @if ($error)
        <div class="border-t px-4 py-3" style="border-color: rgba(var(--theme-border-color-rgb), 0.68);">
            <p class="text-sm font-medium" style="color: var(--theme-danger-color);">{{ $error }}</p>
        </div>
    @endif
</div>
