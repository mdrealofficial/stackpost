<div class="ai-workspace schedule-index">
@include('appaipublishing::partials.styles')
<x-shared::schedule-workspace-styles />
<x-ui.sub-header compact :title="__('AI Schedules')" :description="__('Turn your topics into recurring content. Review drafts or schedule publishing.')"><x-slot:actions><x-ui.button :href="route('portal.publishing.calendar')" variant="outline" wire:navigate><i class="fa-light fa-calendar-days" aria-hidden="true"></i>{{ __('Open Publishing') }}</x-ui.button><x-ui.button :href="route('portal.ai-publishing.create')" wire:navigate><i class="fa-light fa-plus" aria-hidden="true"></i>{{ __('Create schedule') }}</x-ui.button></x-slot:actions></x-ui.sub-header>
@if($actionMessage)<div class="ai-note" role="status">{{ $actionMessage }}</div>@endif
@php
    $aiScheduleMetrics = [
        ['label' => __('Schedules'), 'value' => number_format($total), 'description' => __('Configured AI schedules'), 'icon' => 'fa-light fa-layer-group', 'tone' => '#0d9488', 'iconSurface' => 'rgba(13,148,136,.12)', 'spark' => 'M2 24 C10 24 12 14 20 18 S28 29 36 20 S44 10 52 16 S58 18 62 12'],
        ['label' => __('Enabled'), 'value' => number_format($summary['enabled'] ?? 0), 'description' => __('Running on their schedule'), 'icon' => 'fa-light fa-circle-play', 'tone' => '#16856f', 'iconSurface' => 'rgba(22,133,111,.11)', 'spark' => 'M2 20 C10 20 12 26 20 22 S29 10 36 17 S44 25 52 16 S58 17 62 13'],
        ['label' => __('Paused'), 'value' => number_format($summary['paused'] ?? 0), 'description' => __('Temporarily stopped'), 'icon' => 'fa-light fa-circle-pause', 'tone' => '#64748b', 'iconSurface' => 'rgba(100,116,139,.12)', 'spark' => 'M2 18 C10 18 13 20 20 20 S29 18 36 20 S45 18 52 20 S58 18 62 18'],
        ['label' => __('Needs attention'), 'value' => number_format($summary['attention'] ?? 0), 'description' => __('Schedules requiring review'), 'icon' => 'fa-light fa-triangle-exclamation', 'tone' => '#b7791f', 'iconSurface' => 'rgba(183,121,31,.12)', 'spark' => 'M2 25 C10 25 12 16 20 19 S28 28 36 20 S44 11 52 17 S58 18 62 12'],
        ['label' => __('Expired'), 'value' => number_format($summary['expired'] ?? 0), 'description' => __('Schedules past their end date'), 'icon' => 'fa-light fa-calendar-xmark', 'tone' => '#b7791f', 'iconSurface' => 'rgba(183,121,31,.12)', 'spark' => 'M2 22 C10 22 12 18 20 20 S28 24 36 20 S44 18 52 21 S58 19 62 17'],
        ['label' => __('Samples'), 'value' => number_format($summary['sample'] ?? 0), 'description' => __('Preview runs kept for testing'), 'icon' => 'fa-light fa-flask', 'tone' => '#7c3aed', 'iconSurface' => 'rgba(124,58,237,.10)', 'spark' => 'M2 24 C10 24 12 15 20 18 S28 28 36 19 S44 11 52 16 S58 18 62 12'],
    ];
