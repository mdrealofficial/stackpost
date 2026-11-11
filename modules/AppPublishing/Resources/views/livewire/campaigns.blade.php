<div class="mx-auto w-full max-w-[1680px] space-y-4 px-4 pb-6 pt-5 sm:px-5 xl:px-6">
    <x-ui.sub-header :compact="true" :title="__('Campaigns')" :description="__('Organize posts around a shared goal and timeline.')"><x-slot:actions>
            <x-ui.button :href="route('portal.publishing.calendar')" variant="outline" wire:navigate>{{ __('Calendar') }}</x-ui.button>
            <x-ui.button type="button" wire:click="createNew">{{ __('New Campaign') }}</x-ui.button>
        </x-slot:actions></x-ui.sub-header>

    <div class="grid gap-4 xl:grid-cols-[24rem_minmax(0,1fr)]">
        <x-ui.card padding="none">
            <div class="border-b px-5 py-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68);">
                <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $editingId ? __('Edit Campaign') : __('Create Campaign') }}</p>
            </div>

            <div class="space-y-4 px-5 py-5">
                <x-ui.input wire:model.defer="form.name" :label="__('Campaign name')" :error="$errors->first('form.name')" :placeholder="__('Holiday Launch 2026')" />

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

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.date-picker
                        wire:model.defer="form.starts_on"
                        name="starts_on"
                        :label="__('Starts on')"
                        :value="$form['starts_on'] ?? ''"
                        :error="$errors->first('form.starts_on')"
                        :placeholder="__('Start date')"
                    />
                    <x-ui.date-picker
                        wire:model.defer="form.ends_on"
                        name="ends_on"
                        :label="__('Ends on')"
                        :value="$form['ends_on'] ?? ''"
                        :error="$errors->first('form.ends_on')"
                        :placeholder="__('End date')"
                    />
                </div>

                <x-ui.textarea wire:model.defer="form.description" :label="__('Description')" :error="$errors->first('form.description')" rows="4" placeholder="{{ __('Campaign objective, audience, or rollout framing...') }}">{{ $form['description'] ?? '' }}</x-ui.textarea>

                <x-ui.textarea wire:model.defer="form.notes" :label="__('Internal notes')" :error="$errors->first('form.notes')" rows="4" placeholder="{{ __('Operational reminders, approvals, dependencies, or scheduling context...') }}">{{ $form['notes'] ?? '' }}</x-ui.textarea>

                <div class="flex items-center gap-3 pt-2">
                    <x-ui.button type="button" wire:click="save">{{ $editingId ? __('Update Campaign') : __('Create Campaign') }}</x-ui.button>
                    <x-ui.button type="button" variant="outline" wire:click="createNew">{{ __('Reset') }}</x-ui.button>
                </div>
            </div>
        </x-ui.card>

        <section class="space-y-4">
            @php
                $campaignMetrics = [
                    ['label' => __('Total'), 'value' => number_format($summary['total']), 'description' => __('Campaigns in this workspace'), 'icon' => 'fa-light fa-bullseye', 'tone' => '#0d9488', 'iconSurface' => 'rgba(13,148,136,.12)', 'spark' => 'M2 24 C10 24 12 14 20 18 S28 29 36 20 S44 10 52 16 S58 18 62 12'],
                    ['label' => __('Active'), 'value' => number_format($summary['active']), 'description' => __('Campaigns currently running'), 'icon' => 'fa-light fa-circle-play', 'tone' => '#16856f', 'iconSurface' => 'rgba(22,133,111,.11)', 'spark' => 'M2 20 C10 20 12 26 20 22 S29 10 36 17 S44 25 52 16 S58 17 62 13'],
                    ['label' => __('Draft'), 'value' => number_format($summary['draft']), 'description' => __('Campaigns still being prepared'), 'icon' => 'fa-light fa-pen-line', 'tone' => '#7c3aed', 'iconSurface' => 'rgba(124,58,237,.10)', 'spark' => 'M2 18 C10 18 13 20 20 20 S29 18 36 20 S45 18 52 20 S58 18 62 18'],
                    ['label' => __('Completed'), 'value' => number_format($summary['completed']), 'description' => __('Campaigns that reached their end'), 'icon' => 'fa-light fa-circle-check', 'tone' => '#b7791f', 'iconSurface' => 'rgba(183,121,31,.12)', 'spark' => 'M2 22 C10 22 12 18 20 20 S28 24 36 20 S44 18 52 21 S58 19 62 17'],
                ];
            @endphp
            <x-ui.metric-strip :items="$campaignMetrics" columns="grid-cols-2 lg:grid-cols-4" gap="gap-4" variant="portal" :show-progress="false" aria-label="{{ __('Campaign summary') }}" />

            <x-ui.card padding="none">
                <div class="flex flex-col gap-3 border-b px-5 py-4 lg:flex-row lg:items-center lg:justify-between" style="border-color: rgba(var(--theme-border-color-rgb), 0.68);">
                    <div>
                        <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Campaigns') }}</p>
                        <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Find a campaign or update its details.') }}</p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-ui.input wire:model.live.debounce.300ms="search" :placeholder="__('Search campaigns...')" />
                        <x-ui.select wire:model.live="statusFilter" :aria-label="__('Filter status')">
                            <option value="all">{{ __('All statuses') }}</option>
                            @foreach ($statusOptions as $option)
                                <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </x-ui.select>
                    </div>
                </div>

                <div class="divide-y" style="divide-color: rgba(var(--theme-border-color-rgb), 0.68);">
                    @forelse ($campaigns as $campaign)
                        <div class="grid gap-4 px-5 py-4 2xl:grid-cols-[minmax(0,1fr)_90px_140px_auto] lg:items-center">
                            <div class="min-w-0">
                                <div class="flex items-center gap-3">
                                    <span class="inline-flex h-3.5 w-3.5 shrink-0 rounded-full" style="background-color: {{ $campaign->color }};"></span>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $campaign->name }}</p>
                                        <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ $campaign->description ?: __('No campaign description yet.') }}</p>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Status') }}</p>
                                <p class="mt-2 text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ str($campaign->status)->headline() }}</p>
                            </div>

                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Timeline') }}</p>
                                <p class="mt-2 text-sm" style="color: var(--theme-header-text-color);">
                                    {{ $campaign->starts_on?->format('M j, Y') ?: '—' }} to {{ $campaign->ends_on?->format('M j, Y') ?: '—' }}
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                                <x-ui.button type="button" size="sm" variant="outline" wire:click="edit({{ $campaign->id }})">{{ __('Edit') }}</x-ui.button>
                                <x-ui.button type="button" size="sm" variant="secondary" wire:click="duplicate({{ $campaign->id }})">{{ __('Duplicate') }}</x-ui.button>
                                <x-ui.button type="button" size="sm" variant="outline" wire:click="delete({{ $campaign->id }})">{{ __('Delete') }}</x-ui.button>
                            </div>
                        </div>
                    @empty
                        <div class="px-5 py-8">
                            <x-ui.empty
                                :icon="'fa-light fa-bullhorn'"
                                :title="__('No campaigns found')"
                                :description="__('Try another search or status, or create a campaign.')"
                            >
                                <x-ui.button type="button" size="sm" wire:click="createNew">
                                    <i class="fa-light fa-plus"></i>
                                    {{ __('Create Campaign') }}
                                </x-ui.button>
                            </x-ui.empty>
                        </div>
                    @endforelse
                </div>
            </x-ui.card>
        </section>
    </div>
</div>
