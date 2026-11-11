@component(theme_view('layouts.app', 'app'), ['title' => __('Blogs')])
    <div class="space-y-6">
        

        <x-ui.sub-header
            :eyebrow="__('Blogs')"
            :title="__('Blogs')"
            :description="__('Manage multilingual blog posts, publishing state, categories, and tags from one workspace.')"
            :count="$summary['total']"
        >
            <x-slot:actions>
                <x-ui.button :href="route('admin-blogs.create')" wire:navigate>{{ __('Create post') }}</x-ui.button>
            </x-slot:actions>
        </x-ui.sub-header>

        <div class="grid gap-5 md:grid-cols-3">
            <x-ui.card class="space-y-2">
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">{{ __('Total') }}</p>
                <p class="text-2xl font-semibold tracking-[-0.04em]" style="color: var(--theme-header-text-color);">{{ number_format($summary['total']) }}</p>
                <p class="text-sm leading-6 text-slate-500">{{ __('All blog posts currently stored.') }}</p>
            </x-ui.card>
            <x-ui.card class="space-y-2">
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">{{ __('Published') }}</p>
                <p class="text-2xl font-semibold tracking-[-0.04em] text-emerald-600">{{ number_format($summary['published']) }}</p>
                <p class="text-sm leading-6 text-slate-500">{{ __('Posts currently visible for publishing workflows.') }}</p>
            </x-ui.card>
            <x-ui.card class="space-y-2">
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">{{ __('Draft') }}</p>
                <p class="text-2xl font-semibold tracking-[-0.04em] text-slate-600">{{ number_format($summary['draft']) }}</p>
                <p class="text-sm leading-6 text-slate-500">{{ __('Posts not yet published.') }}</p>
            </x-ui.card>
        </div>

        <x-ui.card>
            <form method="GET" action="{{ route('admin-blogs.index') }}" class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_220px_260px_auto]">
                <x-ui.input name="q" :label="__('Search')" :value="$filters['q']" :placeholder="__('Title, slug, excerpt...')" />
                <x-ui.select name="status" :label="__('Status')">
                    <option value="all" @selected($filters['status'] === 'all')>{{ __('All') }}</option>
                    <option value="1" @selected((string) $filters['status'] === '1')>{{ __('Published') }}</option>
                    <option value="0" @selected((string) $filters['status'] === '0')>{{ __('Draft') }}</option>
                </x-ui.select>
                <x-ui.select name="category" :label="__('Category')">
                    <option value="all" @selected($filters['category'] === 'all')>{{ __('All categories') }}</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) $filters['category'] === (string) $category->id)>{{ $category->nameForLocale() }}</option>
                    @endforeach
                </x-ui.select>
                <div class="flex items-end gap-3">
                    <x-ui.button type="submit">{{ __('Filter') }}</x-ui.button>
                    <x-ui.button :href="route('admin-blogs.index')" variant="outline" wire:navigate>{{ __('Reset') }}</x-ui.button>
                </div>
            </form>
        </x-ui.card>

        <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($blogs as $blog)
                <x-ui.card class="space-y-4">
                    <div class="aspect-[16/9] overflow-hidden rounded-[1.2rem] border bg-slate-50" style="border-color: var(--theme-border-color);">
                        @if ($blog->thumbnailUrl())
                            <img src="{{ $blog->thumbnailUrl() }}" alt="{{ $blog->titleForLocale() }}" class="h-full w-full object-cover">
                        @else
                            <div class="flex h-full items-center justify-center text-sm font-medium text-slate-400">{{ __('No thumbnail') }}</div>
                        @endif
                    </div>

                    <div class="space-y-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate font-semibold" style="color: var(--theme-header-text-color);">{{ $blog->titleForLocale() }}</p>
                                <p class="mt-1 truncate text-xs font-mono" style="color: var(--theme-muted-text-color);">/{{ $blog->slug }}</p>
                            </div>
                            <x-ui.badge :variant="$blog->statusVariant()">{{ $blog->statusLabel() }}</x-ui.badge>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            @if ($blog->category)
                                <x-ui.badge>{{ $blog->category->nameForLocale() }}</x-ui.badge>
                            @endif
                            @foreach ($blog->tags->take(3) as $tag)
                                <x-ui.badge variant="primary">{{ $tag->nameForLocale() }}</x-ui.badge>
                            @endforeach
                            @if ($blog->tags->count() > 3)
                                <x-ui.badge variant="neutral">+{{ $blog->tags->count() - 3 }}</x-ui.badge>
                            @endif
                        </div>

                        <p class="text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ $blog->contentPreview() }}</p>
                    </div>

                    <div class="flex items-center justify-between gap-4 border-t pt-4" style="border-color: var(--theme-border-color);">
                        <div class="text-xs" style="color: var(--theme-muted-text-color);">
                            {{ $blog->publishedAtFormatted() ?: $blog->createdAtFormatted() ?: __('N/A') }}
                        </div>
                        <div class="flex items-center gap-2">
                            <x-ui.button :href="route('admin-blogs.edit', $blog)" variant="outline" size="sm" wire:navigate>{{ __('Edit') }}</x-ui.button>
                            <x-ui.confirm-action
                                :action="route('admin-blogs.destroy', $blog)"
                                :title="__('Delete this blog post?')"
                                :description="__('This permanently removes the selected blog post from the library.')"
                                :submit-label="__('Delete')"
                                size="sm"
                            >
                                <x-slot:trigger>
                                    <x-ui.button type="button" variant="danger" size="sm">{{ __('Delete') }}</x-ui.button>
                                </x-slot:trigger>
                            </x-ui.confirm-action>
                        </div>
                    </div>
                </x-ui.card>
            @empty
                <x-ui.card class="md:col-span-2 xl:col-span-3">
                    <x-ui.empty
                        class="py-12"
                        icon="fa-light fa-newspaper"
                        :title="__('No blog posts found.')"
                        :description="__('Try broadening your filters or create your first blog post.')"
                    />
                </x-ui.card>
            @endforelse
        </div>

        @if ($blogs->hasPages())
            <div class="rounded-[0.85rem] border px-6 py-4" style="border-color: var(--theme-border-color); background-color: var(--theme-surface-base);">
                {{ $blogs->links() }}
            </div>
        @endif
    </div>
@endcomponent
