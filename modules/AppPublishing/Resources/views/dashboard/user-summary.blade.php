@php
    $monthTotal = max(0, (int) ($metrics['month_total'] ?? 0));
    $published = max(0, (int) ($metrics['published'] ?? 0));
    $scheduled = max(0, (int) ($metrics['scheduled'] ?? 0));
    $drafts = max(0, (int) ($metrics['drafts'] ?? 0));
    $failed = max(0, (int) ($metrics['failed'] ?? 0));
    $processing = max(0, (int) ($metrics['processing'] ?? 0));
    $upcoming = max(0, (int) ($metrics['upcoming'] ?? 0));
    $activeChannels = max(0, (int) ($metrics['accounts'] ?? 0));
    $limit = (int) ($metrics['limit'] ?? 0);
    $usagePercent = is_numeric($metrics['usage_percent'] ?? null) ? min(100, max(0, (int) $metrics['usage_percent'])) : null;
    $successRate = is_numeric($metrics['publish_success_rate'] ?? null) ? min(100, max(0, (int) $metrics['publish_success_rate'])) : null;
    $calendarRoute = $item['route'] ?? route('portal.publishing.calendar');
    $queueRoute = route('portal.publishing.queue');
    $draftRoute = route('portal.publishing.drafts');

    $trendData = array_map('intval', (array) data_get($chart, 'series.0.data', []));
    $trendTotal = array_sum($trendData);
    $activityOptions = [
        'chart'=>['type'=>'areaspline','backgroundColor'=>'transparent','spacing'=>[12,8,8,0]],
        'title'=>['text'=>null], 'credits'=>['enabled'=>false], 'legend'=>['enabled'=>false],
        'xAxis'=>['categories'=>(array)($chart['categories'] ?? []),'tickWidth'=>0,'lineColor'=>'#dce6e3'],
        'yAxis'=>['min'=>0,'softMax'=>1,'allowDecimals'=>false,'title'=>['text'=>null],'gridLineColor'=>'#e8eeeb','gridLineDashStyle'=>'Dash'],
        'tooltip'=>['shared'=>true,'valueDecimals'=>0],
        'plotOptions'=>['areaspline'=>['lineWidth'=>3,'fillOpacity'=>0.10,'marker'=>['enabled'=>true,'radius'=>3]]],
        'series'=>[['name'=>__('Posts created'),'data'=>$trendData,'color'=>'#176b5d']],
    ];
    $recentItems = collect($recentPosts ?? [])->take(3);

    $primaryStats = [
        ['label' => __('Published'), 'value' => $published, 'description' => __('Delivered this month'), 'icon' => 'fa-paper-plane', 'tone' => '#0d9488', 'iconSurface' => 'rgba(13,148,136,.09)', 'spark' => 'M2 30 C10 28 14 18 22 20 S34 29 42 16 S52 10 62 6'],
        ['label' => __('Scheduled'), 'value' => $scheduled, 'description' => __('Waiting for delivery'), 'icon' => 'fa-calendar-clock', 'tone' => '#b7791f', 'iconSurface' => 'rgba(183,121,31,.10)', 'spark' => 'M2 28 C10 24 15 16 23 18 S33 29 41 20 S53 12 62 14'],
        ['label' => __('Drafts'), 'value' => $drafts, 'description' => __('Still being prepared'), 'icon' => 'fa-file-pen', 'tone' => '#64748b', 'iconSurface' => 'rgba(100,116,139,.09)', 'spark' => 'M2 25 C10 26 15 18 23 22 S34 29 42 17 S53 20 62 9'],
        ['label' => __('Failed'), 'value' => $failed, 'description' => __('Require a review'), 'icon' => 'fa-triangle-exclamation', 'tone' => '#c24141', 'iconSurface' => $failed > 0 ? 'rgba(220,38,38,.09)' : 'rgba(100,116,139,.07)', 'spark' => 'M2 24 C12 24 16 23 23 24 S35 24 42 24 S53 24 62 24'],
    ];

    $nextStepStats = [
        ['label' => __('Overdue posts'), 'value' => $reports['action_overdue'] ?? 0, 'description' => __('Review scheduled posts'), 'icon' => 'fa-clock', 'tone' => '#b7791f', 'iconSurface' => 'rgba(183,121,31,.10)', 'href' => route('portal.publishing.queue', ['status' => 'scheduled']), 'wireNavigate' => true],
        ['label' => __('Failed posts'), 'value' => $reports['action_failed'] ?? 0, 'description' => __('Review failed posts'), 'icon' => 'fa-triangle-exclamation', 'tone' => '#c24141', 'iconSurface' => 'rgba(220,38,38,.09)', 'href' => route('portal.publishing.queue', ['status' => 'failed']), 'wireNavigate' => true],
        ['label' => __('Drafts to continue'), 'value' => $reports['action_drafts'] ?? 0, 'description' => __('Open your draft library'), 'icon' => 'fa-file-pen', 'tone' => '#0d9488', 'iconSurface' => 'rgba(13,148,136,.09)', 'href' => $draftRoute, 'wireNavigate' => true],
    ];

    $emptyActionRoute = $activeChannels > 0 || ! \Illuminate\Support\Facades\Route::has('portal.channels')
        ? $calendarRoute
        : route('portal.channels');
    $emptyActionLabel = $activeChannels > 0 ? __('Open calendar') : __('Connect a channel');
