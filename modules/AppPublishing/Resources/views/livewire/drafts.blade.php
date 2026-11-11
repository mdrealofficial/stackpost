@php
    $publishingApprovalTeam = \Modules\AppTeams\Support\TeamWorkspaceAccess::activeTeam(auth()->user());
    $publishingCanApprovePosts = auth()->user()
        && $publishingApprovalTeam
        && \Modules\AppTeams\Support\TeamWorkspaceAccess::hasPermission(auth()->user(), 'post.approve', $publishingApprovalTeam);
@endphp

<div class="mx-auto flex w-full max-w-[1680px] flex-col gap-4 px-4 py-5 sm:px-5 xl:px-6">
    <x-ui.sub-header :compact="true" :title="__('Drafts')" :description="__('Finish your drafts and prepare your next post.')"><x-slot:actions>
                <x-ui.button :href="route('portal.publishing.calendar')" variant="outline" wire:navigate>
                    {{ __('Calendar') }}
                </x-ui.button>
                <x-ui.button :href="route('portal.publishing.queue')" variant="outline" wire:navigate>
                    {{ __('Queue') }}
                </x-ui.button>
                @if ($publishingCanApprovePosts)
                    <x-ui.button :href="route('portal.publishing.approvals')" variant="outline" wire:navigate>
                        {{ __('Approvals') }}
                    </x-ui.button>
                @endif
                <x-ui.button :href="route('portal.publishing.calendar', ['compose' => 1])" variant="primary" wire:navigate>
                    {{ __('New Post') }}
                </x-ui.button>
            </x-slot:actions></x-ui.sub-header>

    <x-ui.filter-panel :compact="true" fieldsClass="lg:items-end lg:grid-cols-[minmax(0,1fr)_320px]">
            <div class="min-w-0 flex-1">
                <p class="text-xs font-medium" style="color: var(--theme-muted-text-color);">{{ __('Draft filters') }}</p>
                <x-ui.icon-input
                    wire:model.live.debounce.300ms="search"
                    icon="fa-light fa-magnifying-glass"
                    :placeholder="__('Search by title, caption, or draft id...')"
                    wrapperClass="mt-2"
                   
                />
            </div>

            <div class="min-w-0">
                <p class="text-xs font-medium" style="color: var(--theme-muted-text-color);">{{ __('Network view') }}</p>

                <div class="mt-2 flex items-center gap-2">
                    <div class="min-w-0 flex-1">
                        <x-ui.select wire:model.live="providerFilter" :aria-label="__('Network')">
                            <option value="all">{{ __('All networks') }}</option>
                            @foreach ($providerFilters as $provider)
                                <option value="{{ $provider['key'] }}">{{ $provider['label'] }}</option>
                            @endforeach
                        </x-ui.select>
                    </div>
                    <x-ui.button type="button" variant="outline" wire:click="clearFilters">
                        <i class="fa-light fa-rotate-left mr-2"></i>
                        {{ __('Reset') }}
                    </x-ui.button>
                </div>
            </div>
        </x-ui.filter-panel>

    @php
        $draftMetrics = [
            ['label' => __('Draft queue'), 'value' => number_format($summary['total']), 'description' => __('Drafts ready for your next edit'), 'icon' => 'fa-light fa-file-lines', 'tone' => '#0d9488', 'iconSurface' => 'rgba(13,148,136,.12)', 'spark' => 'M2 24 C10 24 12 14 20 18 S28 29 36 20 S44 10 52 16 S58 18 62 12'],
            ['label' => __('Networks'), 'value' => number_format($summary['providers']), 'description' => __('Networks represented in drafts'), 'icon' => 'fa-light fa-globe', 'tone' => '#7c3aed', 'iconSurface' => 'rgba(124,58,237,.10)', 'spark' => 'M2 18 C10 18 12 26 20 22 S29 8 36 16 S44 26 52 15 S58 14 62 10'],
            ['label' => __('With media'), 'value' => number_format($summary['with_media']), 'description' => __('Drafts that include media'), 'icon' => 'fa-light fa-image', 'tone' => '#16856f', 'iconSurface' => 'rgba(22,133,111,.11)', 'spark' => 'M2 20 C10 20 12 27 20 22 S29 10 36 17 S44 26 52 16 S58 18 62 13'],
            ['label' => __('Pending review'), 'value' => number_format($summary['pending_review']), 'description' => __('Drafts awaiting approval'), 'icon' => 'fa-light fa-badge-check', 'tone' => '#b7791f', 'iconSurface' => 'rgba(183,121,31,.12)', 'spark' => 'M2 22 C10 22 12 18 20 20 S28 24 36 20 S44 18 52 21 S58 19 62 17'],
        ];
    @endphp
    <x-ui.metric-strip :items="$draftMetrics" columns="grid-cols-2 lg:grid-cols-4" gap="gap-4" variant="portal" :show-progress="false" aria-label="{{ __('Draft summary') }}" />

    <x-ui.card padding="sm">
        

        @if ($draftCards->isEmpty())
            <div class="flex min-h-[18rem] items-center justify-center rounded-[1.4rem] border border-dashed px-6 py-10 text-center" style="border-color: rgba(var(--theme-border-color-rgb), 0.58);">
                <div class="max-w-md">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-[1.15rem]" style="background: linear-gradient(135deg, rgba(var(--theme-accent-rgb), 0.12), rgba(var(--theme-accent-rgb), 0.04)); color: var(--theme-accent);">
                        <i class="fa-light fa-file-circle-plus text-xl"></i>
                    </div>
                    <p class="mt-4 text-lg font-semibold" style="color: var(--theme-header-text-color);">{{ __('No drafts found') }}</p>
                    <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Draft posts will appear here instead of the calendar once they are saved but not scheduled for delivery yet.') }}</p>
                </div>
            </div>
        @else
            <div class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
                @foreach ($draftCards as $draft)
                    @php
                        $hasMedia = filled($draft['media_preview']);
                        $providerChipStyle = publishing_provider_chip_style($draft['provider_key']);
                    @endphp

                    <x-ui.card padding="none" class="flex h-full flex-col overflow-hidden">
                        <div class="flex items-start justify-between gap-3 border-b px-4 py-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.52);">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-full text-xs" style="{{ $providerChipStyle }}">
                                        <i class="{{ $draft['provider_icon'] }}"></i>
                                    </span>
                                    <span class="rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.16em]" style="{{ $providerChipStyle }}">
                                        {{ $draft['provider_label'] }}
                                    </span>
                                    @if ($draft['review_status'] !== '')
                                        <span class="rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.16em]" style="{{ $draft['review_status'] === 'pending'
                                            ? 'background-color: rgba(245, 158, 11, 0.14); color: #d97706;'
                                            : ($draft['review_status'] === 'rejected'
                                                ? 'background-color: rgba(239, 68, 68, 0.12); color: #dc2626;'
                                                : 'background-color: rgba(16, 185, 129, 0.14); color: #059669;') }}">
                                            {{ $draft['review_badge'] ?: str((string) $draft['review_status'])->headline() }}
                                        </span>
                                    @endif
                                </div>
                                <p class="mt-3 text-sm font-semibold leading-6" style="color: var(--theme-header-text-color);">{{ $draft['channel'] }}</p>
                                @if ($draft['handle'])
                                    <p class="text-xs" style="color: var(--theme-muted-text-color);">{{ $draft['handle'] }}</p>
                                @endif
                            </div>

                            <div class="text-right text-[11px] leading-5" style="color: var(--theme-muted-text-color);">
                                <p>{{ __('Updated') }}</p>
                                <p class="font-semibold" style="color: var(--theme-header-text-color);">{{ optional($draft['updated_at'])->format('d/m/Y H:i') ?: '--' }}</p>
                            </div>
                        </div>

                        <div class="flex flex-1 flex-col gap-4 px-4 py-4">
                            <div class="flex items-start gap-4">
                                <div class="min-w-0 flex-1">
                                    @if ($draft['caption'] !== '')
                                        <p class="text-sm leading-6" style="color: var(--theme-header-text-color); display: -webkit-box; -webkit-line-clamp: 5; -webkit-box-orient: vertical; overflow: hidden;">
                                            {{ $draft['caption'] }}
                                        </p>
                                    @elseif ($draft['title'])
                                        <p class="text-base font-semibold leading-7" style="color: var(--theme-header-text-color); display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
                                            {{ $draft['title'] }}
                                        </p>
                                    @else
                                        <p class="text-sm italic leading-6" style="color: var(--theme-muted-text-color);">
                                            {{ __('This draft includes media or publishing settings, but no written caption yet.') }}
                                        </p>
                                    @endif
                                </div>

                                @if ($hasMedia)
                                    <div class="h-[5.75rem] w-[5.75rem] shrink-0 overflow-hidden rounded-[1rem] border" style="border-color: rgba(var(--theme-border-color-rgb), 0.58);">
                                        <img src="{{ $draft['media_preview'] }}" alt="{{ $draft['title'] ?: $draft['channel'] }}" class="h-full w-full object-cover" loading="lazy" decoding="async">
                                    </div>
                                @endif
                            </div>

                            <div class="grid gap-3 sm:grid-cols-3">
                                <div class="rounded-[1rem] border px-3 py-3" style="border-color: rgba(var(--theme-border-color-rgb), 0.52);">
                                    <p class="text-[10px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Media') }}</p>
                                    <p class="mt-2 text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $draft['media_type'] }}</p>
                                    <p class="mt-1 text-xs" style="color: var(--theme-muted-text-color);">{{ trans_choice(':count file|:count files', $draft['media_count'], ['count' => $draft['media_count']]) }}</p>
                                </div>
                                <div class="rounded-[1rem] border px-3 py-3" style="border-color: rgba(var(--theme-border-color-rgb), 0.52);">
                                    <p class="text-[10px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Schedule') }}</p>
                                    <p class="mt-2 text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $draft['scheduled_at'] ? $draft['scheduled_at']->format('d/m/Y H:i') : __('Not scheduled') }}</p>
                                </div>
                                <div class="rounded-[1rem] border px-3 py-3" style="border-color: rgba(var(--theme-border-color-rgb), 0.52);">
                                    <p class="text-[10px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Draft id') }}</p>
                                    <p class="mt-2 truncate text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $draft['id'] }}</p>
                                </div>
                            </div>

                            @if ($draft['review_status'] !== '')
                                <div class="rounded-[1rem] border px-3 py-3" style="border-color: rgba(var(--theme-border-color-rgb), 0.52); background-color: rgba(var(--theme-border-color-rgb), 0.03);">
                                    <p class="text-[10px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Approval audit') }}</p>
                                    @if ($draft['review_submitted_at'])
                                        <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">
                                            {{ __('Submitted') }}
                                            @if ($draft['review_submitted_by'] !== '')
                                                {{ __('by :name', ['name' => $draft['review_submitted_by']]) }}
                                            @endif
                                            {{ __('on :date', ['date' => $draft['review_submitted_at']->format('d/m/Y H:i')]) }}
                                        </p>
                                    @endif
                                    @if ($draft['review_decided_at'])
                                        <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">
                                            {{ __('Decided') }}
                                            @if ($draft['review_decided_by'] !== '')
                                                {{ __('by :name', ['name' => $draft['review_decided_by']]) }}
                                            @endif
                                            {{ __('on :date', ['date' => $draft['review_decided_at']->format('d/m/Y H:i')]) }}
                                        </p>
                                    @endif
                                    @if ($draft['review_note'] !== '')
                                        <p class="mt-2 text-sm leading-6" style="color: var(--theme-header-text-color);">{{ $draft['review_note'] }}</p>
                                    @endif
                                </div>
                            @endif

                            <div class="mt-auto flex flex-wrap gap-2 pt-1">
                                <x-ui.button :href="route('portal.publishing.calendar', ['edit' => $draft['id']])" variant="primary" size="md" class="flex-1 min-w-[11rem]" wire:navigate>
                                    <i class="fa-light fa-pen-to-square"></i>
                                    {{ __('Edit Draft') }}
                                </x-ui.button>
                                <x-ui.button
                                    type="button"
                                   
                                   
                                    x-data
                                    :wire:click="'deleteDraft('.\Illuminate\Support\Js::from((string) $draft['id']).')'" wire:confirm="{{ __('Delete this draft?') }}"
                                 variant="outline" size="sm">
                                    <i class="fa-light fa-trash-can"></i>
                                    {{ __('Delete') }}
                                </x-ui.button>
                            </div>
                        </div>
                    </x-ui.card>
                @endforeach
            </div>

            <div class="mt-5">
                {{ $drafts->links() }}
            </div>
        @endif
    </x-ui.card>
</div>
