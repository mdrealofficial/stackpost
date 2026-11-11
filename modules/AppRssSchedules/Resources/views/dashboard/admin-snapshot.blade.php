@php
    $dailyCategories = collect($daily ?? [])->map(fn (array $point) => $point['label'] ?? '--')->all();
    $dailySeries = [[
        'name' => __('Queued'),
        'data' => collect($daily ?? [])->map(fn (array $point) => (int) ($point['value'] ?? 0))->all(),
    ]];
    $footerStats = [
        ['label' => __('Queued (7d)'), 'value' => (int) ($metrics['queued_week'] ?? 0)],
        ['label' => __('Active schedules'), 'value' => (int) ($metrics['active'] ?? 0)],
        ['label' => __('Paused schedules'), 'value' => (int) ($metrics['paused'] ?? 0)],
        ['label' => __('History rows'), 'value' => (int) ($metrics['history_total'] ?? 0)],
    ];
@endphp
<x-ui.dashboard-module :title="__('RSS automation')" :description="__('Feed schedules and queued posts.')" class="sp-overview-module">
    <x-slot:actions><x-ui.button :href="$route" size="sm" wire:navigate>{{ __('Open RSS schedules') }}</x-ui.button></x-slot:actions>
    <dl class="sp-overview-metrics"><div><dt>{{ __('Schedules') }}</dt><dd>{{ number_format((int)($metrics['total'])) }}</dd></div><div><dt>{{ __('Active') }}</dt><dd>{{ number_format((int)($metrics['active'])) }}</dd></div><div><dt>{{ __('Paused') }}</dt><dd>{{ number_format((int)($metrics['paused'])) }}</dd></div></dl>
    <x-ui.chart
                        :title="__('Queued post trend')"
                        :description="__('Posts queued by RSS schedules per day (last 7 days).')"
                        type="line"
                        :categories="$dailyCategories"
                        :series="$dailySeries"
                        :height="220"
                        :footer-stats="$footerStats"
                    />
</x-ui.dashboard-module>
