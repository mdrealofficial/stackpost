@php
    $currentPostTarget = (string) data_get($composer, 'network_options.'.$providerKey.'.post_to', 'feed');
    $currentCoverUrl = (string) (
        data_get($composer, 'network_options.'.$providerKey.'.cover_url', '')
        ?: data_get($composer, 'network_options.'.$providerKey.'.cover_items.0.previewUrl', '')
        ?: data_get($composer, 'network_options.'.$providerKey.'.cover_items.0.url', '')
    );
    $coverPickerName = 'instagram_reel_cover_'.$providerKey;
@endphp

<div
    class="space-y-4 px-4 py-4"
    x-data="{ postTo: @js($currentPostTarget), coverUrl: @js($currentCoverUrl) }"
    x-on:image-picker:change.window="
        if (($event.detail?.name || '') === @js($coverPickerName)) {
            coverUrl = String($event.detail?.value || '');
            $wire.set('composer.network_options.{{ $providerKey }}.cover_url', coverUrl, false);
        }
    "
>
    <div class="space-y-2.5">
        <x-ui.label>{{ __('Post To') }}</x-ui.label>
        <div class="flex flex-wrap gap-2">
            @foreach (($composerNetworkConfig['post_to_options'] ?? []) as $option)
                <button
                    type="button"
                    data-no-loading
                    x-on:click="
                        postTo = '{{ $option['key'] }}';
                        $wire.set('composer.network_options.{{ $providerKey }}.post_to', '{{ $option['key'] }}', false);
                    "
                    class="inline-flex cursor-pointer items-center gap-2 rounded-full border px-3.5 py-2 text-sm font-semibold transition"
                    x-bind:style="postTo === '{{ $option['key'] }}'
                        ? 'border-color: rgba(var(--theme-accent-rgb), 0.28); background-color: rgba(var(--theme-accent-rgb), 0.1); color: var(--theme-header-text-color);'
                        : 'border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: color-mix(in srgb, var(--theme-surface-base) 82%, transparent); color: var(--theme-muted-text-color);'"
                >
                    <span>{{ $option['label'] }}</span>
                </button>
            @endforeach
        </div>
    </div>

    <div x-show="postTo === 'reels'" x-cloak class="space-y-3">
        <x-ui.image-picker
            :name="$coverPickerName"
            context="portal"
            value-field="url"
            :value="$currentCoverUrl"
            :preview="$currentCoverUrl"
            :label="__('Reel cover image')"
            :button-label="__('Choose cover')"
            :dialog-title="__('Choose Reel cover')"
            :dialog-description="__('Select an image from Files to use as the Instagram Reel cover.')"
            layout="compact"
        />
        <p class="text-xs leading-5" style="color: var(--theme-muted-text-color);">
            {{ __('Optional. Instagram requires a publicly reachable image URL for custom Reel covers.') }}
        </p>
    </div>
</div>