@endphp

<div class="dash-publishing">
@if(isset($reports))
<section class="dash-attention" aria-label="{{ __('Your next steps') }}">
<div class="dash-section-heading"><div><h2>{{ __('Your next steps') }}</h2><p>{{ __('Active publishing channels in this workspace · all dates. Historical totals remain in the reports below.') }}</p></div></div>
<x-ui.metric-strip :items="$nextStepStats" columns="md:grid-cols-3" gap="gap-4" variant="portal" :showProgress="false" />
</section>
@endif
    <div class="dash-section-heading"><div><span class="dash-kicker">{{ now()->translatedFormat('F Y') }}</span><h2>{{ __('Your publishing pulse') }}</h2><p>{{ __('Only posts created this month are counted below. Earlier posts remain in your workspace.') }}</p></div><a class="dash-link" href="{{ $calendarRoute }}" wire:navigate>{{ __('Open calendar') }} <i class="fa-light fa-arrow-up-right"></i></a></div>
    <x-ui.metric-strip :items="$primaryStats" columns="md:grid-cols-4" gap="gap-4" variant="portal" :showProgress="false" />
    <div class="dash-publishing-grid">
        <x-ui.card padding="none" class="dash-panel"><div class="dash-section-heading"><div><h3>{{ __('Your last 7 days') }}</h3><p>{{ __('Posts created over the last 7 days') }}</p></div><span class="dash-pill">{{ $trendTotal }} {{ __('posts') }}</span></div>
            <x-ui.chart id="dashboard-publishing-activity" class="dash-chart" :height="250" :options="$activityOptions" />
            @if($trendTotal === 0)<p class="dash-muted">{{ __('No posts created in the last 7 days.') }} <a class="dash-link" href="{{ $emptyActionRoute }}" wire:navigate>{{ $emptyActionLabel }}</a></p>@endif
            <div class="dash-foot"><span>{{ __('Monthly allowance') }} <strong>{{ $monthTotal }} / {{ $limit < 0 ? __('Unlimited') : $limit }}</strong></span><span>{{ $upcoming }} {{ __('scheduled in the next 7 days') }}</span></div>
        </x-ui.card>
        <x-ui.card padding="none" class="dash-panel"><div class="dash-section-heading"><div><h3>{{ __('Pick up where you left off') }}</h3><p>{{ __('Latest posts across all dates') }}</p></div><a class="dash-link" href="{{ $queueRoute }}" wire:navigate>{{ __('Queue') }} <i class="fa-light fa-arrow-right"></i></a></div>
            @forelse($recentItems as $post)<a class="dash-post" href="{{ $post['post_url'] && $post['status'] === __('Published') ? $post['post_url'] : $post['route'] }}" @if($post['post_url'] && $post['status'] === __('Published')) target="_blank" rel="noreferrer" @else wire:navigate @endif><span class="dash-post-icon"><i class="fa-light {{ $post['is_video'] ? 'fa-video' : 'fa-file-lines' }}"></i></span><span class="dash-post-copy"><strong>{{ $post['title'] ?: __('Untitled post') }}</strong><small>{{ $post['network'] }} · {{ $post['scheduled'] ?: $post['created'] }}</small></span><span class="dash-pill" style="color:{{ $post['status_tone']['text'] }};background:{{ $post['status_tone']['surface'] }}">{{ $post['status'] }}</span></a>@empty<div class="dash-empty"><h3>{{ __('Your ideas belong here') }}</h3><p>{{ __('Create your first draft to start building your content library.') }}</p><a class="dash-link" href="{{ $draftRoute }}" wire:navigate>{{ __('Open drafts') }}</a></div>@endforelse
            <div class="dash-shortcuts"><a href="{{ $draftRoute }}" wire:navigate><i class="fa-light fa-file-pen"></i>{{ __('Drafts') }}</a>@if(Route::has('portal.publishing.campaigns'))<a href="{{ route('portal.publishing.campaigns') }}" wire:navigate><i class="fa-light fa-bullhorn"></i>{{ __('Campaigns') }}</a>@endif</div>
            @if(($rssSummary['visible'] ?? false) || ($aiPublishingSummary['visible'] ?? false))<div class="dash-foot">@if($rssSummary['visible'] ?? false)<a class="dash-link" href="{{ $rssSummary['route'] }}" wire:navigate>{{ __('RSS schedules') }} <i class="fa-light fa-arrow-up-right"></i></a>@endif @if($aiPublishingSummary['visible'] ?? false)<a class="dash-link" href="{{ $aiPublishingSummary['route'] }}" wire:navigate>{{ __('AI Publishing') }} <i class="fa-light fa-arrow-up-right"></i></a>@endif</div>@endif
        </x-ui.card>
    </div>
    @include('apppublishing::dashboard.reports')
</div>
