@php
    $dailyCategories = collect($daily ?? [])->map(fn (array $point) => $point['label'] ?? '--')->all();
    $dailyValues = collect($daily ?? [])->map(fn (array $point) => (int) ($point['value'] ?? 0))->values();
    $dailySeries = [[
        'name' => __('Runs'),
        'data' => $dailyValues->all(),
    ]];
    $totalRuns = (int) ($metrics['total'] ?? 0);
    $queuedRuns = (int) ($metrics['queued'] ?? 0);
    $completedRuns = (int) ($metrics['completed'] ?? 0);
    $failedRuns = (int) ($metrics['failed'] ?? 0);
    $weekTotal = (int) ($metrics['week_total'] ?? 0);
    $thisMonth = (int) ($metrics['this_month'] ?? 0);
    $avgDailyRuns = $dailyValues->isNotEmpty() ? round($dailyValues->avg(), 1) : 0;
    $peakDailyRuns = (int) $dailyValues->max();
    $successRate = $totalRuns > 0 ? (int) round(($completedRuns / max(1, $totalRuns)) * 100) : 0;
    $failureRate = $totalRuns > 0 ? (int) round(($failedRuns / max(1, $totalRuns)) * 100) : 0;
    $queuePressure = $totalRuns > 0 ? (int) round(($queuedRuns / max(1, $totalRuns)) * 100) : 0;
    $footerStats = [
        ['label' => __('Runs (7d)'), 'value' => $weekTotal],
        ['label' => __('This month'), 'value' => $thisMonth],
        ['label' => __('Success rate'), 'value' => $successRate, 'suffix' => '%'],
        ['label' => __('Queue share'), 'value' => $queuePressure, 'suffix' => '%'],
    ];
@endphp
<x-ui.dashboard-module :title="__('AI publishing')" :description="__('Run history and current queue.')" class="sp-overview-module">
    <x-slot:actions><x-ui.button :href="$route" size="sm" wire:navigate>{{ __('Open AI publishing') }}</x-ui.button></x-slot:actions>
    <dl class="sp-overview-metrics"><div><dt>{{ __('Runs') }}</dt><dd>{{ number_format((int)($totalRuns)) }}</dd></div><div><dt>{{ __('Queued') }}</dt><dd>{{ number_format((int)($queuedRuns)) }}</dd></div><div><dt>{{ __('Completed') }}</dt><dd>{{ number_format((int)($completedRuns)) }}</dd></div><div><dt>{{ __('Failed') }}</dt><dd>{{ number_format((int)($failedRuns)) }}</dd></div></dl>
    <x-ui.chart
                        :title="__('AI publishing trend')"
                        :description="__('Daily AI publishing run volume over the last 7 days, with queue and execution context summarized below.')"
                        type="line"
                        :categories="$dailyCategories"
                        :series="$dailySeries"
                        :height="220"
                        :footer-stats="$footerStats"
                    />
</x-ui.dashboard-module>
