@php($currentPostTarget = (string) data_get($composer, 'network_options.'.$providerKey.'.post_to', 'feed'))

<div class="space-y-4 px-4 py-4" x-data="{ postTo: @js($currentPostTarget) }">
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
</div>
