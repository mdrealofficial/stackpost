<div
    class="mx-auto max-w-[88rem] space-y-6"
    x-data="{
        formOpen: $wire.entangle('showFormModal').live,
        name: $wire.entangle('form.name'),
        desc: $wire.entangle('form.desc'),
        icon: $wire.entangle('form.icon'),
        color: $wire.entangle('form.color'),
        status: $wire.entangle('form.status'),
        previewPalette(value) {
            const palette = {
                primary: { bg: 'bg-violet-100', text: 'text-violet-600', ring: 'ring-violet-200' },
                success: { bg: 'bg-emerald-100', text: 'text-emerald-600', ring: 'ring-emerald-200' },
                danger: { bg: 'bg-rose-100', text: 'text-rose-600', ring: 'ring-rose-200' },
                warning: { bg: 'bg-amber-100', text: 'text-amber-600', ring: 'ring-amber-200' },
                info: { bg: 'bg-sky-100', text: 'text-sky-600', ring: 'ring-sky-200' },
                dark: { bg: 'bg-slate-200', text: 'text-slate-700', ring: 'ring-slate-300' },
            };

            return palette[value] || palette.primary;
        },
        statusBadgeClass(value) {
            return value === '1'
                ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                : 'border-slate-200 bg-slate-50 text-slate-600';
        },
    }"
