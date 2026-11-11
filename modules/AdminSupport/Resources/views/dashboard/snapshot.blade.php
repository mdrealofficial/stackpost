@php
    $supportSeries = [[
        'name' => __('Tickets'),
        'data' => [
            (int) $metrics['total'],
            (int) $metrics['open'],
            (int) $metrics['resolved'],
            (int) $metrics['unread'],
        ],
    ]];
@endphp
<x-ui.dashboard-module :title="__('Support')" :description="__('Tickets awaiting a response and resolved requests.')" class="sp-overview-module">
    <x-slot:actions><x-ui.button :href="$route" size="sm" wire:navigate>{{ __('Open support queue') }}</x-ui.button></x-slot:actions>
    <dl class="sp-overview-metrics"><div><dt>{{ __('All tickets') }}</dt><dd>{{ number_format((int)($metrics['total'])) }}</dd></div><div><dt>{{ __('Open') }}</dt><dd>{{ number_format((int)($metrics['open'])) }}</dd></div><div><dt>{{ __('Resolved') }}</dt><dd>{{ number_format((int)($metrics['resolved'])) }}</dd></div><div><dt>{{ __('Unread') }}</dt><dd>{{ number_format((int)($metrics['unread'])) }}</dd></div></dl>
    <x-ui.chart
            :title="__('Queue distribution')"
            :description="__('A quick service view across total, open, resolved, and unread states.')"
            type="column"
            :categories="[__('All'), __('Open'), __('Resolved'), __('Unread')]"
            :series="$supportSeries"
            :height="220"
        />
</x-ui.dashboard-module>
