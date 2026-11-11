@component(theme_view('layouts.app', 'app'), ['title' => __('Blog Tags')])
    <div class="space-y-6">
        

        <x-ui.sub-header
            :eyebrow="__('Blogs')"
            :title="__('Blog Tags')"
            :description="__('Manage reusable labels for blog discovery and filtering.')"
            :count="$summary['total']"
        >
            <x-slot:actions>
                <x-ui.button :href="route('admin-blog-tags.create')" wire:navigate>{{ __('Create tag') }}</x-ui.button>
            </x-slot:actions>
        </x-ui.sub-header>

        <div class="grid gap-5 md:grid-cols-3">
            <x-ui.card class="space-y-2">
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">{{ __('Total') }}</p>
                <p class="text-2xl font-semibold tracking-[-0.04em]" style="color: var(--theme-header-text-color);">{{ number_format($summary['total']) }}</p>
                <p class="text-sm leading-6 text-slate-500">{{ __('All tags currently available for blogs.') }}</p>
            </x-ui.card>
            <x-ui.card class="space-y-2">
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">{{ __('Enabled') }}</p>
                <p class="text-2xl font-semibold tracking-[-0.04em] text-emerald-600">{{ number_format($summary['enabled']) }}</p>
                <p class="text-sm leading-6 text-slate-500">{{ __('Tags available for new posts.') }}</p>
            </x-ui.card>
            <x-ui.card class="space-y-2">
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">{{ __('Disabled') }}</p>
                <p class="text-2xl font-semibold tracking-[-0.04em] text-slate-600">{{ number_format($summary['disabled']) }}</p>
                <p class="text-sm leading-6 text-slate-500">{{ __('Tags hidden from selection.') }}</p>
            </x-ui.card>
        </div>

        <x-ui.card>
            <form method="GET" action="{{ route('admin-blog-tags.index') }}" class="grid gap-4 md:grid-cols-[minmax(0,1fr)_220px_auto]">
                <x-ui.input name="q" :label="__('Search')" :value="$filters['q']" :placeholder="__('Name or slug...')" />
                <x-ui.select name="status" :label="__('Status')">
                    <option value="all" @selected($filters['status'] === 'all')>{{ __('All') }}</option>
                    <option value="1" @selected((string) $filters['status'] === '1')>{{ __('Enabled') }}</option>
                    <option value="0" @selected((string) $filters['status'] === '0')>{{ __('Disabled') }}</option>
                </x-ui.select>
                <div class="flex items-end gap-3">
                    <x-ui.button type="submit">{{ __('Filter') }}</x-ui.button>
                    <x-ui.button :href="route('admin-blog-tags.index')" variant="outline" wire:navigate>{{ __('Reset') }}</x-ui.button>
                </div>
            </form>
        </x-ui.card>

        <x-ui.datatable-shell :info="__('Showing :from to :to of :total entries', ['from' => $tags->total() ? $tags->firstItem() : 0, 'to' => $tags->lastItem() ?? 0, 'total' => $tags->total()])">
            <x-ui.table>
                <x-ui.table-head>
                    <x-ui.table-cell head>{{ __('Tag') }}</x-ui.table-cell>
                    <x-ui.table-cell head>{{ __('Slug') }}</x-ui.table-cell>
                    <x-ui.table-cell head>{{ __('Posts') }}</x-ui.table-cell>
                    <x-ui.table-cell head>{{ __('Status') }}</x-ui.table-cell>
                    <x-ui.table-cell head>{{ __('Updated') }}</x-ui.table-cell>
                    <x-ui.table-cell head class="text-right">{{ __('Action') }}</x-ui.table-cell>
                </x-ui.table-head>
                <x-ui.table-body>
                    @forelse ($tags as $tag)
                        <x-ui.table-row>
                            <x-ui.table-cell>
                                <div class="flex items-start gap-3">
                                    <span class="mt-0.5 inline-flex h-10 w-10 items-center justify-center rounded-2xl text-sm font-semibold" style="background-color: color-mix(in srgb, {{ $tag->color ?: '#0f766e' }} 14%, transparent); color: {{ $tag->color ?: '#0f766e' }};">
                                        <i class="{{ $tag->icon ?: 'fa-light fa-hashtag' }}"></i>
                                    </span>
                                    <div>
                                        <p class="font-semibold" style="color: var(--theme-header-text-color);">{{ $tag->nameForLocale() }}</p>
                                    </div>
                                </div>
                            </x-ui.table-cell>
                            <x-ui.table-cell><span class="font-mono text-sm">{{ $tag->slug }}</span></x-ui.table-cell>
                            <x-ui.table-cell>{{ number_format($tag->blogs_count) }}</x-ui.table-cell>
                            <x-ui.table-cell><x-ui.badge :variant="$tag->statusVariant()">{{ $tag->statusLabel() }}</x-ui.badge></x-ui.table-cell>
                            <x-ui.table-cell>{{ $tag->createdAtFormatted() ?: __('N/A') }}</x-ui.table-cell>
                            <x-ui.table-cell class="text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <x-ui.button :href="route('admin-blog-tags.edit', $tag)" variant="outline" size="sm" wire:navigate>{{ __('Edit') }}</x-ui.button>
                                    <x-ui.confirm-action
                                        :action="route('admin-blog-tags.destroy', $tag)"
                                        :title="__('Delete this tag?')"
                                        :description="__('This permanently removes the selected blog tag.')"
                                        :submit-label="__('Delete')"
                                        size="sm"
                                    >
                                        <x-slot:trigger>
                                            <x-ui.button type="button" variant="danger" size="sm">{{ __('Delete') }}</x-ui.button>
                                        </x-slot:trigger>
                                    </x-ui.confirm-action>
                                </div>
                            </x-ui.table-cell>
                        </x-ui.table-row>
                    @empty
                        <x-ui.table-row>
                            <x-ui.table-cell colspan="6" class="py-10 text-center text-slate-500">{{ __('No blog tags found.') }}</x-ui.table-cell>
                        </x-ui.table-row>
                    @endforelse
                </x-ui.table-body>
            </x-ui.table>

            @if ($tags->hasPages())
                <x-slot:footer>
                    {{ $tags->links() }}
                </x-slot:footer>
            @endif
        </x-ui.datatable-shell>
    </div>
@endcomponent
