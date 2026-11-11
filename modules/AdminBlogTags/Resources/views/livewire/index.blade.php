<div class="space-y-6">
    @php
        $surfaceSoftStyle = 'border-color: var(--theme-border-color); background-color: color-mix(in srgb, var(--theme-surface-soft) 76%, transparent);';
    @endphp

    @if ($statusMessage)
        <x-ui.alert variant="success" :title="__('Saved')" :description="$statusMessage" />
    @endif

    <x-ui.page-hero
        :eyebrow="__('Blogs')"
        :title="__('Blog Tags')"
        :description="__('Manage reusable labels for blog discovery, taxonomy, and localized metadata.')"
        :count="$summary['total']"
        icon="fa-light fa-tags"
    >
        <x-slot:actions>
            <x-ui.button :href="route('admin-blog-tags.create')" wire:navigate>
                <i class="fa-light fa-plus"></i>
                {{ __('Create tag') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-hero>

    @php
        $enabledPercent = max(8, $summary['total'] > 0 ? round(($summary['enabled'] / $summary['total']) * 100) : 8);
        $disabledPercent = max(8, $summary['total'] > 0 ? round(($summary['disabled'] / $summary['total']) * 100) : 8);
        $tagStatCards = [
            [
                'label' => __('Total'),
                'value' => number_format($summary['total']),
                'suffix' => __('tags'),
                'description' => __('All tags currently available for blogs.'),
                'icon' => 'fa-layer-group',
                'tone' => 'var(--theme-accent)',
                'progress' => 100,
            ],
            [
                'label' => __('Enabled'),
                'value' => number_format($summary['enabled']),
                'suffix' => __('live'),
                'description' => __('Tags available for new posts and filters.'),
                'icon' => 'fa-circle-check',
                'tone' => '#10b981',
                'progress' => $enabledPercent,
            ],
            [
                'label' => __('Disabled'),
                'value' => number_format($summary['disabled']),
                'suffix' => __('hidden'),
                'description' => __('Tags hidden from selection and publishing flows.'),
                'icon' => 'fa-eye-slash',
                'tone' => '#64748b',
                'progress' => $disabledPercent,
            ],
        ];
    @endphp

    <x-ui.metric-strip :items="$tagStatCards" />

    <div class="space-y-5">
        <x-ui.filter-panel fields-class="lg:grid-cols-[minmax(0,1.5fr)_180px_180px_180px_160px_auto]">
            <x-slot:fields>
                <x-ui.input wire:model.live.debounce.300ms="search" :label="__('Search')" :placeholder="__('Name or slug...')" />
                <x-ui.select wire:model.live="statusFilter" :label="__('Status')">
                    <option value="all">{{ __('All') }}</option>
                    <option value="1">{{ __('Enabled') }}</option>
                    <option value="0">{{ __('Disabled') }}</option>
                </x-ui.select>
                <x-ui.select wire:model.live="dateFilter" :label="__('Range')">
                    @foreach ($filterOptions['dateRanges'] as $option)
                        <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.select wire:model.live="sortFilter" :label="__('Sort')">
                    @foreach ($filterOptions['sorts'] as $option)
                        <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.select wire:model.live="perPage" :label="__('Show')">
                    @foreach ($filterOptions['perPage'] as $option)
                        <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                    @endforeach
                </x-ui.select>
            </x-slot:fields>

            <x-slot:actions>
                <x-ui.button type="button" wire:click="$refresh"><i class="fa-light fa-filter"></i></x-ui.button>
                <x-ui.button type="button" variant="outline" wire:click="resetFilters"><i class="fa-light fa-rotate-right"></i></x-ui.button>
            </x-slot:actions>
        </x-ui.filter-panel>

        <x-ui.bulk-toolbar compact>
            <x-slot:chips>
                @if ($search !== '')
                    <span class="inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-medium" style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color);">{{ $search }}</span>
                @endif
                <span class="inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-medium" style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color);">{{ __('Rows') }}: {{ $perPage }}</span>
                <span class="inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-medium" style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color);">{{ __('Sort') }}: {{ collect($filterOptions['sorts'])->firstWhere('value', $sortFilter)['label'] ?? $sortFilter }}</span>
                <x-ui.button type="button" variant="{{ $statusFilter === '1' ? 'primary' : 'outline' }}" size="sm" wire:click="$set('statusFilter', '1')">{{ __('Enabled') }}</x-ui.button>
                <x-ui.button type="button" variant="{{ $statusFilter === '0' ? 'primary' : 'outline' }}" size="sm" wire:click="$set('statusFilter', '0')">{{ __('Disabled') }}</x-ui.button>
                <x-ui.button type="button" variant="{{ $sortFilter === 'posts_desc' ? 'primary' : 'outline' }}" size="sm" wire:click="$set('sortFilter', 'posts_desc')">{{ __('Most posts') }}</x-ui.button>
            </x-slot:chips>

            <x-slot:selection>
                <div class="flex min-w-0 items-center gap-3">
                    <x-ui.checkbox
                        wire:model.live="selectPage"
                        :label="__('Check all')"
                        minimal
                    />

                    @if (count($selectedTagIds) > 0)
                        <span class="text-sm font-medium" style="color: var(--theme-header-text-color);">
                            {{ trans_choice('{1} :count selected|[2,*] :count selected', count($selectedTagIds), ['count' => count($selectedTagIds)]) }}
                        </span>
                    @else
                        <span class="text-sm" style="color: var(--theme-muted-text-color);">{{ __('No tags selected') }}</span>
                    @endif
                </div>

                @if (count($selectedTagIds) > 0)
                    <x-ui.dropdown-menu align="right" width="auto">
                        <x-slot:trigger>
                            <x-ui.button type="button" variant="outline" size="sm">
                                <i class="fa-light fa-bolt"></i>
                                {{ __('Actions') }}
                                <i class="fa-light fa-chevron-down text-xs"></i>
                            </x-ui.button>
                        </x-slot:trigger>

                        <x-ui.dropdown-menu-item icon="fa-light fa-toggle-on" wire:click="enableSelected">
                            {{ __('Enable selected') }}
                        </x-ui.dropdown-menu-item>
                        <x-ui.dropdown-menu-item icon="fa-light fa-toggle-off" wire:click="disableSelected">
                            {{ __('Disable selected') }}
                        </x-ui.dropdown-menu-item>
                    </x-ui.dropdown-menu>

                    <x-ui.dialog :title="__('Delete selected tags?')" :description="__('This permanently removes all selected blog tags.')" width="sm" dismissible>
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
    </div>

    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($tags as $tag)
            <x-ui.card class="space-y-4 transition-transform duration-200 hover:-translate-y-1" wire:key="blog-tag-card-{{ $tag->id }}">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex min-w-0 items-start gap-3">
                        <x-ui.checkbox
                            value="{{ $tag->id }}"
                            wire:model.live="selectedTagIds"
                            minimal
                            class="mt-3"
                        />
                        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl text-base" style="background-color: color-mix(in srgb, {{ $tag->color ?: '#0f766e' }} 12%, white); color: {{ $tag->color ?: '#0f766e' }};">
                            <i class="{{ $tag->icon ?: 'fa-light fa-hashtag' }}"></i>
                        </span>
                        <div class="min-w-0">
                            <p class="truncate font-semibold" style="color: var(--theme-header-text-color);">{{ $tag->nameForLocale() }}</p>
                            <p class="truncate text-xs font-mono" style="color: var(--theme-muted-text-color);">/{{ $tag->slug }}</p>
                        </div>
                    </div>
                    <x-ui.badge :variant="$tag->statusVariant()">{{ $tag->statusLabel() }}</x-ui.badge>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-[1rem] border px-4 py-3" style="{{ $surfaceSoftStyle }}">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Posts') }}</p>
                        <p class="mt-2 text-xl font-semibold" style="color: var(--theme-header-text-color);">{{ number_format($tag->blogs_count) }}</p>
                    </div>
                    <div class="rounded-[1rem] border px-4 py-3" style="{{ $surfaceSoftStyle }}">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Created') }}</p>
                        <p class="mt-2 text-sm font-medium" style="color: var(--theme-header-text-color);">{{ $tag->createdAtFormatted('Y-m-d') ?: __('N/A') }}</p>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-4 border-t pt-4" style="border-color: var(--theme-border-color);">
                    <p class="inline-flex items-center gap-2 text-xs" style="color: var(--theme-muted-text-color);">
                        <i class="fa-light fa-palette"></i>
                        {{ $tag->color ?: '#0f766e' }}
                    </p>
                    <div class="flex items-center gap-2">
                        <x-ui.button :href="route('admin-blog-tags.edit', $tag)" variant="outline" size="sm" wire:navigate>
                            <i class="fa-light fa-pen-to-square"></i>
                            {{ __('Edit') }}
                        </x-ui.button>
                        <x-ui.dialog :title="__('Delete this tag?')" :description="__('This permanently removes the selected blog tag.')" width="sm" dismissible>
                            <x-slot:trigger>
                                <x-ui.button type="button" variant="danger" size="sm">
                                    <i class="fa-light fa-trash-can"></i>
                                    {{ __('Delete') }}
                                </x-ui.button>
                            </x-slot:trigger>
                            <x-slot:footer>
                                <div class="flex justify-end gap-3">
                                    <x-ui.button type="button" variant="outline" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                                    <x-ui.button type="button" variant="danger" wire:click="delete({{ $tag->id }})" x-on:click="open = false">{{ __('Delete') }}</x-ui.button>
                                </div>
                            </x-slot:footer>
                        </x-ui.dialog>
                    </div>
                </div>
            </x-ui.card>
        @empty
            <x-ui.card class="md:col-span-2 xl:col-span-3">
                <x-ui.empty
                    class="py-16"
                    icon="fa-light fa-tags"
                    :title="__('No blog tags found.')"
                    :description="__('Try broadening your filters or create your first tag.')"
                />
            </x-ui.card>
        @endforelse
    </div>

    @if ($tags->hasPages())
        <div>
            {{ $tags->links() }}
        </div>
    @endif
</div>
