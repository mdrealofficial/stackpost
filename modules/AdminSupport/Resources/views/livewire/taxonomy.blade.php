<div class="mx-auto max-w-[88rem] space-y-6">
    @if ($statusMessage)
        <x-ui.alert :title="__('Saved')" :description="$statusMessage" variant="success" dismissible />
    @endif

    @php
        $resourceName = str($resource)->headline()->value();
        $directoryTitle = __(':label directory', ['label' => str($config['plural_label'])->headline()->value()]);
        $metricCards = [
            [
                'label' => __('Total'),
                'value' => number_format($summary['total']),
                'description' => __('Total items currently available in this support directory.'),
                'tone' => 'var(--theme-accent)',
                'progress' => 100,
                'icon' => 'fa-light fa-layer-group',
                'iconSurface' => 'color-mix(in srgb, var(--theme-accent) 10%, transparent)',
                'iconBorder' => 'color-mix(in srgb, var(--theme-accent) 18%, var(--theme-border-color))',
            ],
            [
                'label' => __('Enabled'),
                'value' => number_format($summary['enabled']),
                'description' => __('Items currently available for routing and workflow selection.'),
                'tone' => '#10b981',
                'progress' => $summary['total'] > 0 ? max(8, (int) round(($summary['enabled'] / $summary['total']) * 100)) : 8,
                'icon' => 'fa-light fa-circle-check',
                'iconSurface' => 'color-mix(in srgb, #10b981 10%, transparent)',
                'iconBorder' => 'color-mix(in srgb, #10b981 18%, var(--theme-border-color))',
            ],
            [
                'label' => __('Disabled'),
                'value' => number_format($summary['disabled']),
                'description' => __('Items kept in the directory but hidden from active support flows.'),
                'tone' => '#64748b',
                'progress' => $summary['total'] > 0 ? max(8, (int) round(($summary['disabled'] / $summary['total']) * 100)) : 8,
                'icon' => 'fa-light fa-eye-slash',
                'iconSurface' => 'color-mix(in srgb, #64748b 10%, transparent)',
                'iconBorder' => 'color-mix(in srgb, #64748b 18%, var(--theme-border-color))',
            ],
        ];
    @endphp

    <x-ui.page-hero
        :eyebrow="$config['eyebrow']"
        :title="$config['title']"
        :description="$config['description']"
        :count="$summary['total']"
        :icon="$config['icon']"
    />

    <x-ui.metric-strip :items="$metricCards" columns="md:grid-cols-3" />

    <section class="rounded-[2rem] border p-4 shadow-[0_28px_80px_-54px_rgba(15,23,42,0.24)] lg:p-5" style="border-color: color-mix(in srgb, var(--theme-border-color) 94%, transparent); background:
        radial-gradient(circle at top right, rgba(var(--theme-accent-rgb), 0.08), transparent 24%),
        linear-gradient(180deg, color-mix(in srgb, var(--theme-surface-soft) 72%, transparent), var(--theme-surface-base));">
        <div class="grid gap-5 xl:grid-cols-[360px_minmax(0,1fr)]">
            <x-ui.card class="overflow-hidden border shadow-none xl:sticky xl:top-6 self-start" style="border-color: color-mix(in srgb, var(--theme-border-color) 94%, transparent); background:
                linear-gradient(180deg, color-mix(in srgb, var(--theme-accent) 7%, transparent) 0%, var(--theme-surface-base) 28%);">
                <div class="border-b px-6 py-5" style="border-color: var(--theme-border-color);">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.2em]" style="color: var(--theme-muted-text-color);">{{ __('Editor') }}</p>
                    <h3 class="mt-2 text-[1.2rem] font-semibold tracking-[-0.03em] text-slate-950 dark:text-white">
                        {{ $editId !== '' ? __('Edit :label', ['label' => $config['singular']]) : __('Create :label', ['label' => $config['singular']]) }}
                    </h3>
                    <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">
                        {{ $editId !== '' ? __('Update the selected item and save changes.') : __('Add a new item for the support workflow.') }}
                    </p>
                </div>

                <form wire:submit="save" class="space-y-4 px-6 py-6">
                    <x-ui.input wire:model="name" name="name" :label="__('Name')" :error="$errors->first('name')" :placeholder="__('Enter name')" />
                    <x-ui.icon-picker
                        wire:model.live="icon"
                        name="icon"
                        :label="__('Icon class')"
                        :value="$icon ?: $config['default_icon']"
                        :error="$errors->first('icon')"
                        :preview-color="$color ?: $config['default_color']"
                    />
                    <x-ui.color-picker
                        wire:model.live="color"
                        name="color"
                        :label="__('Color')"
                        :value="$color ?: $config['default_color']"
                        :error="$errors->first('color')"
                    />

                    <div class="rounded-[1.15rem] border px-4 py-4" style="border-color: color-mix(in srgb, var(--theme-border-color) 94%, transparent); background:
                        linear-gradient(180deg, color-mix(in srgb, var(--theme-surface-soft) 66%, transparent), transparent),
                        var(--theme-surface-base);">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Preview') }}</p>
                            <span class="inline-flex rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.16em]" style="border-color: color-mix(in srgb, {{ $color ?: $config['default_color'] }} 18%, var(--theme-border-color)); color: {{ $color ?: $config['default_color'] }};">
                                {{ $editId !== '' ? __('Editing') : __('Draft') }}
                            </span>
                        </div>
                        <div class="mt-4 flex items-center gap-3 rounded-[1rem] border px-4 py-4" style="border-color: color-mix(in srgb, {{ $color ?: $config['default_color'] }} 12%, var(--theme-border-color)); background-color: color-mix(in srgb, {{ $color ?: $config['default_color'] }} 6%, transparent);">
                            <span class="inline-flex h-12 w-12 items-center justify-center rounded-full border text-base" style="border-color: color-mix(in srgb, {{ $color ?: $config['default_color'] }} 18%, var(--theme-border-color)); background-color: color-mix(in srgb, {{ $color ?: $config['default_color'] }} 12%, transparent); color: {{ $color ?: $config['default_color'] }};">
                                <i class="{{ $icon ?: $config['default_icon'] }}"></i>
                            </span>
                            <div class="min-w-0">
                                <p class="font-medium leading-6" style="color: var(--theme-header-text-color);">{{ $name !== '' ? $name : __('Untitled') }}</p>
                                <p class="text-xs" style="color: var(--theme-muted-text-color);">{{ $color ?: $config['default_color'] }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-[1.15rem] border px-4 py-4" style="border-color: color-mix(in srgb, var(--theme-border-color) 94%, transparent); background-color: color-mix(in srgb, var(--theme-surface-soft) 52%, transparent);">
                        <x-ui.field :label="__('Status')" :error="$errors->first('status')">
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <x-ui.radio name="status" value="1" :label="__('Enabled')" wire:model.live="status" />
                                <x-ui.radio name="status" value="0" :label="__('Disabled')" wire:model.live="status" />
                            </div>
                        </x-ui.field>
                    </div>

                    <div class="flex justify-end gap-3 border-t pt-4" style="border-color: var(--theme-border-color);">
                        @if ($editId !== '')
                            <x-ui.button type="button" variant="outline" wire:click="cancelEdit">{{ __('Cancel edit') }}</x-ui.button>
                        @endif
                        <x-ui.button type="submit">{{ $editId !== '' ? __('Save changes') : __('Create') }}</x-ui.button>
                    </div>
                </form>
            </x-ui.card>

            <div class="space-y-5">
                <x-ui.card class="overflow-hidden border shadow-none" style="border-color: color-mix(in srgb, var(--theme-border-color) 94%, transparent); background:
                    radial-gradient(circle at top right, rgba(var(--theme-accent-rgb), 0.08), transparent 26%),
                    linear-gradient(180deg, color-mix(in srgb, var(--theme-surface-soft) 68%, transparent), var(--theme-surface-base));">
                    <div class="border-b px-6 py-5" style="border-color: var(--theme-border-color);">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em]" style="color: var(--theme-muted-text-color);">{{ __('Filters') }}</p>
                        <div class="mt-2 flex flex-wrap items-end justify-between gap-3">
                            <div>
                                <h3 class="text-[1.08rem] font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">{{ __('Search the directory') }}</h3>
                                <p class="mt-1 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Filter by item name, icon token, and activation state before editing the directory.') }}</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <x-ui.badge variant="primary">{{ number_format($summary['total']) }} {{ str($config['plural_label'])->lower() }}</x-ui.badge>
                                <x-ui.badge variant="success">{{ number_format($summary['enabled']) }} {{ __('enabled') }}</x-ui.badge>
                            </div>
                        </div>
                    </div>

                    <div class="px-6 py-5">
                        <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_220px_auto]">
                            <x-ui.input wire:model.live.debounce.300ms="search" :label="__('Search')" :placeholder="__('Search by name, icon, or color')" />
                            <x-ui.select wire:model.live="statusFilter" :label="__('Status')">
                                <option value="all">{{ __('All') }}</option>
                                <option value="1">{{ __('Enabled') }}</option>
                                <option value="0">{{ __('Disabled') }}</option>
                            </x-ui.select>
                            <div class="flex items-end gap-3">
                                <x-ui.button type="button" wire:click="$refresh">{{ __('Filter') }}</x-ui.button>
                                <x-ui.button type="button" variant="outline" wire:click="$set('search', ''); $set('statusFilter', 'all')">{{ __('Reset') }}</x-ui.button>
                            </div>
                        </div>
                    </div>
                </x-ui.card>

                <x-ui.datatable-shell :title="$directoryTitle" :info="__('Review item identity, icon styling, visual color token, and activation state in a cleaner operational table.')" header-class="py-4" eyebrow-class="text-[10px] tracking-[0.2em]" title-class="mt-1 text-[1.15rem] tracking-[-0.025em]" description-class="mt-1 leading-6">
                <x-slot:toolbar>
                    <div class="flex flex-wrap items-center gap-3">
                        <x-ui.badge variant="primary">{{ number_format($summary['total']) }} {{ str($config['plural_label'])->lower() }}</x-ui.badge>
                        <x-ui.badge variant="success">{{ number_format($summary['enabled']) }} {{ __('enabled') }}</x-ui.badge>
                        <x-ui.badge variant="neutral">{{ number_format($summary['disabled']) }} {{ __('disabled') }}</x-ui.badge>
                        <x-ui.badge variant="neutral">{{ __('Support :label', ['label' => str($config['singular'])->lower()]) }}</x-ui.badge>
                    </div>
                </x-slot:toolbar>

                <x-ui.table class="rounded-none border-0 shadow-none">
                    <x-ui.table-head>
                        <x-ui.table-cell head>{{ __('Name') }}</x-ui.table-cell>
                        <x-ui.table-cell head>{{ __('Status') }}</x-ui.table-cell>
                        <x-ui.table-cell head>{{ __('Actions') }}</x-ui.table-cell>
                    </x-ui.table-head>
                    <x-ui.table-body>
                        @forelse ($items as $item)
                            <x-ui.table-row>
                                <x-ui.table-cell>
                                    <div class="flex items-center gap-3">
                                        <span class="inline-flex h-11 w-11 items-center justify-center rounded-full" style="background-color: color-mix(in srgb, {{ $item->color }} 14%, transparent); color: {{ $item->color }};">
                                            <i class="{{ $item->icon }}"></i>
                                        </span>
                                        <div>
                                            <p class="font-medium" style="color: var(--theme-header-text-color);">{{ $item->name }}</p>
                                            <p class="text-xs font-mono" style="color: {{ $item->color ?: 'var(--theme-muted-text-color)' }};">{{ $item->color ?: '#000000' }}</p>
                                        </div>
                                    </div>
                                </x-ui.table-cell>
                                <x-ui.table-cell><x-ui.badge :variant="$item->status ? 'success' : 'neutral'">{{ $item->status ? __('Enabled') : __('Disabled') }}</x-ui.badge></x-ui.table-cell>
                                <x-ui.table-cell>
                                    <div class="flex items-center justify-end gap-2 pr-4">
                                        <x-ui.button type="button" variant="outline" size="sm" wire:click="edit({{ $item->id }})">{{ __('Edit') }}</x-ui.button>
                                        <x-ui.dialog :title="__('Delete this item?')" :description="__('This action cannot be undone.')" width="sm" dismissible>
                                            <x-slot:trigger>
                                                <x-ui.button type="button" variant="danger" size="sm">{{ __('Delete') }}</x-ui.button>
                                            </x-slot:trigger>
                                            <x-slot:footer>
                                                <div class="flex justify-end gap-3">
                                                    <x-ui.button type="button" variant="outline" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                                                    <x-ui.button type="button" variant="danger" wire:click="delete({{ $item->id }})" x-on:click="open = false">{{ __('Delete') }}</x-ui.button>
                                                </div>
                                            </x-slot:footer>
                                        </x-ui.dialog>
                                    </div>
                                </x-ui.table-cell>
                            </x-ui.table-row>
                        @empty
                            <x-ui.table-row>
                                <x-ui.table-cell colspan="3" class="py-10">
                                    <x-ui.empty :icon="$config['icon']" :title="__('No items found.')" :description="__('Try broadening the current filters or create a new item.')" />
                                </x-ui.table-cell>
                            </x-ui.table-row>
                        @endforelse
                    </x-ui.table-body>
                </x-ui.table>

                @if ($items->hasPages())
                    <div class="border-t px-6 py-4" style="border-color: var(--theme-border-color);">{{ $items->links() }}</div>
                @endif
                </x-ui.datatable-shell>
            </div>
        </div>
    </section>
</div>
