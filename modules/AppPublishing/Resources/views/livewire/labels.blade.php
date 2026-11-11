<div class="mx-auto w-full max-w-[1680px] space-y-4 px-4 pb-6 pt-5 sm:px-5 xl:px-6">
    <x-ui.sub-header :compact="true" :title="__('Labels')" :description="__('Organize and find content with reusable labels.')"><x-slot:actions>
            <x-ui.button :href="route('portal.publishing.calendar')" variant="outline" wire:navigate>{{ __('Calendar') }}</x-ui.button>
            <x-ui.button type="button" wire:click="createNew">{{ __('New Label') }}</x-ui.button>
        </x-slot:actions></x-ui.sub-header>

    <div class="grid gap-4 xl:grid-cols-[24rem_minmax(0,1fr)]">
        <x-ui.card padding="none">
            <div class="border-b px-5 py-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68);">
                <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $editingId ? __('Edit Label') : __('Create Label') }}</p>
            </div>

            <div class="space-y-4 px-5 py-5">
                <x-ui.input wire:model.defer="form.name" :label="__('Label name')" :error="$errors->first('form.name')" :placeholder="__('Launch')" />

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <x-ui.label>{{ __('Status') }}</x-ui.label>
                        <x-ui.select wire:model.defer="form.status" :aria-label="__('Status')">
                            @foreach ($statusOptions as $option)
                                <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </x-ui.select>
                    </div>

                    <x-ui.color-picker wire:model.defer="form.color" :label="__('Accent color')" :value="$form['color'] ?? '#2563eb'" :error="$errors->first('form.color')" />
                </div>

                <x-ui.textarea wire:model.defer="form.description" :label="__('Description')" :error="$errors->first('form.description')" rows="4" placeholder="{{ __('What this label should be used for across the publishing workflow...') }}">{{ $form['description'] ?? '' }}</x-ui.textarea>

                <div class="flex items-center gap-3 pt-2">
                    <x-ui.button type="button" wire:click="save">{{ $editingId ? __('Update Label') : __('Create Label') }}</x-ui.button>
                    <x-ui.button type="button" variant="outline" wire:click="createNew">{{ __('Reset') }}</x-ui.button>
                </div>
            </div>
        </x-ui.card>

        <section class="space-y-4">
            @php
                $labelMetrics = [
                    ['label' => __('Total'), 'value' => number_format($summary['total']), 'description' => __('Reusable labels in this workspace'), 'icon' => 'fa-light fa-tags', 'tone' => '#0d9488', 'iconSurface' => 'rgba(13,148,136,.12)', 'spark' => 'M2 24 C10 24 12 14 20 18 S28 29 36 20 S44 10 52 16 S58 18 62 12'],
                    ['label' => __('Active'), 'value' => number_format($summary['active']), 'description' => __('Labels available in the composer'), 'icon' => 'fa-light fa-circle-check', 'tone' => '#16856f', 'iconSurface' => 'rgba(22,133,111,.11)', 'spark' => 'M2 20 C10 20 12 26 20 22 S29 10 36 17 S44 25 52 16 S58 17 62 13'],
                    ['label' => __('Draft'), 'value' => number_format($summary['draft']), 'description' => __('Labels still being prepared'), 'icon' => 'fa-light fa-pen-line', 'tone' => '#7c3aed', 'iconSurface' => 'rgba(124,58,237,.10)', 'spark' => 'M2 18 C10 18 13 20 20 20 S29 18 36 20 S45 18 52 20 S58 18 62 18'],
                ];
            @endphp
            <x-ui.metric-strip :items="$labelMetrics" columns="grid-cols-2 lg:grid-cols-3" gap="gap-4" variant="portal" :show-progress="false" aria-label="{{ __('Label summary') }}" />

            <x-ui.card padding="none">
                <div class="flex flex-col gap-3 border-b px-5 py-4 lg:flex-row lg:items-center lg:justify-between" style="border-color: rgba(var(--theme-border-color-rgb), 0.68);">
                    <div>
                        <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Labels') }}</p>
                        <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Manage reusable labels for composer organization and reporting.') }}</p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-ui.input wire:model.live.debounce.300ms="search" :placeholder="__('Search labels...')" />
                        <x-ui.select wire:model.live="statusFilter" :aria-label="__('Filter status')">
                            <option value="all">{{ __('All statuses') }}</option>
                            @foreach ($statusOptions as $option)
                                <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </x-ui.select>
                    </div>
                </div>

                <div class="divide-y" style="divide-color: rgba(var(--theme-border-color-rgb), 0.68);">
                    @forelse ($labels as $label)
                        <div class="grid gap-4 px-5 py-4 lg:grid-cols-[minmax(0,1fr)_100px_auto] lg:items-center">
                            <div class="min-w-0">
                                <div class="flex items-center gap-3">
                                    <span class="inline-flex h-3.5 w-3.5 shrink-0 rounded-full" style="background-color: {{ $label->color }};"></span>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $label->name }}</p>
                                        <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ $label->description ?: __('No label description yet.') }}</p>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Status') }}</p>
                                <p class="mt-2 text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ str($label->status)->headline() }}</p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                                <x-ui.button type="button" size="sm" variant="outline" wire:click="edit({{ $label->id }})">{{ __('Edit') }}</x-ui.button>
                                <x-ui.button type="button" size="sm" variant="secondary" wire:click="duplicate({{ $label->id }})">{{ __('Duplicate') }}</x-ui.button>
                                <x-ui.button type="button" size="sm" variant="outline" wire:click="delete({{ $label->id }})">{{ __('Delete') }}</x-ui.button>
                            </div>
                        </div>
                    @empty
                        <div class="px-5 py-8">
                            <x-ui.empty
                                :icon="'fa-light fa-tags'"
                                :title="__('No labels found')"
                                :description="__('Try another search or status, or create a label.')"
                            >
                                <x-ui.button type="button" size="sm" wire:click="createNew">
                                    <i class="fa-light fa-plus"></i>
                                    {{ __('Create Label') }}
                                </x-ui.button>
                            </x-ui.empty>
                        </div>
                    @endforelse
                </div>
            </x-ui.card>
        </section>
    </div>
</div>
