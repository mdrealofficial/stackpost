@php
    $totalChannels = (int) ($metrics['total'] ?? 0);
    $activeChannels = (int) ($metrics['active'] ?? 0);
    $pausedChannels = (int) ($metrics['paused'] ?? 0);
    $providerCount = (int) ($metrics['providers'] ?? 0);
    $recentCount = (int) ($metrics['recent'] ?? 0);
    $limit = (int) ($metrics['limit'] ?? -1);
    $usagePercent = $metrics['usage_percent'] ?? null;
    $limitLabel = $limit > -1 ? number_format($limit) : __('Unlimited');
    $providerMix = collect($providerChart[0]['data'] ?? []);
    $recentChannelAccounts = collect($recentAccounts ?? []);
    $donutPoints = $providerMix->sortByDesc('y')->take(5)->values()->map(fn($point)=>['name'=>$point['name'],'y'=>(int)$point['y']]);
    $otherChannels = (int)$providerMix->sum('y') - (int)$donutPoints->sum('y');
    if ($otherChannels > 0) $donutPoints->push(['name'=>__('Other providers'),'y'=>$otherChannels]);
    $providerOptions = [
        'chart'=>['type'=>'pie','backgroundColor'=>'transparent','spacing'=>[4,0,4,0]],
        'title'=>['text'=>null], 'credits'=>['enabled'=>false],
        'colors'=>['#176b5d','#319985','#63b5a3','#91cabb','#b8dfd4','#cbd5d1'],
        'legend'=>['enabled'=>true,'itemStyle'=>['fontWeight'=>'normal','fontSize'=>'11px']],
        'tooltip'=>['pointFormat'=>'<b>{point.y}</b> ({point.percentage:.1f}%)'],
        'plotOptions'=>['pie'=>['innerSize'=>'72%','borderWidth'=>2,'borderColor'=>'transparent','dataLabels'=>['enabled'=>false],'showInLegend'=>true]],
        'series'=>[['name'=>__('Channels'),'data'=>$donutPoints->all()]],
    ];
@endphp

<section class="dash-panel dash-channel-panel"><div class="dash-section-heading"><div><span class="dash-kicker">{{ __('Your network') }}</span><h2>{{ __('Your connected channels') }}</h2></div><a class="dash-link" href="{{ $item['route'] ?? route('portal.channels') }}" wire:navigate>{{ __('Manage channels') }} <i class="fa-light fa-arrow-up-right"></i></a></div>
<div class="dash-network-total"><strong>{{ $totalChannels }}</strong><span>{{ __('connected channels') }}<small>{{ $activeChannels }} {{ __('active') }} · {{ $pausedChannels }} {{ __('paused') }} · {{ $providerCount }} {{ __('providers') }}</small></span></div>
@if($totalChannels > 0)<x-ui.chart id="dashboard-channel-distribution" class="dash-chart" :height="265" :options="$providerOptions" />@else<p class="dash-muted">{{ __('Connect your first channel to bring your network together.') }}</p>@endif
<div class="dash-foot"><span>{{ __('Channel capacity') }}</span><strong>{{ $totalChannels }} / {{ $limitLabel }}</strong></div></section>
