    <div class="space-y-6">
        
        @if ($statusMessage)
            <x-ui.alert :title="__('Saved')" :description="$statusMessage" variant="success" dismissible />
        @endif

        <x-ui.page-hero
            :eyebrow="__('Blogs')"
            :title="__('Blogs')"
            :description="__('Manage multilingual blog posts, publishing state, categories, and tags from one workspace.')"
            :count="$summary['total']"
            icon="fa-light fa-newspaper"
        >
            <x-slot:actions>
                <x-ui.button :href="route('admin-blogs.rss.index')" variant="outline" wire:navigate>{{ __('RSS Sources') }}</x-ui.button>
                <x-ui.button :href="route('admin-blogs.create')" wire:navigate>{{ __('Create post') }}</x-ui.button>
            </x-slot:actions>
        </x-ui.page-hero>

        @php
            $blogMetricCards = [
                [
                    'label' => __('Total'),
                    'value' => number_format($summary['total']),
                    'description' => __('All blog posts currently stored.'),
                    'tone' => 'var(--theme-accent)',
                    'progress' => 100,
                ],
                [
                    'label' => __('Published'),
                    'value' => number_format($summary['published']),
                    'description' => __('Posts currently visible for publishing workflows.'),
                    'tone' => '#10b981',
                    'progress' => max(8, $summary['total'] > 0 ? round(($summary['published'] / $summary['total']) * 100) : 8),
                ],
                [
                    'label' => __('Draft'),
                    'value' => number_format($summary['draft']),
                    'description' => __('Posts not yet published.'),
                    'tone' => '#64748b',
                    'progress' => max(8, $summary['total'] > 0 ? round(($summary['draft'] / $summary['total']) * 100) : 8),
                ],
            ];
        @endphp

        <x-ui.metric-strip :items="$blogMetricCards" :show-icons="false" />

        <x-ui.filter-panel fields-class="xl:grid-cols-[minmax(0,1fr)_220px_260px_auto]">
            <x-slot:fields>
                <x-ui.input wire:model.live.debounce.300ms="search" :label="__('Search')" :placeholder="__('Title, slug, excerpt...')" />
                <x-ui.select wire:model.live="statusFilter" :label="__('Status')">
                    <option value="all">{{ __('All') }}</option>
                    <option value="1">{{ __('Published') }}</option>
                    <option value="0">{{ __('Draft') }}</option>
                </x-ui.select>
                <x-ui.select wire:model.live="categoryFilter" :label="__('Category')">
                    <option value="all">{{ __('All categories') }}</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->nameForLocale() }}</option>
                    @endforeach
                </x-ui.select>
            </x-slot:fields>

            <x-slot:actions>
                <x-ui.button type="button" wire:click="$refresh">{{ __('Filter') }}</x-ui.button>
                <x-ui.button type="button" variant="outline" wire:click="resetFilters">{{ __('Reset') }}</x-ui.button>
            </x-slot:actions>
        </x-ui.filter-panel>

        <x-ui.bulk-toolbar compact>
            <x-slot:chips>
                @if ($search !== '')
                    <span class="inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-medium" style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color);">{{ $search }}</span>
                @endif
                <span class="inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-medium" style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color);">
                    {{ __('Status') }}: {{ $statusFilter === 'all' ? __('All') : ($statusFilter === '1' ? __('Published') : __('Draft')) }}
                </span>
                <span class="inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-medium" style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color);">
                    {{ __('Category') }}: {{ $categoryFilter === 'all' ? __('All categories') : ($categories->firstWhere('id', (int) $categoryFilter)?->nameForLocale() ?? __('Unknown')) }}
                </span>
            </x-slot:chips>

            <x-slot:selection>
                <div class="flex min-w-0 items-center gap-3">
                    <x-ui.checkbox
                        wire:model.live="selectPage"
                        :label="__('Check all')"
                        minimal
                    />

                    @if (count($selectedBlogIds) > 0)
                        <span class="text-sm font-medium" style="color: var(--theme-header-text-color);">
                            {{ trans_choice('{1} :count selected|[2,*] :count selected', count($selectedBlogIds), ['count' => count($selectedBlogIds)]) }}
                        </span>
                    @else
                        <span class="text-sm" style="color: var(--theme-muted-text-color);">{{ __('No posts selected') }}</span>
                    @endif
                </div>

                @if (count($selectedBlogIds) > 0)
                    <x-ui.dropdown-menu align="right" width="auto">
                        <x-slot:trigger>
                            <x-ui.button type="button" variant="outline" size="sm">
                                <i class="fa-light fa-bolt"></i>
                                {{ __('Actions') }}
                                <i class="fa-light fa-chevron-down text-xs"></i>
                            </x-ui.button>
                        </x-slot:trigger>

                        <x-ui.dropdown-menu-item icon="fa-light fa-circle-check" wire:click="publishSelected">
                            {{ __('Publish selected') }}
                        </x-ui.dropdown-menu-item>
                        <x-ui.dropdown-menu-item icon="fa-light fa-eye-slash" wire:click="draftSelected">
                            {{ __('Move to draft') }}
                        </x-ui.dropdown-menu-item>
                    </x-ui.dropdown-menu>

                    <x-ui.dialog :title="__('Delete selected blog posts?')" :description="__('This permanently removes all selected blog posts from the library.')" width="sm" dismissible>
                        <x-slot:trigger>
                            <x-ui.button type="button" variant="danger" size="sm">
                                <i class="fa-light fa-trash-can"></i>
                            </x-ui.button>
                        </x-slot:trigger>
                        <x-slot:footer>
                            <div class="flex justify-end gap-3">
                                <x-ui.button type="button" variant="outline" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                                <x-ui.button type="button" variant="danger" wire:click="deleteSelected" x-on:click="open = false">{{ __('Delete') }}</x-ui.button>
                            </div>
                        </x-slot:footer>
                    </x-ui.dialog>
                @endif
            </x-slot:selection>
        </x-ui.bulk-toolbar>

        <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($blogs as $blog)