>
    @if ($statusMessage)
        <x-ui.alert variant="success" :title="__('Saved')" :description="$statusMessage" />
    @endif

    <x-ui.page-hero
        :eyebrow="__('AI center')"
        :title="__('AI Template Categories')"
        :description="__('Organize reusable AI prompts into clear category groups for faster authoring and governance.')"
        :count="$summary['total']"
        icon="fa-light fa-folders"
    >
        <x-slot:actions>
            <x-ui.button type="button" wire:click="openCreateModal">{{ __('Create category') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-hero>

    <x-ui.metric-strip
        :items="[
            ['label' => __('Total'), 'value' => number_format($summary['total']), 'description' => __('All configured category groups for AI templates.'), 'progress' => 100, 'tone' => 'var(--theme-accent)'],
            ['label' => __('Enabled'), 'value' => number_format($summary['enabled']), 'description' => __('Categories currently available to template authors.'), 'progress' => $summary['total'] > 0 ? (int) round(($summary['enabled'] / $summary['total']) * 100) : 0, 'tone' => 'var(--theme-success-color)'],
            ['label' => __('Disabled'), 'value' => number_format($summary['disabled']), 'description' => __('Categories hidden from the active template workflow.'), 'progress' => $summary['total'] > 0 ? (int) round(($summary['disabled'] / $summary['total']) * 100) : 0, 'tone' => 'var(--theme-muted-text-color)'],
        ]"
        :show-icons="false"
        columns="md:grid-cols-3"
    />

    <x-ui.filter-panel fields-class="md:grid-cols-[minmax(0,1fr)_220px_auto]">
        <x-slot:fields>
            <x-ui.input wire:model.live.debounce.300ms="search" :label="__('Search')" :placeholder="__('Name, icon, description...')" />
            <x-ui.select wire:model.live="statusFilter" :label="__('Status')">
                <option value="all">{{ __('All') }}</option>
                <option value="1">{{ __('Enabled') }}</option>
                <option value="0">{{ __('Disabled') }}</option>
            </x-ui.select>
        </x-slot:fields>
        <x-slot:actions>
            <x-ui.button type="button" wire:click="$refresh">{{ __('Refresh') }}</x-ui.button>
            <x-ui.button type="button" variant="outline" wire:click="$set('search', ''); $set('statusFilter', 'all')">{{ __('Reset') }}</x-ui.button>
        </x-slot:actions>
        <x-slot:chips>
            @if ($search !== '')
                <x-ui.badge variant="neutral">{{ __('Search') }}: {{ $search }}</x-ui.badge>
            @endif
            @if ($statusFilter !== 'all')
                <x-ui.badge variant="neutral">{{ __('Status') }}: {{ $statusFilter === '1' ? __('Enabled') : __('Disabled') }}</x-ui.badge>
            @endif
        </x-slot:chips>
    </x-ui.filter-panel>

    <x-ui.bulk-toolbar compact>
        <x-slot:chips>
            <x-ui.badge variant="neutral">{{ number_format($categories->total()) }} {{ __('matching categories') }}</x-ui.badge>
        </x-slot:chips>
        <x-slot:selection>
            <span class="text-sm" style="color: var(--theme-muted-text-color);">{{ __('Page :current of :last', ['current' => $categories->currentPage(), 'last' => $categories->lastPage()]) }}</span>
        </x-slot:selection>
    </x-ui.bulk-toolbar>

    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($categories as $category)
            @php
                $colors = $category->colorClasses();
            @endphp
            <x-ui.surface-card class="space-y-4" wire:key="ai-template-category-card-{{ $category->id }}">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex min-w-0 items-center gap-4">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl ring-1 {{ $colors['bg'] }} {{ $colors['text'] }} {{ $colors['ring'] }}">
                            <i class="{{ $category->icon ?: 'fa-light fa-folder-tree' }} text-lg"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="truncate font-semibold" style="color: var(--theme-header-text-color);">{{ $category->name }}</p>
                            <p class="truncate text-xs" style="color: var(--theme-muted-text-color);">{{ $category->icon }}</p>
                        </div>
                    </div>
                    <x-ui.badge :variant="$category->statusVariant()">{{ $category->statusLabel() }}</x-ui.badge>
                </div>

                <p class="min-h-[3rem] text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ $category->desc ?: __('No description provided for this template category yet.') }}</p>

                <div class="flex items-center justify-between gap-4 border-t pt-4" style="border-color: var(--theme-border-color);">
                    <p class="text-xs" style="color: var(--theme-muted-text-color);">{{ $category->createdAtFormatted() ?: __('N/A') }}</p>
                    <div class="flex items-center gap-2">
                        <x-ui.button type="button" variant="outline" size="sm" wire:click="openEditModal({{ $category->id }})">{{ __('Edit') }}</x-ui.button>
                        <x-ui.button
                            type="button"
                            variant="danger"
                            size="sm"
                            wire:click="delete({{ $category->id }})"
                            wire:confirm="{{ __('Delete this AI template category?|This permanently removes the category from your AI template structure.') }}"
                        >
                            {{ __('Delete') }}
                        </x-ui.button>
                    </div>
                </div>
            </x-ui.surface-card>
        @empty
            <div class="md:col-span-2 xl:col-span-3">
                <x-ui.empty icon="fa-light fa-folders" :title="__('No AI template categories found.')" :description="__('Try broadening your filters or create a new category to start grouping templates.')" />
            </div>
        @endforelse
    </div>

    @if ($categories->hasPages())
        <div class="rounded-[0.85rem] border px-6 py-4" style="border-color: var(--theme-border-color); background-color: var(--theme-surface-base);">
            {{ $categories->links() }}
        </div>
    @endif

    @php($submitLabel = $isEditing ? __('Save changes') : __('Create category'))

    <div
        x-cloak
        x-show="formOpen"
        class="fixed inset-0 z-[120] flex items-center justify-center p-6"
        x-on:keydown.escape.window="$wire.closeFormModal()"
    >
        <div class="absolute inset-0 bg-white/55 backdrop-blur-[6px] dark:bg-slate-950/55" x-on:click="$wire.closeFormModal()"></div>

        <div x-show="formOpen" x-transition.opacity.scale.90 class="relative w-full max-w-3xl">
            <div class="overflow-hidden rounded-[1.15rem] border shadow-[0_32px_80px_-34px_rgba(15,23,42,0.32)]" style="border-color: color-mix(in srgb, var(--theme-border-color) 58%, transparent); background-color: var(--theme-surface-overlay);">
                <div class="flex items-start justify-between gap-4 border-b px-5 py-4 sm:px-6 sm:py-5" style="border-color: color-mix(in srgb, var(--theme-border-color) 52%, transparent);">
                    <div class="min-w-0">
                        <h3 class="text-[1.05rem] font-semibold tracking-[-0.02em]" style="color: var(--theme-header-text-color);">{{ $isEditing ? __('Edit AI template category') : __('Create AI template category') }}</h3>
                        <p class="mt-2 text-[15px] leading-7" style="color: var(--theme-muted-text-color);">{{ $isEditing ? __('Update the category label, icon, color, and status used for AI templates.') : __('Define a new category that can group AI prompt templates by function or output style.') }}</p>
                    </div>

                    <button type="button" class="transition" style="color: var(--theme-muted-text-color);" x-on:click="$wire.closeFormModal()">
                        <i class="fa-light fa-xmark text-lg"></i>
                    </button>
                </div>

                <form wire:submit="saveCategory" class="space-y-5 px-5 py-4 sm:px-6 sm:py-5">
                    <div class="overflow-hidden rounded-[1.1rem] border" style="border-color: color-mix(in srgb, var(--theme-border-color) 56%, transparent); background:
                        radial-gradient(circle at top right, rgba(var(--theme-accent-rgb), 0.08), transparent 34%),
                        rgba(var(--theme-surface-base-rgb,255,255,255),0.92);">
                        <div class="flex flex-col gap-4 px-5 py-5 sm:flex-row sm:items-start sm:justify-between sm:px-6">
                            <div class="flex min-w-0 items-center gap-4">
                                <div
                                    class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl ring-1"
                                    x-bind:class="`${previewPalette(color).bg} ${previewPalette(color).text} ${previewPalette(color).ring}`"
                                >
                                    <i x-bind:class="`${icon !== '' ? icon : 'fa-light fa-folder-tree'} text-xl`"></i>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.2em]" style="color: var(--theme-muted-text-color);">{{ __('Live preview') }}</p>
                                    <p class="truncate text-lg font-semibold" style="color: var(--theme-header-text-color);" x-text="name !== '' ? name : @js(__('Category name'))"></p>
                                    <p class="truncate text-xs" style="color: var(--theme-muted-text-color);" x-text="icon !== '' ? icon : @js(__('Choose an icon class'))"></p>
                                </div>
                            </div>

                            <span
                                class="inline-flex items-center rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em]"
                                x-bind:class="statusBadgeClass(status)"
                                x-text="status === '1' ? @js(__('Enabled')) : @js(__('Disabled'))"
                            >
                            </span>
                        </div>

                        <div class="border-t px-5 py-4 sm:px-6" style="border-color: color-mix(in srgb, var(--theme-border-color) 50%, transparent);">
                            <p class="text-sm leading-6" style="color: var(--theme-muted-text-color);" x-text="desc !== '' ? desc : @js(__('This category description will help authors understand when to use this prompt group.'))"></p>
                        </div>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">
                        <x-ui.field :label="__('Status')" :error="$errors->first('form.status')" class="space-y-2.5">
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div class="rounded-[0.85rem] border px-4 py-3" style="border-color: var(--theme-border-color);">
                                    <x-ui.radio
                                        name="ai-template-category-status"
                                        value="1"
                                        x-model="status"
                                        :checked="$form['status'] === '1'"
                                        :label="__('Enable')"
                                    />
                                </div>
                                <div class="rounded-[0.85rem] border px-4 py-3" style="border-color: var(--theme-border-color);">
                                    <x-ui.radio
                                        name="ai-template-category-status"
                                        value="0"
                                        x-model="status"
                                        :checked="$form['status'] === '0'"
                                        :label="__('Disable')"
                                    />
                                </div>
                            </div>
                        </x-ui.field>

                        <div class="space-y-2.5">
                            <label class="text-sm font-medium" style="color: var(--theme-header-text-color);">{{ __('Highlight color') }}</label>
                            <select x-model="color" class="h-12 w-full rounded-[0.85rem] border px-4 text-sm" style="border-color: var(--theme-border-color); background-color: var(--theme-surface-base); color: var(--theme-header-text-color);">
                                @foreach ($colorOptions as $option)
                                    <option value="{{ $option['value'] }}">{{ __($option['label']) }}</option>
                                @endforeach
                            </select>
                            @error('form.color')
                                <p class="text-sm font-medium" style="color: var(--theme-danger-color);">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">
                        <div class="space-y-2.5">
                            <label class="text-sm font-medium" style="color: var(--theme-header-text-color);">{{ __('Name') }}</label>
                            <input type="text" x-model="name" required autofocus class="h-12 w-full rounded-[0.85rem] border px-4 text-sm" style="border-color: var(--theme-border-color); background-color: var(--theme-surface-base); color: var(--theme-header-text-color);">
                            @error('form.name')
                                <p class="text-sm font-medium" style="color: var(--theme-danger-color);">{{ $message }}</p>
                            @enderror
                        </div>

                        <x-ui.icon-picker
                            x-model="icon"
                            :label="__('Icon')"
                            :error="$errors->first('form.icon')"
                            :preview-color="'var(--theme-accent)'"
                            :dialog-title="__('Choose category icon')"
                            :dialog-description="__('Select the Font Awesome icon used for this AI template category.')"
                            :placeholder="'fa-light fa-folder-tree'"
                            :value="$form['icon'] ?: 'fa-light fa-folder-tree'"
                        />
                    </div>

                    <div class="space-y-2.5">
                        <label class="text-sm font-medium" style="color: var(--theme-header-text-color);">{{ __('Description') }}</label>
                        <textarea x-model="desc" rows="6" class="w-full rounded-[0.85rem] border px-4 py-3 text-sm" style="border-color: var(--theme-border-color); background-color: var(--theme-surface-base); color: var(--theme-header-text-color);"></textarea>
                        @error('form.desc')
                            <p class="text-sm font-medium" style="color: var(--theme-danger-color);">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="rounded-[1rem] border px-4 py-3 text-sm leading-6" style="border-color: color-mix(in srgb, var(--theme-border-color) 56%, transparent); background-color: rgba(var(--theme-surface-base-rgb,255,255,255),0.78); color: var(--theme-muted-text-color);">
                        {{ __('Use short category names, choose one clear Font Awesome icon, and write descriptions that explain what kind of AI prompts belong in this group.') }}
                    </div>

                    <div class="flex items-center justify-end gap-3 border-t pt-4" style="border-color: color-mix(in srgb, var(--theme-border-color) 52%, transparent);">
                        <button
                            type="button"
                            class="inline-flex h-11 items-center justify-center rounded-[0.85rem] border px-5 text-sm font-semibold shadow-[0_1px_2px_rgba(15,23,42,0.08)] transition hover:-translate-y-px"
                            style="border-color: var(--theme-border-color); background-color: var(--theme-surface-base); color: var(--theme-header-text-color);"
                            x-on:click="$wire.closeFormModal()"
                        >
                            {{ __('Cancel') }}
                        </button>
                        <button
                            type="submit"
                            class="inline-flex h-11 items-center justify-center rounded-[0.85rem] px-5 text-sm font-semibold text-white shadow-[0_10px_24px_-16px_rgba(var(--theme-accent-rgb),0.9)] transition hover:-translate-y-px"
                            style="background-color: var(--theme-accent);"
                        >
                            {{ $submitLabel }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
