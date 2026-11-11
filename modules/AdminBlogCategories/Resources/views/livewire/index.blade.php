<div class="space-y-6">
    @if ($statusMessage)
        <x-ui.alert :title="__('Saved')" :description="$statusMessage" variant="success" dismissible />
    @endif

    <x-ui.page-hero
        :eyebrow="__('Blogs')"
        :title="__('Blog Categories')"
        :description="__('Organize posts into reusable groups and manage localized labels from one place.')"
        :count="$summary['total']"
        icon="fa-light fa-folder-tree"
    >
        <x-slot:actions>
            <x-ui.button :href="route('admin-blog-categories.create')" wire:navigate>{{ __('Create category') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-hero>

    @php
        $categoryMetricCards = [
            [
                'label' => __('Total'),
                'value' => number_format($summary['total']),
                'description' => __('All blog categories currently available.'),
                'tone' => 'var(--theme-accent)',
                'progress' => 100,
            ],
            [
                'label' => __('Enabled'),
                'value' => number_format($summary['enabled']),
                'description' => __('Categories visible for publishing workflows.'),
                'tone' => '#10b981',
                'progress' => max(8, $summary['total'] > 0 ? round(($summary['enabled'] / $summary['total']) * 100) : 8),
            ],
            [
                'label' => __('Disabled'),
                'value' => number_format($summary['disabled']),
                'description' => __('Categories hidden from new content selection.'),
                'tone' => '#64748b',
                'progress' => max(8, $summary['total'] > 0 ? round(($summary['disabled'] / $summary['total']) * 100) : 8),
            ],
        ];
    @endphp

    <x-ui.metric-strip :items="$categoryMetricCards" :show-icons="false" />

    <x-ui.filter-panel fields-class="md:grid-cols-[minmax(0,1fr)_220px_auto]">
        <x-slot:fields>
            <x-ui.input wire:model.live.debounce.300ms="search" :label="__('Search')" :placeholder="__('Name, slug, description...')" />
            <x-ui.select wire:model.live="statusFilter" :label="__('Status')">
                <option value="all">{{ __('All') }}</option>
                <option value="1">{{ __('Enabled') }}</option>
                <option value="0">{{ __('Disabled') }}</option>
            </x-ui.select>
        </x-slot:fields>

        <x-slot:actions>
            <x-ui.button type="button" wire:click="$refresh">{{ __('Filter') }}</x-ui.button>
            <x-ui.button :href="route('admin-blog-categories.index')" variant="outline" wire:navigate>{{ __('Reset') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.filter-panel>

    <x-ui.bulk-toolbar compact>
        <x-slot:chips>
            @if ($search !== '')
                <span class="inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-medium" style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color);">{{ $search }}</span>
            @endif
            <span class="inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-medium" style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color);">{{ __('Rows') }}: {{ $categories->count() }}</span>
            <x-ui.button type="button" variant="{{ $statusFilter === '1' ? 'primary' : 'outline' }}" size="sm" wire:click="$set('statusFilter', '1')">{{ __('Enabled') }}</x-ui.button>
            <x-ui.button type="button" variant="{{ $statusFilter === '0' ? 'primary' : 'outline' }}" size="sm" wire:click="$set('statusFilter', '0')">{{ __('Disabled') }}</x-ui.button>
            <span class="inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-medium" style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color);">
                <i class="fa-light fa-grip-dots mr-2"></i>{{ __('Drag to reorder this page') }}
            </span>
        </x-slot:chips>

        <x-slot:selection>
            <div class="flex min-w-0 items-center gap-3">
                <x-ui.checkbox
                    wire:model.live="selectPage"
                    :label="__('Check all')"
                    minimal
                />

                @if (count($selectedCategoryIds) > 0)
                    <span class="text-sm font-medium" style="color: var(--theme-header-text-color);">
                        {{ trans_choice('{1} :count selected|[2,*] :count selected', count($selectedCategoryIds), ['count' => count($selectedCategoryIds)]) }}
                    </span>
                @else
                    <span class="text-sm" style="color: var(--theme-muted-text-color);">{{ __('No categories selected') }}</span>
                @endif
            </div>

            @if (count($selectedCategoryIds) > 0)
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

                <x-ui.dialog :title="__('Delete selected categories?')" :description="__('This permanently removes all selected blog categories.')" width="sm" dismissible>
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

    <div
        x-data="{
            draggingId: null,
            startDrag(event) {
                const card = event.currentTarget;
                this.draggingId = card.dataset.categoryId;
                card.classList.add('opacity-60');
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', this.draggingId);
            },
            dragOver(event) {
                event.preventDefault();
                const sourceId = this.draggingId;
                const target = event.currentTarget;
                const targetId = target.dataset.categoryId;
                if (!sourceId || sourceId === targetId) return;

                const parent = target.parentElement;
                const source = parent.querySelector(`[data-category-id='${sourceId}']`);
                if (!source) return;

                const targetRect = target.getBoundingClientRect();
                const insertAfter = (event.clientY - targetRect.top) > (targetRect.height / 2);
                parent.insertBefore(source, insertAfter ? target.nextElementSibling : target);
            },
            endDrag(event) {
                event.currentTarget.classList.remove('opacity-60');
                this.draggingId = null;
                this.syncOrder();
            },
            syncOrder() {
                const orderedIds = Array.from(this.$root.querySelectorAll('[data-category-id]')).map((item) => Number(item.dataset.categoryId));
                $wire.reorder(orderedIds);
            },
        }"
        class="grid gap-4"
    >
        @forelse ($categories as $category)
            <x-ui.card
                class="border p-0 transition-shadow duration-200 hover:shadow-[0_10px_30px_rgba(15,23,42,0.08)]"
                wire:key="blog-category-card-{{ $category->id }}"
                data-category-id="{{ $category->id }}"
                draggable="true"
                x-on:dragstart="startDrag($event)"
                x-on:dragover="dragOver($event)"
                x-on:dragend="endDrag($event)"
            >
                <div class="flex flex-col gap-4 px-5 py-5 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex min-w-0 items-start gap-4">
                        <div class="flex items-center gap-3 pt-1">
                            <x-ui.checkbox
                                value="{{ $category->id }}"
                                wire:model.live="selectedCategoryIds"
                                minimal
                            />
                            <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-2xl border text-sm transition" style="border-color: var(--theme-border-color); background-color: color-mix(in srgb, var(--theme-surface-soft) 72%, transparent); color: var(--theme-muted-text-color); cursor: grab;">
                                <i class="fa-light fa-grip-dots"></i>
                            </button>
                        </div>

                        <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl text-sm font-semibold" style="background-color: color-mix(in srgb, {{ $category->color ?: '#0f766e' }} 14%, transparent); color: {{ $category->color ?: '#0f766e' }};">
                            <i class="{{ $category->icon ?: 'fa-light fa-folder-tree' }}"></i>
                        </span>

                        <div class="min-w-0 space-y-2">
                            <div class="flex flex-wrap items-center gap-3">
                                <p class="text-base font-semibold" style="color: var(--theme-header-text-color);">{{ $category->nameForLocale() }}</p>
                                <x-ui.badge :variant="$category->statusVariant()">{{ $category->statusLabel() }}</x-ui.badge>
                                <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-medium" style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color);">
                                    #{{ $category->sort_order }}
                                </span>
                            </div>

                            <p class="text-sm font-mono" style="color: var(--theme-muted-text-color);">/{{ $category->slug }}</p>
                            <p class="text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ $category->descriptionForLocale() ?: __('No description') }}</p>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3 lg:min-w-[28rem]">
                        <div class="rounded-[1rem] border px-4 py-3" style="border-color: var(--theme-border-color); background-color: color-mix(in srgb, var(--theme-surface-soft) 76%, transparent);">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Posts') }}</p>
                            <p class="mt-2 text-lg font-semibold" style="color: var(--theme-header-text-color);">{{ number_format($category->blogs_count) }}</p>
                        </div>
                        <div class="rounded-[1rem] border px-4 py-3" style="border-color: var(--theme-border-color); background-color: color-mix(in srgb, var(--theme-surface-soft) 76%, transparent);">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Updated') }}</p>
                            <p class="mt-2 text-sm font-medium" style="color: var(--theme-header-text-color);">{{ $category->createdAtFormatted() ?: __('N/A') }}</p>
                        </div>
                        <div class="flex items-center justify-end gap-2 rounded-[1rem] border px-4 py-3" style="border-color: var(--theme-border-color); background-color: color-mix(in srgb, var(--theme-surface-base) 98%, transparent);">
                            <x-ui.button :href="route('admin-blog-categories.edit', $category)" variant="outline" size="sm" wire:navigate>{{ __('Edit') }}</x-ui.button>
                            <x-ui.dialog :title="__('Delete this category?')" :description="__('This permanently removes the selected blog category.')" width="sm" dismissible>
                                <x-slot:trigger>
                                    <x-ui.button type="button" variant="danger" size="sm">{{ __('Delete') }}</x-ui.button>
                                </x-slot:trigger>
                                <x-slot:footer>
                                    <div class="flex justify-end gap-3">
                                        <x-ui.button type="button" variant="outline" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                                        <x-ui.button type="button" variant="danger" wire:click="delete({{ $category->id }})" x-on:click="open = false">{{ __('Delete') }}</x-ui.button>
                                    </div>
                                </x-slot:footer>
                            </x-ui.dialog>
                        </div>
                    </div>
                </div>
            </x-ui.card>
        @empty
            <x-ui.card>
                <x-ui.empty
                    class="py-12"
                    icon="fa-light fa-folder-tree"
                    :title="__('No blog categories found.')"
                    :description="__('Try broadening your filters or create your first category.')"
                />
            </x-ui.card>
        @endforelse
    </div>

    @if ($categories->hasPages())
        <div>
            {{ $categories->links() }}
        </div>
    @endif
</div>
