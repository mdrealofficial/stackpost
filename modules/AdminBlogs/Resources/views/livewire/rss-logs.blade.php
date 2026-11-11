<div class="space-y-6">
    @if ($statusMessage)
        <x-ui.alert :title="__('Saved')" :description="$statusMessage" variant="success" dismissible />
    @endif

    <x-ui.page-hero
        :eyebrow="__('Blogs')"
        :title="__('RSS Import Logs')"
        :description="__('Review imported RSS items, jump to the created blog post, or open the original source URL.')"
        :count="$recentImports->total()"
        icon="fa-light fa-clock-rotate-left"
    >
        <x-slot:actions>
            <x-ui.button :href="route('admin-blogs.rss.index')" variant="outline" wire:navigate>
                {{ __('Back to RSS sources') }}
            </x-ui.button>

            @if ($recentImports->total() > 0)
                <x-ui.dialog :title="__('Empty import logs?')" :description="__('This removes the RSS import history shown here. Imported blog posts and RSS sources will remain.')" width="sm" dismissible>
                    <x-slot:trigger>
                        <x-ui.button type="button" variant="outline">
                            {{ __('Empty logs') }}
                        </x-ui.button>
                    </x-slot:trigger>
                    <x-slot:footer>
                        <div class="flex justify-end gap-3">
                            <x-ui.button type="button" variant="outline" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                            <x-ui.button type="button" variant="danger" wire:click="clearImportLogs" x-on:click="open = false">
                                {{ __('Empty logs') }}
                            </x-ui.button>
                        </div>
                    </x-slot:footer>
                </x-ui.dialog>
            @endif
        </x-slot:actions>
    </x-ui.page-hero>

    <x-ui.card class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Recent import logs') }}</p>
                <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Track the latest imported RSS items and jump back to the source or created post.') }}</p>
            </div>
            <span class="inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-medium" style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color);">
                {{ __('Showing :count logs', ['count' => $recentImports->total()]) }}
            </span>
        </div>

        @if ($recentImports->count() > 0)
            <div class="overflow-hidden rounded-[1.15rem] border" style="border-color: var(--theme-border-color);">
                <div class="grid gap-0 divide-y" style="divide-color: var(--theme-border-color);">
                    @foreach ($recentImports as $import)
                        <div class="grid gap-3 px-5 py-4 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)_auto] lg:items-center">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $import->title }}</p>
                                <div class="mt-1 flex flex-wrap items-center gap-2 text-xs" style="color: var(--theme-muted-text-color);">
                                    <span>{{ $import->created ? date('Y-m-d H:i', (int) $import->created) : __('Unknown') }}</span>
                                    @if ($import->source)
                                        <span>&bull;</span>
                                        <span class="truncate">{{ $import->source->name }}</span>
                                    @endif
                                </div>
                            </div>

                            <div class="min-w-0 text-sm" style="color: var(--theme-muted-text-color);">
                                @if ($import->blog)
                                    <span class="block truncate font-medium" style="color: var(--theme-header-text-color);">{{ $import->blog->title }}</span>
                                    <span class="block truncate font-mono text-xs">/{{ $import->blog->slug }}</span>
                                @elseif ($import->external_url)
                                    <span class="block truncate">{{ $import->external_url }}</span>
                                @else
                                    <span class="block">{{ __('No destination') }}</span>
                                @endif
                            </div>

                            <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                                @if ($import->blog)
                                    <x-ui.button :href="route('admin-blogs.preview', $import->blog)" variant="outline" size="sm">{{ __('Preview') }}</x-ui.button>
                                    <x-ui.button :href="route('admin-blogs.edit', $import->blog)" variant="outline" size="sm" wire:navigate>{{ __('Edit') }}</x-ui.button>
                                @endif
                                @if ($import->external_url)
                                    <x-ui.button :href="$import->external_url" variant="outline" size="sm" target="_blank" rel="noopener">
                                        {{ __('Source') }}
                                    </x-ui.button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            @if ($recentImports->hasPages())
                <div class="rounded-[0.85rem] border px-6 py-4" style="border-color: var(--theme-border-color); background-color: var(--theme-surface-base);">
                    {{ $recentImports->links() }}
                </div>
            @endif
        @else
            <x-ui.empty
                class="py-10"
                icon="fa-light fa-clock-rotate-left"
                :title="__('No import logs yet.')"
                :description="__('Run a source once and the latest imported RSS items will appear here.')"
            />
        @endif
    </x-ui.card>
</div>
