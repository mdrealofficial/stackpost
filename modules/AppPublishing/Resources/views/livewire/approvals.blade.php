<div class="mx-auto flex w-full max-w-[1680px] flex-col gap-4 px-4 py-5 sm:px-5 xl:px-6">
    <x-ui.sub-header :compact="true" :title="__('Approvals')" :description="__('Review team posts before publishing.')"><x-slot:actions>
                <x-ui.button :href="route('portal.publishing.calendar')" variant="outline" wire:navigate>
                    {{ __('Calendar') }}
                </x-ui.button>
                <x-ui.button :href="route('portal.publishing.queue')" variant="outline" wire:navigate>
                    {{ __('Queue') }}
                </x-ui.button>
                <x-ui.button :href="route('portal.publishing.drafts')" variant="outline" wire:navigate>
                    {{ __('Drafts') }}
                </x-ui.button>
            </x-slot:actions></x-ui.sub-header>

    <x-ui.filter-panel :compact="true" fieldsClass="lg:items-end lg:grid-cols-[minmax(0,1fr)_200px_auto]">
            <div class="min-w-0">
                <p class="text-xs font-medium" style="color: var(--theme-muted-text-color);">{{ __('Approval search') }}</p>
                <x-ui.icon-input
                    wire:model.live.debounce.300ms="search"
                    icon="fa-light fa-magnifying-glass"
                    :placeholder="__('Search by title, caption, or post id...')"
                    wrapperClass="mt-2"
                   
                />
            </div>

            <div class="min-w-0">
                <p class="text-xs font-medium" style="color: var(--theme-muted-text-color);">{{ __('Network') }}</p>
                <div class="mt-2">
                    <x-ui.select wire:model.live="providerFilter" :aria-label="__('Network')">
                        <option value="all">{{ __('All networks') }}</option>
                        @foreach ($providerFilters as $provider)
                            <option value="{{ $provider['key'] }}">{{ $provider['label'] }}</option>
                        @endforeach
                    </x-ui.select>
                </div>
            </div>

            <x-ui.button type="button" variant="outline" wire:click="clearFilters">
                <i class="fa-light fa-rotate-left mr-2"></i>
                {{ __('Reset') }}
            </x-ui.button>
        </x-ui.filter-panel>

    @php
        $approvalMetrics = [
            ['label' => __('Pending approvals'), 'value' => number_format($summary['pending']), 'description' => __('Posts waiting for review'), 'icon' => 'fa-light fa-badge-check', 'tone' => '#b7791f', 'iconSurface' => 'rgba(183,121,31,.12)', 'spark' => 'M2 24 C10 24 12 15 20 19 S28 28 36 20 S44 11 52 17 S58 18 62 12'],
            ['label' => __('Networks'), 'value' => number_format($summary['providers']), 'description' => __('Connected networks in this view'), 'icon' => 'fa-light fa-globe', 'tone' => '#0d9488', 'iconSurface' => 'rgba(13,148,136,.12)', 'spark' => 'M2 24 C10 24 12 14 20 18 S28 29 36 20 S44 10 52 16 S58 18 62 12'],
            ['label' => __('Submitted today'), 'value' => number_format($summary['submitted_today']), 'description' => __('New review requests today'), 'icon' => 'fa-light fa-paper-plane', 'tone' => '#16856f', 'iconSurface' => 'rgba(22,133,111,.11)', 'spark' => 'M2 20 C10 20 12 26 20 22 S29 10 36 17 S44 25 52 16 S58 17 62 13'],
            ['label' => __('Oldest pending'), 'value' => $summary['oldest_pending'] ?: '—', 'description' => __('Age of the oldest review item'), 'icon' => 'fa-light fa-hourglass-half', 'tone' => '#7c3aed', 'iconSurface' => 'rgba(124,58,237,.10)', 'spark' => 'M2 18 C10 18 13 20 20 20 S29 18 36 20 S45 18 52 20 S58 18 62 18'],
        ];
    @endphp
    <x-ui.metric-strip :items="$approvalMetrics" columns="grid-cols-2 lg:grid-cols-4" gap="gap-4" variant="portal" :show-progress="false" aria-label="{{ __('Approval summary') }}" />

    <x-ui.card padding="sm">
        @if ($reviewCards->isEmpty())
            <div class="flex min-h-[18rem] items-center justify-center rounded-[1.4rem] border border-dashed px-6 py-10 text-center" style="border-color: rgba(var(--theme-border-color-rgb), 0.58);">
                <div class="max-w-md">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-[1.15rem]" style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.12), rgba(245, 158, 11, 0.04)); color: #d97706;">
                        <i class="fa-light fa-badge-check text-xl"></i>
                    </div>
                    <p class="mt-4 text-lg font-semibold" style="color: var(--theme-header-text-color);">{{ __('Approval inbox is clear') }}</p>
                    <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('New publishing items that require approval will appear here once teammates submit them.') }}</p>
                </div>
            </div>
        @else
            <div class="grid gap-4 xl:grid-cols-3">
                @foreach ($reviewCards as $review)
                    @php($providerChipStyle = publishing_provider_chip_style($review['provider_key']))
                    <x-ui.card padding="none" x-data="{ expanded: false }" class="overflow-hidden">
                        <div class="relative border-b" style="border-color: rgba(var(--theme-border-color-rgb), 0.52);">
                            <div class="absolute inset-x-0 top-0 z-10 flex items-start gap-3 p-4">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-full text-xs shadow-sm" style="{{ $providerChipStyle }}">
                                        <i class="{{ $review['provider_icon'] }}"></i>
                                    </span>
                                    <span class="rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.16em] shadow-sm" style="{{ $providerChipStyle }}">
                                        {{ $review['provider_label'] }}
                                    </span>
                                    <span class="rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.16em] shadow-sm" style="background-color: rgba(245, 158, 11, 0.14); color: #d97706;">
                                        {{ __('Pending approval') }}
                                    </span>
                                </div>
                            </div>

                            @if ($review['media_preview'])
                                <div class="aspect-[16/10] overflow-hidden" style="background-color: rgba(var(--theme-border-color-rgb), 0.08);">
                                    @if (str_starts_with(strtolower($review['media_mime']), 'video/'))
                                        <video src="{{ $review['media_preview'] }}" muted playsinline preload="metadata" class="h-full w-full object-cover"></video>
                                    @else
                                        <img src="{{ $review['media_preview'] }}" alt="{{ $review['title'] ?: $review['channel'] }}" class="h-full w-full object-cover" loading="lazy" decoding="async">
                                    @endif
                                </div>
                            @else
                                @php($placeholderIcon = ($review['caption'] !== '' || $review['title']) ? 'fa-light fa-align-center' : 'fa-light fa-image')
                                <div class="flex aspect-[16/10] items-center justify-center p-5" style="background:
                                    linear-gradient(180deg, rgba(var(--theme-accent-rgb), 0.04), rgba(var(--theme-border-color-rgb), 0.02)),
                                    color-mix(in srgb, var(--theme-surface-base) 98%, transparent);">
                                    <span class="inline-flex h-20 w-20 items-center justify-center rounded-full" style="background-color: rgba(var(--theme-accent-rgb), 0.1); color: var(--theme-accent);">
                                        <i class="{{ $placeholderIcon }} text-[2.5rem]"></i>
                                    </span>
                                </div>
                            @endif
                        </div>

                        <div class="space-y-4 p-4">
                            <div>
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-base font-semibold leading-6" style="color: var(--theme-header-text-color);">{{ $review['channel'] }}</p>
                                        <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px]" style="color: var(--theme-muted-text-color);">
                                            @if ($review['handle'])
                                                <span>{{ $review['handle'] }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    @if ($review['caption'] !== '')
                                        <div
                                            class="whitespace-pre-line text-sm leading-6"
                                            style="color: var(--theme-header-text-color);"
                                            x-bind:class="expanded ? '' : 'max-h-[9rem] overflow-hidden'"
                                        >
                                            {{ $review['caption'] }}
                                        </div>
                                        @if (mb_strlen($review['caption']) > 220)
                                            <x-ui.button
                                                type="button"
                                               
                                               
                                                x-on:click="expanded = !expanded"
                                                x-text="expanded ? @js(__('Show less')) : @js(__('Show more'))"
                                             variant="outline" size="sm"></x-ui.button>
                                        @endif
                                    @elseif ($review['title'])
                                        <p class="text-sm leading-6" style="color: var(--theme-header-text-color);">
                                            {{ $review['title'] }}
                                        </p>
                                    @else
                                        <p class="text-sm italic leading-6" style="color: var(--theme-muted-text-color);">
                                            {{ __('This approval request includes media and publishing settings, but no written caption yet.') }}
                                        </p>
                                    @endif
                                </div>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="rounded-[1rem] border px-3 py-3" style="border-color: rgba(var(--theme-border-color-rgb), 0.52);">
                                    <p class="text-[10px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('When approved') }}</p>
                                    <p class="mt-2 text-sm font-semibold" style="color: var(--theme-header-text-color);">
                                        {{ $review['schedule_mode'] === 'immediately' ? __('Publish immediately') : __('Return to schedule') }}
                                    </p>
                                </div>
                                <div class="rounded-[1rem] border px-3 py-3" style="border-color: rgba(var(--theme-border-color-rgb), 0.52);">
                                    <p class="text-[10px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Scheduled time') }}</p>
                                    <p class="mt-2 text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $review['scheduled_at'] ? $review['scheduled_at']->format('d/m/Y H:i') : __('Not scheduled') }}</p>
                                </div>
                            </div>

                            @if ($review['internal_note'] !== '')
                                <div class="rounded-[1rem] border px-3 py-3" style="border-color: rgba(var(--theme-border-color-rgb), 0.52); background-color: rgba(var(--theme-border-color-rgb), 0.03);">
                                    <p class="text-[10px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Internal note') }}</p>
                                    <p class="mt-2 text-sm leading-6" style="color: var(--theme-header-text-color);">{{ $review['internal_note'] }}</p>
                                </div>
                            @endif

                            <x-ui.textarea
                                wire:model.defer="reviewNotes.{{ $review['review_id'] }}"
                                :label="__('Decision note')"
                                rows="3"
                                :placeholder="__('Explain why this post is approved or what needs to change before it can be published.')"
                            ></x-ui.textarea>

                            <div class="space-y-2 pt-1">
                                @if (! $review['can_decide'])
                                    <div class="rounded-[0.95rem] border px-3 py-2 text-xs leading-5" style="border-color: rgba(245, 158, 11, 0.24); background-color: rgba(245, 158, 11, 0.08); color: #a16207;">
                                        {{ __('This workflow review requires both post approval and workflow approval permission.') }}
                                    </div>
                                @endif
                                <x-ui.button :href="route('portal.publishing.calendar', ['edit' => $review['post_id']])" variant="outline" size="md" class="flex w-full justify-center [&_i]:!text-current" wire:navigate>
                                    <i class="fa-light fa-pen-to-square"></i>
                                    {{ __('Open in composer') }}
                                </x-ui.button>
                                <div class="grid grid-cols-2 gap-2">
                                    <x-ui.button type="button" variant="primary" size="md" class="flex w-full justify-center [&_i]:!text-current disabled:cursor-not-allowed disabled:opacity-50" wire:click="approveReview({{ $review['review_id'] }})" :disabled="! $review['can_decide']">
                                        <i class="fa-light fa-badge-check"></i>
                                        {{ __('Approve') }}
                                    </x-ui.button>
                                    <x-ui.button
                                        type="button"
                                       
                                       
                                        wire:click="rejectReview({{ $review['review_id'] }})"
                                        :disabled="! $review['can_decide']"
                                     variant="outline" size="sm">
                                        <i class="fa-light fa-ban" style="color: currentColor;"></i>
                                        {{ __('Reject') }}
                                    </x-ui.button>
                                </div>
                            </div>
                        </div>
                    </x-ui.card>
                @endforeach
            </div>

            <div class="mt-5">
                {{ $reviews->links() }}
            </div>
        @endif
    </x-ui.card>
</div>
