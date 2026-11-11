@php
    $dailyCategories = collect($daily ?? [])->map(fn (array $point) => $point['label'] ?? '--')->all();
    $dailyValues = collect($daily ?? [])->map(fn (array $point) => (int) ($point['value'] ?? 0))->values();
    $dailySeries = [[
        'name' => __('Requests'),
        'data' => $dailyValues->all(),
    ]];
    $totalRequests = (int) ($metrics['total_requests'] ?? 0);
    $successRate = (int) ($metrics['success_rate'] ?? 0);
    $successfulRequests = (int) ($metrics['successful_requests'] ?? 0);
    $failedRequests = (int) ($metrics['failed_requests'] ?? 0);
    $totalTokens = (int) ($metrics['total_tokens'] ?? 0);
    $estimatedCost = (float) ($metrics['estimated_cost'] ?? 0);
    $avgLatency = (int) ($metrics['avg_latency'] ?? 0);
    $requests7d = (int) ($metrics['requests_7d'] ?? 0);
    $avgDailyRequests = $dailyValues->isNotEmpty() ? round($dailyValues->avg(), 1) : 0;
    $peakDailyRequests = (int) $dailyValues->max();
    $failureRate = $totalRequests > 0 ? (int) round(($failedRequests / max(1, $totalRequests)) * 100) : 0;
    $tokensPerRequest = $totalRequests > 0 ? (int) round($totalTokens / max(1, $totalRequests)) : 0;
    $costPerRequest = $totalRequests > 0 ? $estimatedCost / max(1, $totalRequests) : 0;
    $latencySeconds = $avgLatency > 0 ? round($avgLatency / 1000, 1) : 0;
    $chartFooterStats = [
        ['label' => __('7-day requests'), 'value' => $requests7d],
        ['label' => __('Success rate'), 'value' => $successRate, 'suffix' => '%'],
        ['label' => __('Failed'), 'value' => $failedRequests],
        ['label' => __('Avg latency'), 'value' => $avgLatency, 'suffix' => 'ms'],
    ];
@endphp
<x-ui.dashboard-module :title="__('AI usage')" :description="__('Request volume, response time, and usage.')" class="sp-overview-module">
    <x-slot:actions><x-ui.button :href="$logsRoute" variant="outline" size="sm" wire:navigate>{{ __('Open logs') }}</x-ui.button>
<x-ui.button :href="$reportRoute" size="sm" wire:navigate>{{ __('Open report') }}</x-ui.button></x-slot:actions>
    <dl class="sp-overview-metrics"><div><dt>{{ __('Requests') }}</dt><dd>{{ number_format((int)($totalRequests)) }}</dd></div><div><dt>{{ __('Successful') }}</dt><dd>{{ number_format((int)($successfulRequests)) }}</dd></div><div><dt>{{ __('Tokens') }}</dt><dd>{{ number_format((int)($totalTokens)) }}</dd></div><div><dt>{{ __('Estimated cost') }}</dt><dd>{{ '$'.number_format($estimatedCost, 4) }}</dd></div></dl>
    <x-ui.chart
                    :title="__('AI request trend')"
                    :description="__('Daily request volume over the last 7 days, with quality and latency summarized below.')"
                    type="line"
                    :categories="$dailyCategories"
                    :series="$dailySeries"
                    :height="220"
                    :footer-stats="$chartFooterStats"
                    class="w-full"
                />
</x-ui.dashboard-module>
