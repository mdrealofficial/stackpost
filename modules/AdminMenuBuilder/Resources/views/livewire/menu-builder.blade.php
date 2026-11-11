<div
    x-data="{
        area: @entangle('activeArea').live,
        dragging: null,
        dropTarget: null,
        scrollFrame: null,
        startSectionDrag(areaKey, sectionIndex) {
            this.dragging = { type: 'section', area: areaKey, sectionIndex };
            this.dropTarget = null;
        },
        startItemDrag(areaKey, sectionIndex, itemIndex) {
            this.dragging = { type: 'item', area: areaKey, sectionIndex, itemIndex };
            this.dropTarget = null;
        },
        clearDrag() {
            this.dragging = null;
            this.dropTarget = null;
            if (this.scrollFrame) {
                cancelAnimationFrame(this.scrollFrame);
                this.scrollFrame = null;
            }
        },
        handleAutoScroll(event) {
            if (! this.dragging) {
                return;
            }

            const threshold = 120;
            const maxStep = 22;
            const topZone = threshold;
            const bottomZone = window.innerHeight - threshold;
            let delta = 0;

            if (event.clientY < topZone) {
                delta = -Math.max(8, Math.round(((topZone - event.clientY) / threshold) * maxStep));
            } else if (event.clientY > bottomZone) {
                delta = Math.max(8, Math.round(((event.clientY - bottomZone) / threshold) * maxStep));
            }

            if (! delta) {
                if (this.scrollFrame) {
                    cancelAnimationFrame(this.scrollFrame);
                    this.scrollFrame = null;
                }

                return;
            }

            if (this.scrollFrame) {
                return;
            }

            const tick = () => {
                window.scrollBy({ top: delta, behavior: 'auto' });
                this.scrollFrame = requestAnimationFrame(tick);
            };

            this.scrollFrame = requestAnimationFrame(tick);
        },
        setDropTarget(type, areaKey, sectionIndex, itemIndex = null) {
            if (! this.dragging || this.dragging.area !== areaKey || this.dragging.type !== type) {
                return;
            }

            this.dropTarget = { type, area: areaKey, sectionIndex, itemIndex };
        },
        dropSection(areaKey, targetSectionIndex) {
            if (! this.dragging || this.dragging.area !== areaKey) {
                this.clearDrag();
                return;
            }

            if (this.dragging.type === 'section') {
                this.$wire.moveSectionToIndex(areaKey, this.dragging.sectionIndex, targetSectionIndex);
            }

            if (this.dragging.type === 'item') {
                this.$wire.moveItemToSection(areaKey, this.dragging.sectionIndex, this.dragging.itemIndex, targetSectionIndex, null);
            }

            this.clearDrag();
        },
        dropItem(areaKey, targetSectionIndex, targetItemIndex) {
            if (! this.dragging || this.dragging.area !== areaKey || this.dragging.type !== 'item') {
                this.clearDrag();
                return;
            }

            this.$wire.moveItemToSection(areaKey, this.dragging.sectionIndex, this.dragging.itemIndex, targetSectionIndex, targetItemIndex);
            this.clearDrag();
        },
    }"
    x-on:dragover.window="handleAutoScroll($event)"
    x-on:drop.window="clearDrag()"
    class="mx-auto max-w-[88rem] space-y-6"