<x-ui.card class="relative z-0 space-y-4 overflow-visible" data-blog-card>
                    <div class="flex items-center justify-between">
                        <x-ui.checkbox
                            value="{{ $blog->id }}"
                            wire:model.live="selectedBlogIds"
                            minimal
                        />
                    </div>

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

                    <div class="flex flex-col gap-3 border-t pt-4 sm:flex-row sm:items-center sm:justify-between sm:gap-4" style="border-color: var(--theme-border-color);">
                        <div class="text-xs leading-5 sm:leading-normal" style="color: var(--theme-muted-text-color);">
                            {{ $blog->publishedAtFormatted() ?: $blog->createdAtFormatted() ?: __('N/A') }}
                        </div>
                        <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                            <x-ui.button :href="route('admin-blogs.preview', $blog)" variant="outline" size="sm">{{ __('Preview') }}</x-ui.button>
                            <details class="relative ml-auto z-[60]" ontoggle="const card=this.closest('[data-blog-card]'); if(card){card.style.zIndex=this.open?'80':'0';}">
                                <summary class="flex h-10 w-10 cursor-pointer list-none items-center justify-center rounded-[0.8rem] border bg-white transition hover:opacity-80 [&::-webkit-details-marker]:hidden" style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color);">
                                    <i class="fa-light fa-ellipsis text-sm"></i>
                                </summary>
                                <div class="absolute right-0 top-full z-[70] mt-2 flex min-w-[10rem] flex-col overflow-hidden rounded-[0.9rem] border shadow-[0_18px_38px_-24px_rgba(15,23,42,0.28)]" style="border-color: var(--theme-border-color); background: var(--theme-surface-base);">
                                    <form method="POST" action="{{ route('admin-blogs.duplicate', $blog) }}">
                                        @csrf
                                        <button type="submit" class="inline-flex w-full items-center gap-2 px-3 py-2.5 text-sm font-medium transition hover:bg-[rgba(var(--theme-accent-rgb),0.06)]" style="color: var(--theme-header-text-color);">
                                            <i class="fa-light fa-copy text-xs"></i>
                                            {{ __('Duplicate') }}
                                        </button>
                                    </form>
                                    <a href="{{ route('admin-blogs.edit', $blog) }}" wire:navigate class="inline-flex items-center gap-2 px-3 py-2.5 text-sm font-medium transition hover:bg-[rgba(var(--theme-accent-rgb),0.06)]" style="color: var(--theme-header-text-color);">
                                        <i class="fa-light fa-pen text-xs"></i>
                                        {{ __('Edit') }}
                                    </a>
                                    <x-ui.dialog :title="__('Delete this blog post?')" :description="__('This permanently removes the selected blog post from the library.')" width="sm" dismissible>
                                        <x-slot:trigger>
                                            <button type="button" class="inline-flex w-full items-center gap-2 px-3 py-2.5 text-sm font-medium transition hover:bg-rose-50" style="color: #e11d48;">
                                                <i class="fa-light fa-trash text-xs"></i>
                                                {{ __('Delete') }}
                                            </button>
                                        </x-slot:trigger>
                                        <x-slot:footer>
                                            <div class="flex justify-end gap-3">
                                                <x-ui.button type="button" variant="outline" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                                                <x-ui.button type="button" variant="danger" wire:click="delete({{ $blog->id }})" x-on:click="open = false">{{ __('Delete') }}</x-ui.button>
                                            </div>
                                        </x-slot:footer>
                                    </x-ui.dialog>
                                </div>
                            </details>
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
