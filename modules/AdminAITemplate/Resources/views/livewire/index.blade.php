<div
    class="mx-auto max-w-[88rem] space-y-6"
    x-data="{
        formOpen: $wire.entangle('showFormModal').live,
        cateId: $wire.entangle('form.cate_id'),
        content: $wire.entangle('form.content'),
        status: $wire.entangle('form.status'),
        categories: @js($formCategories->map(fn ($category) => [
            'id' => (string) $category->id,
            'name' => $category->name,
            'icon' => $category->icon ?: 'fa-light fa-folder-tree',
            'status' => $category->status ? '1' : '0',
        ])->values()->all()),
        selectedCategory() {
            return this.categories.find((category) => category.id === this.cateId)
                || { id: '', name: @js(__('Select category')), icon: 'fa-light fa-folder-tree', status: '1' };
        },
        statusBadgeClass(value) {
            return value === '1'
                ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                : 'border-slate-200 bg-slate-50 text-slate-600';
        },
    }"
>
    @if ($statusMessage)
        <x-ui.alert :variant="$statusVariant" :title="$statusVariant === 'success' ? __('Saved') : __('Action failed')" :description="$statusMessage" />
    @endif

    <x-ui.page-hero
        :eyebrow="__('AI center')"
        :title="__('AI Templates')"
        :description="__('Manage reusable AI prompts, keep them grouped by category, and maintain a cleaner prompt library.')"
        :count="$summary['total']"
        icon="fa-light fa-rectangle-history"
    >
        <x-slot:actions>
            <x-ui.button href="{{ route('admin-ai-template-categories.index') }}" variant="outline" wire:navigate>{{ __('Open categories') }}</x-ui.button>
            <x-ui.button type="button" wire:click="openCreateModal">{{ __('Create template') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-hero>

    <x-ui.metric-strip
        :items="[
            ['label' => __('Total'), 'value' => number_format($summary['total']), 'description' => __('All AI templates currently stored in the library.'), 'progress' => 100, 'tone' => 'var(--theme-accent)'],
            ['label' => __('Enabled'), 'value' => number_format($summary['enabled']), 'description' => __('Templates active and available for current AI flows.'), 'progress' => $summary['total'] > 0 ? (int) round(($summary['enabled'] / $summary['total']) * 100) : 0, 'tone' => 'var(--theme-success-color)'],
            ['label' => __('Disabled'), 'value' => number_format($summary['disabled']), 'description' => __('Templates kept in storage but not currently available.'), 'progress' => $summary['total'] > 0 ? (int) round(($summary['disabled'] / $summary['total']) * 100) : 0, 'tone' => 'var(--theme-muted-text-color)'],
        ]"
        :show-icons="false"
        columns="md:grid-cols-3"
    />

    <x-ui.filter-panel fields-class="xl:grid-cols-[minmax(0,1fr)_220px_240px_auto]">
        <x-slot:fields>
            <x-ui.input wire:model.live.debounce.300ms="search" :label="__('Search')" :placeholder="__('Template content or category...')" />
            <x-ui.select wire:model.live="statusFilter" :label="__('Status')">
                <option value="all">{{ __('All') }}</option>
                <option value="1">{{ __('Enabled') }}</option>
                <option value="0">{{ __('Disabled') }}</option>
            </x-ui.select>
            <x-ui.select wire:model.live="categoryFilter" :label="__('Category')">
                <option value="">{{ __('All categories') }}</option>
                @foreach ($filterCategories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </x-ui.select>
        </x-slot:fields>
        <x-slot:actions>
            <x-ui.button type="button" wire:click="$refresh">{{ __('Refresh') }}</x-ui.button>
            <x-ui.button type="button" variant="outline" wire:click="$set('search', ''); $set('statusFilter', 'all'); $set('categoryFilter', '')">{{ __('Reset') }}</x-ui.button>
        </x-slot:actions>
        <x-slot:chips>
            @if ($search !== '')
                <x-ui.badge variant="neutral">{{ __('Search') }}: {{ $search }}</x-ui.badge>
            @endif
            @if ($statusFilter !== 'all')
                <x-ui.badge variant="neutral">{{ __('Status') }}: {{ $statusFilter === '1' ? __('Enabled') : __('Disabled') }}</x-ui.badge>
            @endif
            @if ($categoryFilter !== '')
                <x-ui.badge variant="neutral">{{ __('Category') }}: {{ $filterCategories->firstWhere('id', (int) $categoryFilter)?->name ?? __('Unknown') }}</x-ui.badge>
            @endif
        </x-slot:chips>
    </x-ui.filter-panel>

    <x-ui.bulk-toolbar compact>
        <x-slot:chips>
            <x-ui.badge variant="neutral">{{ number_format($templates->total()) }} {{ __('matching templates') }}</x-ui.badge>
        </x-slot:chips>
        <x-slot:selection>
            <span class="text-sm" style="color: var(--theme-muted-text-color);">{{ __('Page :current of :last', ['current' => $templates->currentPage(), 'last' => $templates->lastPage()]) }}</span>
        </x-slot:selection>
    </x-ui.bulk-toolbar>

    <x-ui.datatable-shell :title="__('Template library')" :info="__('Stored prompts grouped under AI template categories.')" header-class="py-4" eyebrow-class="text-[10px] tracking-[0.2em]" title-class="mt-1 text-[1.15rem] tracking-[-0.025em]" description-class="mt-1 leading-6">
        <x-ui.table class="rounded-none border-0 shadow-none">
            <x-ui.table-head>
                <x-ui.table-cell head>{{ __('Template') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Category') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Created') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Status') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Actions') }}</x-ui.table-cell>
            </x-ui.table-head>
            <x-ui.table-body>
                @forelse ($templates as $template)
                    @php
                        $colors = $template->category?->colorClasses() ?? ['bg' => 'bg-slate-100', 'text' => 'text-slate-600', 'ring' => 'ring-slate-200'];
                    @endphp
                    <x-ui.table-row wire:key="ai-template-row-{{ $template->id }}">
                        <x-ui.table-cell>
                            <div class="space-y-1">
                                <p class="font-medium leading-6" style="color: var(--theme-header-text-color);">{{ $template->contentPreview(150) }}</p>
                                <p class="text-xs" style="color: var(--theme-muted-text-color);">#{{ $template->id }}</p>
                            </div>
                        </x-ui.table-cell>
                        <x-ui.table-cell>
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl ring-1 {{ $colors['bg'] }} {{ $colors['text'] }} {{ $colors['ring'] }}">
                                    <i class="{{ $template->category?->icon ?: 'fa-light fa-folder-tree' }}"></i>
                                </div>
                                <div class="space-y-1">
                                    <p class="font-medium" style="color: var(--theme-header-text-color);">{{ $template->category?->name ?: __('Uncategorized') }}</p>
                                    <p class="text-xs" style="color: var(--theme-muted-text-color);">{{ $template->category?->icon ?: __('No icon') }}</p>
                                </div>
                            </div>
                        </x-ui.table-cell>
                        <x-ui.table-cell><p class="text-sm" style="color: var(--theme-muted-text-color);">{{ $template->createdAtFormatted() ?: __('N/A') }}</p></x-ui.table-cell>
                        <x-ui.table-cell><x-ui.badge :variant="$template->statusVariant()">{{ $template->statusLabel() }}</x-ui.badge></x-ui.table-cell>
                        <x-ui.table-cell>
                            <div class="flex items-center gap-2">
                                <x-ui.button type="button" variant="outline" size="sm" wire:click="openEditModal({{ $template->id }})">{{ __('Edit') }}</x-ui.button>
                                <x-ui.dialog :title="__('Delete this AI template?')" :description="__('This permanently removes the selected AI template from your reusable library.')" width="sm" dismissible>
                                    <x-slot:trigger>
                                        <x-ui.button type="button" variant="danger" size="sm">{{ __('Delete') }}</x-ui.button>
                                    </x-slot:trigger>
                                    <x-slot:footer>
                                        <div class="flex justify-end gap-3">
                                            <x-ui.button type="button" variant="outline" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                                            <x-ui.button type="button" variant="danger" wire:click="delete({{ $template->id }})" x-on:click="open = false">{{ __('Delete') }}</x-ui.button>
                                        </div>
                                    </x-slot:footer>
                                </x-ui.dialog>
                            </div>
                        </x-ui.table-cell>
                    </x-ui.table-row>
                @empty
                    <x-ui.table-row>
                        <x-ui.table-cell colspan="5" class="py-10">
                            <x-ui.empty icon="fa-light fa-rectangle-history" :title="__('No AI templates found.')" :description="__('Try broadening your filters or create a new template to populate the library.')" />
                        </x-ui.table-cell>
                    </x-ui.table-row>
                @endforelse
            </x-ui.table-body>
        </x-ui.table>
        @if ($templates->hasPages())
            <div class="border-t px-6 py-4" style="border-color: var(--theme-border-color);">
                {{ $templates->links() }}
            </div>
        @endif
    </x-ui.datatable-shell>

    @php($submitLabel = $isEditing ? __('Save changes') : __('Create template'))

    <div
        x-cloak
        x-show="formOpen"
        class="fixed inset-0 z-[120] flex items-center justify-center p-6"
        x-on:keydown.escape.window="$wire.closeFormModal()"
    >
        <div class="absolute inset-0 bg-white/55 backdrop-blur-[6px] dark:bg-slate-950/55" x-on:click="$wire.closeFormModal()"></div>

        <div x-show="formOpen" x-transition.opacity.scale.90 class="relative w-full max-w-4xl">
            <div class="overflow-hidden rounded-[1.15rem] border shadow-[0_32px_80px_-34px_rgba(15,23,42,0.32)]" style="border-color: color-mix(in srgb, var(--theme-border-color) 58%, transparent); background-color: var(--theme-surface-overlay);">
                <div class="flex items-start justify-between gap-4 border-b px-5 py-4 sm:px-6 sm:py-5" style="border-color: color-mix(in srgb, var(--theme-border-color) 52%, transparent);">
                    <div class="min-w-0">
                        <h3 class="text-[1.05rem] font-semibold tracking-[-0.02em]" style="color: var(--theme-header-text-color);">{{ $isEditing ? __('Edit AI template') : __('Create AI template') }}</h3>
                        <p class="mt-2 text-[15px] leading-7" style="color: var(--theme-muted-text-color);">{{ $isEditing ? __('Update category assignment, prompt content, and publication status for this AI template.') : __('Create a reusable AI prompt template and assign it to one of your template categories.') }}</p>
                    </div>

                    <button type="button" class="transition" style="color: var(--theme-muted-text-color);" x-on:click="$wire.closeFormModal()">
                        <i class="fa-light fa-xmark text-lg"></i>
                    </button>
                </div>

                <form wire:submit="saveTemplate" class="space-y-5 px-5 py-4 sm:px-6 sm:py-5">
                    <div class="overflow-hidden rounded-[1.1rem] border" style="border-color: color-mix(in srgb, var(--theme-border-color) 56%, transparent); background:
                        radial-gradient(circle at top right, rgba(var(--theme-accent-rgb), 0.08), transparent 34%),
                        rgba(var(--theme-surface-base-rgb,255,255,255),0.92);">
                        <div class="flex flex-col gap-4 px-5 py-5 sm:flex-row sm:items-start sm:justify-between sm:px-6">
                            <div class="flex min-w-0 items-center gap-4">
                                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl ring-1 bg-violet-100 text-violet-600 ring-violet-200">
                                    <i x-bind:class="`${selectedCategory().icon || 'fa-light fa-folder-tree'} text-xl`"></i>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.2em]" style="color: var(--theme-muted-text-color);">{{ __('Live preview') }}</p>
                                    <p class="truncate text-lg font-semibold" style="color: var(--theme-header-text-color);" x-text="selectedCategory().name || @js(__('Select category'))"></p>
                                    <p class="truncate text-xs" style="color: var(--theme-muted-text-color);" x-text="content !== '' ? content.slice(0, 120) : @js(__('Write the reusable AI prompt content here.'))"></p>
                                </div>
                            </div>

                            <span
                                class="inline-flex items-center rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em]"
                                x-bind:class="statusBadgeClass(status)"
                                x-text="status === '1' ? @js(__('Enabled')) : @js(__('Disabled'))"
                            ></span>
                        </div>
                    </div>

                    <div class="grid items-start gap-5 md:grid-cols-2">
                        <x-ui.field :label="__('Status')" :error="$errors->first('form.status')" class="space-y-2.5">
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div class="rounded-[0.85rem] border px-4 py-3" style="border-color: var(--theme-border-color);">
                                    <x-ui.radio
                                        name="ai-template-status"
                                        value="1"
                                        x-model="status"
                                        :checked="$form['status'] === '1'"
                                        :label="__('Enable')"
                                    />
                                </div>
                                <div class="rounded-[0.85rem] border px-4 py-3" style="border-color: var(--theme-border-color);">
                                    <x-ui.radio
                                        name="ai-template-status"
                                        value="0"
                                        x-model="status"
                                        :checked="$form['status'] === '0'"
                                        :label="__('Disable')"
                                    />
                                </div>
                            </div>
                        </x-ui.field>

                        <x-ui.select x-model="cateId" :label="__('Category')" :error="$errors->first('form.cate_id')">
                                <option value="">{{ __('Select category') }}</option>
                                @foreach ($formCategories as $category)
                                    <option value="{{ $category->id }}">
                                        {{ $category->name }}{{ ! $category->status ? ' ('.__('Disabled').')' : '' }}
                                    </option>
                                @endforeach
                        </x-ui.select>
                    </div>

                    <x-ui.textarea x-model="content" :label="__('Content')" :error="$errors->first('form.content')" rows="10"></x-ui.textarea>

                    <div class="rounded-[1rem] border px-4 py-3 text-sm leading-6" style="border-color: color-mix(in srgb, var(--theme-border-color) 56%, transparent); background-color: rgba(var(--theme-surface-base-rgb,255,255,255),0.78); color: var(--theme-muted-text-color);">
                        {{ __('Keep the prompt concise, assign the best-fit category, and disable templates you want to preserve without exposing them to active AI flows.') }}
                    </div>

                    <div class="flex items-center justify-between gap-3 border-t pt-4" style="border-color: color-mix(in srgb, var(--theme-border-color) 52%, transparent);">
                        <div>
                            @if ($isEditing && $editingTemplate)
                                <x-ui.dialog :title="__('Delete this AI template?')" :description="__('This permanently removes the selected AI template from your reusable prompt library.')" width="sm" dismissible>
                                    <x-slot:trigger>
                                        <x-ui.button type="button" variant="danger">{{ __('Delete template') }}</x-ui.button>
                                    </x-slot:trigger>
                                    <x-slot:footer>
                                        <div class="flex justify-end gap-3">
                                            <x-ui.button type="button" variant="outline" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                                            <x-ui.button type="button" variant="danger" wire:click="delete({{ $editingTemplate->id }})" x-on:click="open = false">{{ __('Delete') }}</x-ui.button>
                                        </div>
                                    </x-slot:footer>
                                </x-ui.dialog>
                            @endif
                        </div>

                        <div class="flex items-center gap-3">
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
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