>
    <x-ui.page-hero
        :eyebrow="__('Navigation workspace')"
        :title="__('Menu Builder')"
        :description="__('Rename sidebar sections, rename menu items, and reorder navigation for both admin and user workspaces from one dedicated screen.')"
        icon="fa-light fa-bars-sort"
    >
        <x-slot:actions>
            <x-ui.button href="{{ settings_default_url() }}" variant="outline" wire:navigate>{{ __('Open settings') }}</x-ui.button>
            <button
                type="button"
                wire:click="resetDefaults"
                class="inline-flex h-10 items-center justify-center gap-2 whitespace-nowrap rounded-[0.75rem] border px-4.5 text-sm font-semibold tracking-[-0.01em] shadow-sm transition hover:-translate-y-px hover:shadow-[0_14px_28px_-22px_rgba(15,23,42,0.3)]"
                style="border-color: var(--theme-border-color); color: var(--theme-header-text-color); background-color: transparent;"
            >
                {{ __('Reset all defaults') }}
            </button>
        </x-slot:actions>
    </x-ui.page-hero>

    <div class="grid gap-5 xl:grid-cols-[18rem_minmax(0,1fr)]">
        <div class="space-y-4 rounded-[1.2rem] border p-4 shadow-[0_18px_36px_-30px_rgba(15,23,42,0.18)]" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-surface-base-rgb,255,255,255),0.98);">
            <button type="button" x-on:click="area = 'admin'" class="flex w-full items-center justify-between rounded-[1rem] border px-4 py-3 text-left transition" x-bind:style="area === 'admin' ? 'border-color: rgba(var(--theme-accent-rgb), 0.45); background-color: rgba(var(--theme-accent-rgb), 0.10); color: var(--theme-header-text-color); box-shadow: 0 18px 36px -28px rgba(var(--theme-accent-rgb), 0.45);' : 'border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-muted-text-color); background-color: rgba(var(--theme-surface-base-rgb,255,255,255),0.98);'">
                <span>
                    <span class="block text-sm font-semibold">{{ __('Admin sidebar') }}</span>
                    <span class="mt-1 block text-xs">{{ __('Manage the backend navigation tree.') }}</span>
                </span>
                <i class="fa-light fa-chevron-right text-xs"></i>
            </button>

            <button type="button" x-on:click="area = 'user'" class="flex w-full items-center justify-between rounded-[1rem] border px-4 py-3 text-left transition" x-bind:style="area === 'user' ? 'border-color: rgba(var(--theme-accent-rgb), 0.45); background-color: rgba(var(--theme-accent-rgb), 0.10); color: var(--theme-header-text-color); box-shadow: 0 18px 36px -28px rgba(var(--theme-accent-rgb), 0.45);' : 'border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-muted-text-color); background-color: rgba(var(--theme-surface-base-rgb,255,255,255),0.98);'">
                <span>
                    <span class="block text-sm font-semibold">{{ __('User sidebar') }}</span>
                    <span class="mt-1 block text-xs">{{ __('Manage the portal navigation tree.') }}</span>
                </span>
                <i class="fa-light fa-chevron-right text-xs"></i>
            </button>
        </div>

        <x-ui.section-card
            :title="__('Menu Builder')"
            :description="__('Move sections and items, then rename labels before saving the new sidebar structure.')"
            eyebrow="Workspace"
            header-class="py-4"
            eyebrow-class="text-[10px] tracking-[0.2em]"
            title-class="mt-1 text-[1.1rem] tracking-[-0.025em]"
            description-class="mt-1 leading-6"
            body-class="p-6"
        >
            <div class="space-y-6">
                @foreach (['admin' => __('Admin sidebar'), 'user' => __('User sidebar')] as $areaKey => $areaLabel)
                    <div x-show="area === '{{ $areaKey }}'" x-cloak class="space-y-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $areaLabel }}</p>
                                <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Use the arrow buttons to move sections and items. Labels update the rendered sidebar immediately after saving.') }}</p>
                            </div>
                            <button
                                type="button"
                                wire:click="resetArea('{{ $areaKey }}')"
                                class="inline-flex h-10 items-center justify-center gap-2 whitespace-nowrap rounded-[0.75rem] border px-4.5 text-sm font-semibold tracking-[-0.01em] shadow-sm transition hover:-translate-y-px hover:shadow-[0_14px_28px_-22px_rgba(15,23,42,0.3)]"
                                style="border-color: var(--theme-border-color); color: var(--theme-header-text-color); background-color: transparent;"
                            >
                                {{ __('Reset to default') }}
                            </button>
                        </div>

                        <div class="space-y-4">
                            @foreach (($menus[$areaKey] ?? []) as $sectionIndex => $section)
                                <div
                                    x-show="dragging && dragging.type === 'section' && dragging.area === '{{ $areaKey }}'"
                                    x-on:dragover.prevent.stop
                                    x-on:dragenter.prevent.stop="setDropTarget('section', '{{ $areaKey }}', {{ $sectionIndex }})"
                                    x-on:drop.prevent.stop="dropSection('{{ $areaKey }}', {{ $sectionIndex }})"
                                    class="rounded-[0.9rem] border border-dashed px-4 py-3 text-xs font-semibold uppercase tracking-[0.18em] transition"
                                    x-bind:style="dropTarget && dropTarget.type === 'section' && dropTarget.area === '{{ $areaKey }}' && dropTarget.sectionIndex === {{ $sectionIndex }}
                                        ? 'border-color: rgba(var(--theme-accent-rgb), 0.45); background-color: rgba(var(--theme-accent-rgb), 0.10); color: var(--theme-accent);'
                                        : 'border-color: rgba(var(--theme-border-color-rgb), 0.4); background-color: rgba(var(--theme-border-color-rgb), 0.02); color: var(--theme-muted-text-color); opacity: 0;'"
                                >
                                    {{ __('Drop section here') }}
                                </div>

                                <div
                                    x-on:dragstart="startSectionDrag('{{ $areaKey }}', {{ $sectionIndex }})"
                                    x-on:dragend="clearDrag()"
                                    x-on:dragover.prevent
                                    x-on:drop.prevent="dropSection('{{ $areaKey }}', {{ $sectionIndex }})"
                                    class="rounded-[1rem] border p-4 shadow-[0_18px_36px_-30px_rgba(15,23,42,0.18)] transition"
                                    x-bind:style="dragging && dragging.area === '{{ $areaKey }}' && dragging.type === 'section' && dragging.sectionIndex === {{ $sectionIndex }}
                                        ? 'border-color: rgba(var(--theme-accent-rgb), 0.45); background-color: rgba(var(--theme-accent-rgb), 0.06); opacity: 0.7;'
                                        : 'border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-surface-base-rgb,255,255,255),0.98);'"
                                >
                                    <div class="flex items-start gap-3">
                                        <div class="flex flex-col gap-2">
                                            <button
                                                type="button"
                                                draggable="true"
                                                x-on:dragstart.stop="startSectionDrag('{{ $areaKey }}', {{ $sectionIndex }})"
                                                x-on:dragend.stop="clearDrag()"
                                                class="inline-flex h-9 w-9 cursor-grab items-center justify-center rounded-[0.8rem] border transition hover:bg-slate-900/5 active:cursor-grabbing"
                                                style="border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-muted-text-color);"
                                                title="{{ __('Drag section') }}"
                                            >
                                                <i class="fa-light fa-grip-dots-vertical"></i>
                                            </button>
                                            <button type="button" wire:click="moveSectionUp('{{ $areaKey }}', {{ $sectionIndex }})" class="inline-flex h-9 w-9 items-center justify-center rounded-[0.8rem] border transition hover:bg-slate-900/5" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-muted-text-color);">
                                                <i class="fa-light fa-arrow-up"></i>
                                            </button>
                                            <button type="button" wire:click="moveSectionDown('{{ $areaKey }}', {{ $sectionIndex }})" class="inline-flex h-9 w-9 items-center justify-center rounded-[0.8rem] border transition hover:bg-slate-900/5" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-muted-text-color);">
                                                <i class="fa-light fa-arrow-down"></i>
                                            </button>
                                        </div>

                                        <div class="min-w-0 flex-1 space-y-4">
                                            <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_11rem]">
                                                <x-ui.input wire:model.defer="menus.{{ $areaKey }}.{{ $sectionIndex }}.label" :label="__('Section title')" />
                                                <x-ui.input :value="$section['key']" :label="__('Section key')" disabled />
                                            </div>

                                            <div class="space-y-3">
                                                @foreach (($section['items'] ?? []) as $itemIndex => $item)
                                                    <div
                                                        x-show="dragging && dragging.type === 'item' && dragging.area === '{{ $areaKey }}'"
                                                        x-on:dragover.prevent.stop
                                                        x-on:dragenter.prevent.stop="setDropTarget('item', '{{ $areaKey }}', {{ $sectionIndex }}, {{ $itemIndex }})"
                                                        x-on:drop.prevent.stop="dropItem('{{ $areaKey }}', {{ $sectionIndex }}, {{ $itemIndex }})"
                                                        class="rounded-[0.8rem] border border-dashed px-3 py-2 text-[11px] font-semibold uppercase tracking-[0.18em] transition"
                                                        x-bind:style="dropTarget && dropTarget.type === 'item' && dropTarget.area === '{{ $areaKey }}' && dropTarget.sectionIndex === {{ $sectionIndex }} && dropTarget.itemIndex === {{ $itemIndex }}
                                                            ? 'border-color: rgba(var(--theme-accent-rgb), 0.45); background-color: rgba(var(--theme-accent-rgb), 0.10); color: var(--theme-accent);'
                                                            : 'border-color: rgba(var(--theme-border-color-rgb), 0.4); background-color: rgba(var(--theme-border-color-rgb), 0.02); color: var(--theme-muted-text-color); opacity: 0;'"
                                                    >
                                                        {{ __('Drop item here') }}
                                                    </div>

                                                    <div
                                                        x-on:dragover.prevent.stop
                                                        class="rounded-[0.95rem] border p-3 transition"
                                                        x-bind:style="dragging && dragging.area === '{{ $areaKey }}' && dragging.type === 'item' && dragging.sectionIndex === {{ $sectionIndex }} && dragging.itemIndex === {{ $itemIndex }}
                                                            ? 'border-color: rgba(var(--theme-accent-rgb), 0.45); background-color: rgba(var(--theme-accent-rgb), 0.08); opacity: 0.7;'
                                                            : 'border-color: rgba(var(--theme-border-color-rgb), 0.6); background-color: rgba(var(--theme-border-color-rgb), 0.03);'"
                                                    >
                                                        <div class="flex items-start gap-3">
                                                            <div class="flex flex-col gap-2">
                                                                <button
                                                                    type="button"
                                                                    draggable="true"
                                                                    x-on:dragstart.stop="startItemDrag('{{ $areaKey }}', {{ $sectionIndex }}, {{ $itemIndex }})"
                                                                    x-on:dragend.stop="clearDrag()"
                                                                    class="inline-flex h-8 w-8 cursor-grab items-center justify-center rounded-[0.7rem] border transition hover:bg-slate-900/5 active:cursor-grabbing"
                                                                    style="border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-muted-text-color);"
                                                                    title="{{ __('Drag item') }}"
                                                                >
                                                                    <i class="fa-light fa-grip-dots-vertical text-xs"></i>
                                                                </button>
                                                                <button type="button" wire:click="moveItemUp('{{ $areaKey }}', {{ $sectionIndex }}, {{ $itemIndex }})" class="inline-flex h-8 w-8 items-center justify-center rounded-[0.7rem] border transition hover:bg-slate-900/5" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-muted-text-color);">
                                                                    <i class="fa-light fa-arrow-up text-xs"></i>
                                                                </button>
                                                                <button type="button" wire:click="moveItemDown('{{ $areaKey }}', {{ $sectionIndex }}, {{ $itemIndex }})" class="inline-flex h-8 w-8 items-center justify-center rounded-[0.7rem] border transition hover:bg-slate-900/5" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-muted-text-color);">
                                                                    <i class="fa-light fa-arrow-down text-xs"></i>
                                                                </button>
                                                            </div>

                                                            <div class="min-w-0 flex-1 grid gap-3 lg:grid-cols-[minmax(0,1fr)_16rem]">
                                                                <x-ui.input wire:model.defer="menus.{{ $areaKey }}.{{ $sectionIndex }}.items.{{ $itemIndex }}.label" :label="__('Menu label')" />
                                                                <div class="space-y-2">
                                                                    <x-ui.input :value="$item['key']" :label="__('Item key')" disabled />
                                                                    @if (!empty($item['has_children']))
                                                                        <p class="text-xs" style="color: var(--theme-muted-text-color);">{{ trans_choice(':count child link|:count child links', $item['children_count'], ['count' => $item['children_count']]) }}</p>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endforeach

                                                <div
                                                    x-show="dragging && dragging.type === 'item' && dragging.area === '{{ $areaKey }}'"
                                                    x-on:dragover.prevent.stop
                                                    x-on:dragenter.prevent.stop="setDropTarget('item', '{{ $areaKey }}', {{ $sectionIndex }}, null)"
                                                    x-on:drop.prevent.stop="dropSection('{{ $areaKey }}', {{ $sectionIndex }})"
                                                    class="rounded-[0.9rem] border border-dashed px-4 py-3 text-xs font-semibold uppercase tracking-[0.16em]"
                                                    x-bind:style="dropTarget && dropTarget.type === 'item' && dropTarget.area === '{{ $areaKey }}' && dropTarget.sectionIndex === {{ $sectionIndex }} && dropTarget.itemIndex === null
                                                        ? 'border-color: rgba(var(--theme-accent-rgb), 0.45); color: var(--theme-accent); background-color: rgba(var(--theme-accent-rgb), 0.10);'
                                                        : 'border-color: rgba(var(--theme-accent-rgb), 0.35); color: var(--theme-accent); background-color: rgba(var(--theme-accent-rgb), 0.05);'"
                                                >
                                                    {{ __('Drop here to move item into this section') }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach

                            <div
                                x-show="dragging && dragging.type === 'section' && dragging.area === '{{ $areaKey }}'"
                                x-on:dragover.prevent.stop
                                x-on:dragenter.prevent.stop="setDropTarget('section', '{{ $areaKey }}', {{ count($menus[$areaKey] ?? []) }})"
                                x-on:drop.prevent.stop="dropSection('{{ $areaKey }}', {{ count($menus[$areaKey] ?? []) }})"
                                class="rounded-[0.9rem] border border-dashed px-4 py-3 text-xs font-semibold uppercase tracking-[0.18em] transition"
                                x-bind:style="dropTarget && dropTarget.type === 'section' && dropTarget.area === '{{ $areaKey }}' && dropTarget.sectionIndex === {{ count($menus[$areaKey] ?? []) }}
                                    ? 'border-color: rgba(var(--theme-accent-rgb), 0.45); background-color: rgba(var(--theme-accent-rgb), 0.10); color: var(--theme-accent);'
                                    : 'border-color: rgba(var(--theme-border-color-rgb), 0.4); background-color: rgba(var(--theme-border-color-rgb), 0.02); color: var(--theme-muted-text-color); opacity: 0;'"
                            >
                                {{ __('Drop section here') }}
                            </div>
                        </div>
                    </div>
                @endforeach

                <div class="grid gap-3 border-t pt-4 md:grid-cols-[8rem_auto]" style="border-color: rgba(var(--theme-border-color-rgb), 0.68);">
                    <div class="flex min-h-10 items-center">
                        <x-action-message class="text-emerald-600 dark:text-emerald-400" on="settings-saved">{{ __('Saved.') }}</x-action-message>
                    </div>
                    <div class="flex items-center justify-end">
                        <x-ui.button type="button" wire:click="save">{{ __('Save menu changes') }}</x-ui.button>
                    </div>
                </div>
            </div>
        </x-ui.section-card>
    </div>
</div>
