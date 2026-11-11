@php
    $manualSeries = [[
        'name' => __('Requests'),
        'data' => [
            (int) $metrics['total'],
            (int) $metrics['pending'],
            (int) $metrics['approved'],
        ],
    ]];
@endphp
<x-ui.dashboard-module :title="__('Manual payments')" :description="__('Payment requests awaiting review.')" class="sp-overview-module">
    <x-slot:actions><x-ui.button :href="$route" size="sm" wire:navigate>{{ __('Open manual payments') }}</x-ui.button></x-slot:actions>
    <dl class="sp-overview-metrics"><div><dt>{{ __('Requests') }}</dt><dd>{{ number_format((int)($metrics['total'])) }}</dd></div><div><dt>{{ __('Pending') }}</dt><dd>{{ number_format((int)($metrics['pending'])) }}</dd></div><div><dt>{{ __('Approved') }}</dt><dd>{{ number_format((int)($metrics['approved'])) }}</dd></div></dl><p class="text-xs" style="color:var(--theme-muted-text-color)">{{ __('Pending amount') }}: <strong>{{ number_format((float)$metrics['pending_volume'], 2) }}</strong></p>
    <x-ui.chart
            :title="__('Approval queue')"
            :description="__('How manual payment requests are distributed across total, pending, and approved states.')"
            type="bar"
            :categories="[__('All'), __('Pending'), __('Approved')]"
            :series="$manualSeries"
            :height="220"
        />
</x-ui.dashboard-module>
