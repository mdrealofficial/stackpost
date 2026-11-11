@php
    $inactiveSubscriptions = max(0, (int) $metrics['total'] - (int) $metrics['active'] - (int) $metrics['cancelled']);
    $subscriptionSeries = [[
        'name' => __('Subscriptions'),
        'data' => [
            ['name' => __('Active'), 'y' => (int) $metrics['active']],
            ['name' => __('Cancelled'), 'y' => (int) $metrics['cancelled']],
            ['name' => __('Inactive'), 'y' => $inactiveSubscriptions],
        ],
    ]];
@endphp
<x-ui.dashboard-module :title="__('Subscriptions')" :description="__('Active and cancelled recurring subscriptions.')" class="sp-overview-module">
    <x-slot:actions><x-ui.button :href="$route" size="sm" wire:navigate>{{ __('Open subscriptions') }}</x-ui.button></x-slot:actions>
    <dl class="sp-overview-metrics"><div><dt>{{ __('Subscriptions') }}</dt><dd>{{ number_format((int)($metrics['total'])) }}</dd></div><div><dt>{{ __('Active') }}</dt><dd>{{ number_format((int)($metrics['active'])) }}</dd></div><div><dt>{{ __('Cancelled') }}</dt><dd>{{ number_format((int)($metrics['cancelled'])) }}</dd></div><div><dt>{{ __('Inactive') }}</dt><dd>{{ number_format((int)($inactiveSubscriptions)) }}</dd></div></dl><p class="text-xs" style="color:var(--theme-muted-text-color)">{{ __('Active recurring amount') }}: <strong>{{ number_format((float)$metrics['active_volume'], 2) }}</strong></p>
    <x-ui.chart
            :title="__('Subscription states')"
            :description="__('The recurring base split between active, cancelled, and inactive subscriptions.')"
            type="donut"
            :series="$subscriptionSeries"
            :height="220"
        />
</x-ui.dashboard-module>