@endphp
<x-ui.metric-strip :items="$aiScheduleMetrics" columns="grid-cols-2 md:grid-cols-3" gap="gap-4" variant="portal" :show-progress="false" aria-label="{{ __('Schedule overview') }}" />
<div class="schedule-collection-heading"><div><h2>{{ __('Your content schedules') }}</h2><p>{{ __('Follow your prompts, publishing times, and generation results.') }}</p></div></div>
<div class="schedule-toolbar">
    <x-ui.input wire:model.live.debounce.300ms="search" :placeholder="__('Search schedules...')" :aria-label="__('Search schedules')" />
    <x-ui.select wire:model.live="status" :aria-label="__('Filter schedules')"><option value="all">{{ __('All schedules') }}</option>@foreach(['enabled'=>__('Enabled'),'paused'=>__('Paused'),'attention'=>__('Needs attention'),'expired'=>__('Expired'),'sample'=>__('Samples'),'processing'=>__('Processing'),'completed'=>__('Completed')] as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</x-ui.select>
    @if($search !== '' || $status !== 'all')<x-ui.button type="button" wire:click="resetFilters" variant="outline">{{ __('Clear filters') }}</x-ui.button>@endif
    <span class="schedule-result-count" role="status">{{ __(':count results', ['count' => $runs->total()]) }}</span>
</div>
<div class="schedule-list">
@forelse($runs as $run)
    @php
        $state = $run->displayStatus();
        $labels = ['sample'=>__('Sample'),'expired'=>__('Expired'),'enabled'=>__('Enabled'),'paused'=>__('Paused'),'attention'=>__('Needs attention'),'processing'=>__('Processing'),'completed'=>__('Completed')];
        $counts = ($postCounts[$run->id] ?? collect())->keyBy('status');
        $isSample = $run->isDemo();
        $isExpired = !$isSample && $run->hasEnded();
        $tone = in_array($state, ['expired', 'attention']) ? 'warning' : (in_array($state, ['enabled', 'processing']) ? 'success' : 'neutral');
        $modeIsDraft = data_get($run->generation_config, 'delivery_mode', 'automatic') === 'draft';
        $days = array_map(fn ($day) => strtolower(trim((string) $day)), (array) data_get($run->schedule_config, 'weekdays', []));
        $times = (array) data_get($run->schedule_config, 'time_slots', []);
        $notice = $isSample ? __('Sample only. Customize and save your settings before enabling generation.') : ($isExpired ? __('This date range has ended. Existing posts remain in Publishing.') : data_get($run->stats, 'last_error_message'));
    @endphp
    <x-ui.card padding="none" class="schedule-feed-card" wire:key="ai-run-{{ $run->id }}" data-tone="{{ $tone }}">
        <div class="schedule-feed-top">
            <span class="schedule-feed-icon"><i class="fa-light {{ $isSample ? 'fa-flask' : 'fa-sparkles' }}" aria-hidden="true"></i></span>
            <div class="schedule-feed-title"><h2><a href="{{ route('portal.ai-publishing.edit', $run) }}" wire:navigate>{{ $run->name }}</a></h2><p>{{ __('Prompts: :count', ['count' => count((array) $run->prompt_ids)]) }}</p></div>
            <span class="schedule-mode"><i class="fa-light {{ $modeIsDraft ? 'fa-file-pen' : 'fa-bolt' }}" aria-hidden="true"></i>{{ $modeIsDraft ? __('Create drafts') : __('Auto publish') }}</span>
            <span class="schedule-feed-status" data-tone="{{ $tone }}"><span aria-hidden="true"></span>{{ $labels[$state] ?? $state }}</span>
        </div>
        <div class="schedule-feed-body">
            <section class="schedule-destination"><h3><i class="fa-light fa-share-nodes" aria-hidden="true"></i>{{ __('Destination channels') }}</h3><div class="schedule-channel-total"><strong>{{ count((array) $run->account_ids) }}</strong><span>{{ __('selected') }}</span></div><div class="schedule-production"><span><strong>{{ $totals[$run->id]->generated_count ?? 0 }}</strong>{{ __('Generated slots') }}</span><span><strong>{{ number_format($totals[$run->id]->credits ?? 0) }}</strong>{{ __('Recorded AI credits') }}</span></div></section>
            <section class="schedule-cadence"><h3><i class="fa-light fa-calendar-days" aria-hidden="true"></i>{{ __('Publishing rhythm') }}</h3><div class="schedule-weekdays">@foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $day)<span class="{{ in_array(strtolower($day), $days, true) ? 'is-selected' : '' }}" aria-label="{{ __($day) }}: {{ in_array(strtolower($day), $days, true) ? __('Scheduled') : __('Not scheduled') }}">{{ __($day) }}</span>@endforeach</div><div class="schedule-time-slots">@forelse($times as $time)<span><i class="fa-light fa-clock" aria-hidden="true"></i>{{ $time }}</span>@empty<span>{{ __('No time selected') }}</span>@endforelse</div></section>
            <section class="schedule-next-run"><h3><i class="fa-light fa-clock-rotate-left" aria-hidden="true"></i>{{ __('Next slot') }}</h3><strong>{{ $isSample ? __('Preview only') : ($isExpired ? __('Schedule ended') : ($state === 'paused' ? __('On hold') : ($next[$run->id] ?? __('Not scheduled')))) }}</strong><small>{{ $run->timezone() }}</small><p>{{ __('Last processed') }}<span>{{ $run->last_processed_at ? $run->last_processed_at->copy()->timezone($run->timezone())->format('d M Y, H:i') : __('Not yet') }}</span></p></section>
        </div>
        @if($notice)<div class="schedule-feed-notice" data-tone="{{ $tone }}"><i class="fa-light {{ $tone === 'warning' ? 'fa-triangle-exclamation' : 'fa-circle-info' }}" aria-hidden="true"></i><span>{{ $notice }}</span>@if($isExpired)<a href="{{ route('portal.ai-publishing.edit', $run) }}" wire:navigate>{{ __('Update schedule') }} <i class="fa-light fa-arrow-right" aria-hidden="true"></i></a>@endif</div>@endif
        <footer class="schedule-feed-footer">
            <div class="schedule-outcomes" aria-label="{{ __('Publishing results') }}">@foreach([[1, __('Drafts')], [4, __('Published')], [3, __('Queued')], [5, __('Failed')]] as [$key, $label])<span><strong>{{ $counts[$key]->total ?? 0 }}</strong>{{ $label }}</span>@endforeach</div>
            <div class="schedule-actions">
                <x-ui.button type="button" variant="outline" wire:click="showActivity({{ $run->id }})" :aria-label="__('Activity for :name', ['name' => $run->name])"><i class="fa-light fa-clock-rotate-left" aria-hidden="true"></i>{{ __('Activity') }}</x-ui.button>
                <x-ui.button :variant="$isSample || $isExpired ? 'primary' : 'outline'" :href="route('portal.ai-publishing.edit', $run)" wire:navigate><i class="fa-light fa-pen" aria-hidden="true"></i>{{ $isSample ? __('Customize') : __('Edit') }}</x-ui.button>
                @if(!$isSample && !$run->hasEnded())
                    @if(in_array($run->status, ['paused', 'completed']))
                        <x-ui.button type="button" variant="outline" wire:click="startRun({{ $run->id }})">{{ __('Resume') }}</x-ui.button>
                    @else
                        <x-ui.button type="button" variant="outline" wire:click="stopRun({{ $run->id }})">{{ __('Pause') }}</x-ui.button>
                        <x-ui.button type="button" wire:click="runNow({{ $run->id }})" wire:loading.attr="disabled" wire:confirm="{{ __('Generate one prompt for the next slot? This uses AI credits and follows the saved draft or automatic delivery mode. A retry reuses content already generated.') }}"><i class="fa-light fa-sparkles" aria-hidden="true"></i>{{ $state === 'attention' ? __('Retry attempt') : __('Generate next') }}</x-ui.button>
                    @endif
                @endif
                <button type="button" class="schedule-delete" wire:click="deleteRun({{ $run->id }})" wire:confirm="{{ __('Delete this schedule? Existing posts and credit history will remain.') }}" aria-label="{{ __('Delete :name', ['name' => $run->name]) }}"><i class="fa-light fa-trash" aria-hidden="true"></i></button>
            </div>
        </footer>
    </x-ui.card>
