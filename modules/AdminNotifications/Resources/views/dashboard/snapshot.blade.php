@php
    $notificationSeries = [[
        'name' => __('Notifications'),
        'data' => [
            (int) $metrics['campaigns'],
            (int) $metrics['global'],
            (int) $metrics['deliveries'],
            (int) $metrics['unread'],
        ],
    ]];
@endphp
<x-ui.dashboard-module :title="__('Notifications')" :description="__('Campaigns, broadcasts, and delivery activity.')" class="sp-overview-module">
    <x-slot:actions><x-ui.button :href="$route" size="sm" wire:navigate>{{ __('Open notifications') }}</x-ui.button></x-slot:actions>
    <dl class="sp-overview-metrics"><div><dt>{{ __('Campaigns') }}</dt><dd>{{ number_format((int)($metrics['campaigns'])) }}</dd></div><div><dt>{{ __('Broadcasts') }}</dt><dd>{{ number_format((int)($metrics['global'])) }}</dd></div><div><dt>{{ __('Deliveries') }}</dt><dd>{{ number_format((int)($metrics['deliveries'])) }}</dd></div><div><dt>{{ __('Unread') }}</dt><dd>{{ number_format((int)($metrics['unread'])) }}</dd></div></dl>
    <x-ui.chart
            :title="__('Notification workload')"
            :description="__('Relative weight between campaigns, broadcasts, deliveries, and unread notifications.')"
            type="bar"
            :categories="[__('Campaigns'), __('Global'), __('Deliveries'), __('Unread')]"
            :series="$notificationSeries"
            :height="220"
        />
</x-ui.dashboard-module>
