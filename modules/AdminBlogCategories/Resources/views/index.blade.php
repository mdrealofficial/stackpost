@component(theme_view('layouts.app', 'app'), ['title' => __('Blog Categories')])
    <div class="space-y-6">
        

        <x-ui.sub-header
            :eyebrow="__('Blogs')"
            :title="__('Blog Categories')"
            :description="__('Organize posts into reusable groups and manage localized labels from one place.')"
            :count="$summary['total']"
        >
            <x-slot:actions>
                <x-ui.button :href="route('admin-blog-categories.create')" wire:navigate>{{ __('Create category') }}</x-ui.button>
            </x-slot:actions>
        </x-ui.sub-header>

        <div class="grid gap-5 md:grid-cols-3">
            <x-ui.card class="space-y-2">
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">{{ __('Total') }}</p>
                <p class="text-2xl font-semibold tracking-[-0.04em]" style="color: var(--theme-header-text-color);">{{ number_format($summary['total']) }}</p>
                <p class="text-sm leading-6 text-slate-500">{{ __('All blog categories currently available.') }}</p>
            </x-ui.card>
            <x-ui.card class="space-y-2">
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">{{ __('Enabled') }}</p>
                <p class="text-2xl font-semibold tracking-[-0.04em] text-emerald-600">{{ number_format($summary['enabled']) }}</p>
                <p class="text-sm leading-6 text-slate-500">{{ __('Categories visible for publishing workflows.') }}</p>
            </x-ui.card>
            <x-ui.card class="space-y-2">
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">{{ __('Disabled') }}</p>
                <p class="text-2xl font-semibold tracking-[-0.04em] text-slate-600">{{ number_format($summary['disabled']) }}</p>
                <p class="text-sm leading-6 text-slate-500">{{ __('Categories hidden from new content selection.') }}</p>
            </x-ui.card>
        </div>

        <x-ui.card>
            <form method="GET" action="{{ route('admin-blog-categories.index') }}" class="grid gap-4 md:grid-cols-[minmax(0,1fr)_220px_auto]">
                <x-ui.input name="q" :label="__('Search')" :value="$filters['q']" :placeholder="__('Name, slug, description...')" />
                <x-ui.select name="status" :label="__('Status')">
                    <option value="all" @selected($filters['status'] === 'all')>{{ __('All') }}</option>
                    <option value="1" @selected((string) $filters['status'] === '1')>{{ __('Enabled') }}</option>
                    <option value="0" @selected((string) $filters['status'] === '0')>{{ __('Disabled') }}</option>
                </x-ui.select>
                <div class="flex items-end gap-3">
                    <x-ui.button type="submit">{{ __('Filter') }}</x-ui.button>
                    <x-ui.button :href="route('admin-blog-categories.index')" variant="outline" wire:navigate>{{ __('Reset') }}</x-ui.button>
                </div>
            </form>
        </x-ui.card>

        <x-ui.datatable-shell :info="__('Showing :from to :to of :total entries', ['from' => $categories->total() ? $categories->firstItem() : 0, 'to' => $categories->lastItem() ?? 0, 'total' => $categories->total()])">
            <x-ui.table>
                <x-ui.table-head>
                    <x-ui.table-cell head>{{ __('Category') }}</x-ui.table-cell>
                    <x-ui.table-cell head>{{ __('Slug') }}</x-ui.table-cell>
                    <x-ui.table-cell head>{{ __('Posts') }}</x-ui.table-cell>
                    <x-ui.table-cell head>{{ __('Status') }}</x-ui.table-cell>
                    <x-ui.table-cell head>{{ __('Updated') }}</x-ui.table-cell>
                    <x-ui.table-cell head class="text-right">{{ __('Action') }}</x-ui.table-cell>
                </x-ui.table-head>
                <x-ui.table-body>
                    @forelse ($categories as $category)
                        <x-ui.table-row>
                            <x-ui.table-cell>
                                <div class="flex items-start gap-3">
                                    <span class="mt-0.5 inline-flex h-10 w-10 items-center justify-center rounded-2xl text-sm font-semibold" style="background-color: color-mix(in srgb, {{ $category->color ?: '#0f766e' }} 14%, transparent); color: {{ $category->color ?: '#0f766e' }};">
                                        <i class="{{ $category->icon ?: 'fa-light fa-folder-tree' }}"></i>
                                    </span>
                                    <div>
                                        <p class="font-semibold" style="color: var(--theme-header-text-color);">{{ $category->nameForLocale() }}</p>
                                        <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ $category->descriptionForLocale() ?: __('No description') }}</p>
                                    </div>
                                </div>
                            </x-ui.table-cell>
                            <x-ui.table-cell><span class="font-mono text-sm">{{ $category->slug }}</span></x-ui.table-cell>
                            <x-ui.table-cell>{{ number_format($category->blogs_count) }}</x-ui.table-cell>
                            <x-ui.table-cell><x-ui.badge :variant="$category->statusVariant()">{{ $category->statusLabel() }}</x-ui.badge></x-ui.table-cell>
                            <x-ui.table-cell>{{ $category->createdAtFormatted() ?: __('N/A') }}</x-ui.table-cell>
                            <x-ui.table-cell class="text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <x-ui.button :href="route('admin-blog-categories.edit', $category)" variant="outline" size="sm" wire:navigate>{{ __('Edit') }}</x-ui.button>
                                    <x-ui.confirm-action
                                        :action="route('admin-blog-categories.destroy', $category)"
                                        :title="__('Delete this category?')"
                                        :description="__('This permanently removes the selected blog category.')"
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
                            <x-ui.table-cell colspan="6" class="py-10 text-center text-slate-500">{{ __('No blog categories found.') }}</x-ui.table-cell>
                        </x-ui.table-row>
                    @endforelse
                </x-ui.table-body>
            </x-ui.table>

            @if ($categories->hasPages())
                <x-slot:footer>
                    {{ $categories->links() }}
                </x-slot:footer>
            @endif
        </x-ui.datatable-shell>
    </div>
@endcomponent