@empty
    <div class="schedule-empty"><span class="schedule-feed-icon"><i class="fa-light fa-sparkles" aria-hidden="true"></i></span><h2>{{ $search !== '' || $status !== 'all' ? __('No matching schedules') : __('Give your ideas a schedule') }}</h2><p class="schedule-muted">{{ $search !== '' || $status !== 'all' ? __('Try a different search or clear your filters.') : __('Add prompts, choose channels, and set when AI creates your content.') }}</p>@if($search !== '' || $status !== 'all')<x-ui.button type="button" variant="outline" wire:click="resetFilters">{{ __('Clear filters') }}</x-ui.button>@else<x-ui.button :href="route('portal.ai-publishing.create')" wire:navigate>{{ __('Create schedule') }}</x-ui.button>@endif</div>
@endforelse
</div>
{{ $runs->links() }}
@if($activity)
<div class="ai-overlay" wire:key="ai-activity-{{ $activity->id }}" x-data="{previous:document.activeElement,init(){this.$nextTick(()=>$refs.close.focus())},destroy(){this.previous?.focus()}}" x-on:keydown.escape.window="$wire.closeActivity()" x-on:keydown.tab="const items=[...$el.querySelectorAll('button,a[href],summary')].filter(el=>el.getClientRects().length);const first=items[0],last=items.at(-1);if($event.shiftKey &amp;&amp; document.activeElement===first){$event.preventDefault();last.focus()}else if(!$event.shiftKey &amp;&amp; document.activeElement===last){$event.preventDefault();first.focus()}">
<section class="ai-activity" role="dialog" aria-modal="true" aria-labelledby="ai-activity-title"><header><div><h2 id="ai-activity-title">{{ __('Activity') }}  |  {{ $activity->name }}</h2><p class="ai-muted">{{ __('Latest 30 generation attempts. Delivery status is available in Publishing.') }}</p></div><button type="button" x-ref="close" wire:click="closeActivity" aria-label="{{ __('Close activity') }}"><i class="fa-light fa-xmark" aria-hidden="true"></i></button></header>
<div class="ai-actions"><x-ui.button variant="outline" :href="route('portal.publishing.drafts')">{{ __('Open drafts') }}</x-ui.button><x-ui.button variant="outline" :href="route('portal.publishing.queue')">{{ __('Open queue') }}</x-ui.button></div>
@forelse($attempts as $attempt)<details class="ai-attempt" @if($loop->first) open @endif><summary>{{ \Carbon\Carbon::createFromTimestamp($attempt->slot_at,$activity->timezone())->format('d M Y, H:i') }}  |  {{ __(['success'=>'Generated','failed'=>'Needs attention','pending'=>'Pending','processing'=>'Processing','skipped'=>'Skipped'][$attempt->status] ?? $attempt->status) }}</summary><p class="ai-muted">{{ __(':credits AI credits  |  :posts posts created',['credits'=>$attempt->credits_spent,'posts'=>count((array)data_get($attempt->payload,'post_ids',[]))]) }}</p>@if($attempt->error)<p class="ai-error">{{ $attempt->error }}</p>@endif<p>{{ data_get($attempt->payload,'prompt_text') }}</p>@if(data_get($attempt->payload,'generation.caption'))<pre class="ai-caption">{{ data_get($attempt->payload,'generation.caption') }}</pre>@endif
@if(in_array($attempt->status,['failed','pending','processing']))<x-ui.button type="button" variant="outline" wire:click="skipAttempt({{ $activity->id }})" wire:confirm="{{ __('Skip this unfinished attempt? Already created posts will be kept. The next slot uses the next prompt.') }}">{{ __('Skip unfinished attempt') }}</x-ui.button>@endif</details>@empty<p class="ai-muted" style="padding-top:20px">{{ __('No attempts recorded by the new scheduler yet. Existing posts remain available in Publishing.') }}</p>@endforelse
@if(data_get($activity->stats,'run_logs'))<details class="ai-attempt"><summary>{{ __('Earlier activity') }}</summary>@foreach((array)data_get($activity->stats,'run_logs') as $log)<p>{{ data_get($log,'logged_at') }}  |  {{ data_get($log,'message') }}</p>@endforeach</details>@endif
</section></div>
@endif
</div>
