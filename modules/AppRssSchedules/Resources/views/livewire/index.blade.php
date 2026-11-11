<div class="rss-workspace schedule-index">
    @include('apprssschedules::partials.workspace-styles')
    <x-shared::schedule-workspace-styles />
    <x-ui.sub-header compact :title="__('RSS Schedules')" :description="__('Turn feed updates into drafts or scheduled posts.')"><x-slot:actions><x-ui.button :href="route('portal.publishing.calendar')" variant="outline" wire:navigate><i class="fa-light fa-calendar-days" aria-hidden="true"></i>{{ __('Open Publishing') }}</x-ui.button><x-ui.button :href="route('portal.rss-schedules.create')" wire:navigate><i class="fa-light fa-plus" aria-hidden="true"></i>{{ __('Add feed') }}</x-ui.button></x-slot:actions></x-ui.sub-header>
    @if(session('status'))<div class="schedule-note" role="status">{{ session('status') }}</div>@endif
    @php
        $rssScheduleMetrics = [
            ['label' => __('Schedules'), 'value' => number_format($summary['total']), 'description' => __('Configured RSS schedules'), 'icon' => 'fa-light fa-layer-group', 'tone' => '#0d9488', 'iconSurface' => 'rgba(13,148,136,.12)', 'spark' => 'M2 24 C10 24 12 14 20 18 S28 29 36 20 S44 10 52 16 S58 18 62 12'],
            ['label' => __('Enabled'), 'value' => number_format($summary['active']), 'description' => __('Feeds checked on their rhythm'), 'icon' => 'fa-light fa-circle-play', 'tone' => '#16856f', 'iconSurface' => 'rgba(22,133,111,.11)', 'spark' => 'M2 20 C10 20 12 26 20 22 S29 10 36 17 S44 25 52 16 S58 17 62 13'],
            ['label' => __('Paused'), 'value' => number_format($summary['paused']), 'description' => __('Temporarily stopped'), 'icon' => 'fa-light fa-circle-pause', 'tone' => '#64748b', 'iconSurface' => 'rgba(100,116,139,.12)', 'spark' => 'M2 18 C10 18 13 20 20 20 S29 18 36 20 S45 18 52 20 S58 18 62 18'],
            ['label' => __('Expired'), 'value' => number_format($summary['expired']), 'description' => __('Feeds past their end date'), 'icon' => 'fa-light fa-calendar-xmark', 'tone' => '#b7791f', 'iconSurface' => 'rgba(183,121,31,.12)', 'spark' => 'M2 22 C10 22 12 18 20 20 S28 24 36 20 S44 18 52 21 S58 19 62 17'],
            ['label' => __('Samples'), 'value' => number_format($summary['samples']), 'description' => __('Preview runs kept for testing'), 'icon' => 'fa-light fa-flask', 'tone' => '#7c3aed', 'iconSurface' => 'rgba(124,58,237,.10)', 'spark' => 'M2 24 C10 24 12 15 20 18 S28 28 36 19 S44 11 52 16 S58 18 62 12'],
            ['label' => __('Queued posts'), 'value' => number_format($summary['queued']), 'description' => __('Posts waiting to publish'), 'icon' => 'fa-light fa-paper-plane', 'tone' => '#16856f', 'iconSurface' => 'rgba(22,133,111,.11)', 'spark' => 'M2 21 C10 21 12 27 20 22 S29 10 36 17 S44 26 52 16 S58 18 62 13'],
        ];
    @endphp
    <x-ui.metric-strip :items="$rssScheduleMetrics" columns="grid-cols-2 md:grid-cols-3" gap="gap-4" variant="portal" :show-progress="false" aria-label="{{ __('Schedule overview') }}" />
    <div class="schedule-collection-heading"><div><h2>{{ __('Your feeds') }}</h2><p>{{ __('Choose a source, set its rhythm, and follow every post.') }}</p></div><span><i class="fa-light fa-globe" aria-hidden="true"></i>{{ $timezone }}</span></div>
    <div class="schedule-toolbar">
        <x-ui.input wire:model.live.debounce.300ms="search" :aria-label="__('Search RSS schedules')" :placeholder="__('Search by name or feed URL...')" />
        <x-ui.select wire:model.live="status" :aria-label="__('Schedule status')">@foreach($statusOptions as $option)<option value="{{ $option['value'] }}">{{ $option['label'] }}</option>@endforeach</x-ui.select>
        @if($search !== '' || $status !== 'all')<x-ui.button type="button" wire:click="resetFilters" variant="outline">{{ __('Clear filters') }}</x-ui.button>@endif
        <span class="schedule-result-count" role="status">{{ __(':count results', ['count' => $schedules->total()]) }}</span>
    </div>
    <div class="schedule-list">
    @forelse($schedules as $schedule)
        @php
            $badge = $this->statusBadge($schedule);
            $settings = (array) $schedule->settings;
            $counts = $publishingCounts[$schedule->id] ?? ['published' => 0, 'failed' => 0];
            $format = fn ($value) => $value ? \Carbon\Carbon::createFromTimestamp((int) $value, $timezone)->format('d M Y, H:i') : __('Not yet');
            $isSample = $schedule->isDemo();
            $isExpired = !$isSample && $schedule->end_at && $schedule->end_at < now()->timestamp;
            $tone = $isExpired ? 'warning' : $badge['tone'];
            $days = array_keys(array_filter((array) $schedule->weekdays));
            $times = (array) $schedule->time_posts;
            $modeIsDraft = ($settings['delivery_mode'] ?? 'automatic') === 'draft';
        @endphp
        <x-ui.card padding="none" class="schedule-feed-card" wire:key="rss-row-{{ $schedule->id }}" data-tone="{{ $tone }}">
            <div class="schedule-feed-top">
                <span class="schedule-feed-icon"><i class="fa-light {{ $isSample ? 'fa-flask' : 'fa-rss' }}" aria-hidden="true"></i></span>
                <div class="schedule-feed-title"><h2><a href="{{ route('portal.rss-schedules.edit', $schedule) }}" wire:navigate>{{ $schedule->name }}</a></h2><p>{{ parse_url($schedule->feed_url, PHP_URL_HOST) }}</p></div>
                <span class="schedule-mode"><i class="fa-light {{ $modeIsDraft ? 'fa-file-pen' : 'fa-bolt' }}" aria-hidden="true"></i>{{ $modeIsDraft ? __('Create drafts') : __('Auto publish') }}</span>
                <span class="schedule-feed-status" data-tone="{{ $tone }}"><span aria-hidden="true"></span>{{ $badge['label'] }}</span>
            </div>
            <div class="schedule-feed-body">
                <section class="schedule-destination"><h3><i class="fa-light fa-share-nodes" aria-hidden="true"></i>{{ __('Destination channels') }}</h3><div class="schedule-channel-total"><strong>{{ $schedule->eligible_accounts_count }}</strong><span>{{ __('of :total active', ['total' => count((array) $schedule->account_ids)]) }}</span></div><p>{{ $schedule->eligible_accounts_count ? __('Connected and available') : __('Connect a channel to publish') }}</p></section>
                <section class="schedule-cadence"><h3><i class="fa-light fa-calendar-days" aria-hidden="true"></i>{{ __('Publishing rhythm') }}</h3><div class="schedule-weekdays">@foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $day)<span class="{{ in_array($day, $days, true) ? 'is-selected' : '' }}" aria-label="{{ __($day) }}: {{ in_array($day, $days, true) ? __('Scheduled') : __('Not scheduled') }}">{{ __($day) }}</span>@endforeach</div><div class="schedule-time-slots">@forelse($times as $time)<span><i class="fa-light fa-clock" aria-hidden="true"></i>{{ $time }}</span>@empty<span>{{ __('No time selected') }}</span>@endforelse</div></section>
                <section class="schedule-next-run"><h3><i class="fa-light fa-clock-rotate-left" aria-hidden="true"></i>{{ __('Next run') }}</h3><strong>{{ $isSample ? __('Preview only') : ($isExpired ? __('Schedule ended') : (!$schedule->status ? __('On hold') : $format($schedule->next_run_at))) }}</strong><p>{{ __('Last checked') }} <span>{{ $isSample ? '—' : $format($schedule->last_checked_at) }}</span></p></section>
            </div>
            <div class="schedule-feed-notice" data-tone="{{ $tone }}"><i class="fa-light {{ $isSample ? 'fa-circle-info' : ($tone === 'warning' ? 'fa-triangle-exclamation' : 'fa-circle-check') }}" aria-hidden="true"></i><span>{{ $badge['reason'] }}</span>@if($isExpired)<a href="{{ route('portal.rss-schedules.edit', $schedule) }}" wire:navigate>{{ __('Update schedule') }} <i class="fa-light fa-arrow-right" aria-hidden="true"></i></a>@endif</div>
            <footer class="schedule-feed-footer">
                <div class="schedule-outcomes" aria-label="{{ __('Publishing results') }}">
                    @foreach([[$counts['drafts'] ?? 0, __('Drafts')], [$counts['published'], __('Published')], [$queuedCounts[$schedule->id] ?? 0, __('Queued')], [$counts['failed'], __('Failed')]] as [$count, $label])<span><strong>{{ $count }}</strong>{{ $label }}</span>@endforeach
                </div>
                <div class="schedule-actions">
                    <x-ui.button type="button" variant="outline" wire:click="showActivity('{{ $schedule->id_secure }}')" :aria-label="__('Activity for :name', ['name' => $schedule->name])"><i class="fa-light fa-clock-rotate-left" aria-hidden="true"></i>{{ __('Activity') }}</x-ui.button>
                    <x-ui.button :href="route('portal.rss-schedules.edit', $schedule)" :variant="$isSample || $isExpired ? 'primary' : 'outline'" wire:navigate><i class="fa-light fa-pen" aria-hidden="true"></i>{{ $isSample ? __('Customize') : __('Edit') }}</x-ui.button>
                    @unless($isSample)
                        <x-ui.button type="button" variant="outline" wire:click="toggleSchedule('{{ $schedule->id_secure }}')" wire:loading.attr="disabled">{{ $schedule->status ? __('Pause') : __('Resume') }}</x-ui.button>
                        <x-ui.button type="button" :variant="$isExpired ? 'outline' : 'primary'" wire:click="runSchedule('{{ $schedule->id_secure }}')" wire:loading.attr="disabled" :disabled="$schedule->eligible_accounts_count === 0" :wire:confirm="$modeIsDraft ? __('Create drafts from new feed items now?') : __('Process new feed items now? They will enter the publishing queue and may be published immediately.')"><i class="fa-light fa-play" aria-hidden="true"></i>{{ __('Run now') }}</x-ui.button>
                    @endunless
                    <button type="button" class="schedule-delete" wire:click="deleteSchedule('{{ $schedule->id_secure }}')" wire:confirm="{{ __('Delete this schedule? Posts already created in Publishing will remain.') }}" aria-label="{{ __('Delete :name', ['name' => $schedule->name]) }}"><i class="fa-light fa-trash" aria-hidden="true"></i></button>
                </div>
            </footer>
        </x-ui.card>
    @empty
        <div class="schedule-empty"><span class="schedule-feed-icon"><i class="fa-light fa-rss" aria-hidden="true"></i></span><h2>{{ $search !== '' || $status !== 'all' ? __('No matching schedules') : __('Connect your first feed') }}</h2><p class="schedule-muted">{{ $search !== '' || $status !== 'all' ? __('Try a different search or clear your filters.') : __('Choose a feed, select channels, and decide when new articles become posts.') }}</p>@if($search !== '' || $status !== 'all')<x-ui.button type="button" wire:click="resetFilters" variant="outline">{{ __('Clear filters') }}</x-ui.button>@else<x-ui.button :href="route('portal.rss-schedules.create')" wire:navigate>{{ __('Add feed') }}</x-ui.button>@endif</div>
    @endforelse
    </div>
    {{ $schedules->links() }}
    @if($activity)
        <div class="rss-activity-overlay" role="dialog" aria-modal="true" aria-label="{{ __('RSS Activity') }}" x-data="{ previous: document.activeElement, destroy() { this.previous?.focus(); }, trap(event) { const nodes = [...this.$el.querySelectorAll('a[href], button:not([disabled]), input, select, textarea, [tabindex]')].filter(node => node.getClientRects().length); const first = nodes[0], last = nodes[nodes.length - 1]; if (event.shiftKey &amp;&amp; document.activeElement === first) { event.preventDefault(); last?.focus(); } else if (!event.shiftKey &amp;&amp; document.activeElement === last) { event.preventDefault(); first?.focus(); } } }" x-init="$nextTick(() => $refs.close.focus())" @keydown.tab="trap($event)" @keydown.escape.window="$wire.set('activityKey', null)" @click.self="$wire.set('activityKey', null)">
            <div class="rss-activity"><header><div><h2>{{ __('Activity') }} · {{ $activity->name }}</h2><p class="rss-muted">{{ __('Recent items and the latest processing result.') }}</p></div><button type="button" x-ref="close" wire:click="$set('activityKey', null)" aria-label="{{ __('Close activity') }}"><i class="fa-light fa-xmark"></i></button></header>
                <div class="rss-note">{{ data_get($activity->settings, 'last_run.message', __('No recorded processing result yet.')) }}</div>
                @if(data_get($activity->settings, 'run_history'))
                    <details class="rss-advanced" style="margin-top:16px"><summary>{{ __('Recent runs') }}</summary>
                        @foreach(array_reverse((array) data_get($activity->settings, 'run_history', [])) as $run)
                            <div class="rss-activity-item"><strong>{{ $run['message'] ?? '' }}</strong><p class="rss-muted">{{ \Carbon\Carbon::createFromTimestamp((int) ($run['at'] ?? 0), $timezone)->format('d M Y, H:i') }} · {{ __(':count items created', ['count' => $run['queued'] ?? 0]) }}</p></div>
                        @endforeach
                    </details>
                @endif
                <p class="rss-muted" style="margin-top:12px">{{ __('Last checked') }}: {{ $activity->last_checked_at ? \Carbon\Carbon::createFromTimestamp($activity->last_checked_at, $timezone)->format('d M Y, H:i') : __('Not yet') }}</p>
                @forelse($activityRows as $row)
                    @php
                        $postState = match((int) $row->publishingPost?->status) { 1 => __('Draft'), 2 => __('Processing'), 3 => __('Queued'), 4 => __('Published'), 5 => __('Failed'), default => __('Post removed') };
                    @endphp
                    <div class="rss-activity-item"><strong>{{ $row->title }}</strong><p class="rss-muted">{{ $postState }} · {{ \Carbon\Carbon::createFromTimestamp($row->queued_at, $timezone)->format('d M, H:i') }}</p><p class="rss-muted">{{ data_get($row->publishingPost?->data, 'options.rss_ai_rewrite.message', '') }}</p></div>
                @empty<p class="rss-muted" style="padding:24px 0">{{ __('No feed items have been created yet.') }}</p>@endforelse
                <div class="rss-actions" style="margin-top:16px"><x-ui.button :href="route('portal.publishing.drafts')" variant="outline">{{ __('Open drafts') }}</x-ui.button><x-ui.button :href="route('portal.publishing.queue')" variant="outline">{{ __('Open queue') }}</x-ui.button></div>
            </div>
        </div>
    @endif
</div>
