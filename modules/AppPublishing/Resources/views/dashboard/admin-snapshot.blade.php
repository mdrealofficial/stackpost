@php
    $dailyCategories = collect($daily ?? [])->map(fn (array $point) => $point['label'] ?? '--')->all();
    $dailySeries = [[
        'name' => __('Posts'),
        'data' => collect($daily ?? [])->map(fn (array $point) => (int) ($point['value'] ?? 0))->all(),
    ]];
    $footerStats = [
        ['label' => __('7-day posts'), 'value' => (int) ($metrics['week_total'] ?? 0)],
        ['label' => __('This month'), 'value' => (int) ($metrics['this_month'] ?? 0)],
        ['label' => __('Campaigns'), 'value' => (int) ($metrics['campaigns'] ?? 0)],
        ['label' => __('Labels'), 'value' => (int) ($metrics['labels'] ?? 0)],
    ];
@endphp
<x-ui.dashboard-module :title="__('Publishing')" :description="__('Post volume and publishing outcomes.')" class="sp-overview-module">
    <x-slot:actions><x-ui.button :href="$route" size="sm" wire:navigate>{{ __('Open publishing') }}</x-ui.button></x-slot:actions>
    <dl class="sp-overview-metrics"><div><dt>{{ __('Posts') }}</dt><dd>{{ number_format((int)($metrics['total'])) }}</dd></div><div><dt>{{ __('Published') }}</dt><dd>{{ number_format((int)($metrics['published'])) }}</dd></div><div><dt>{{ __('Scheduled') }}</dt><dd>{{ number_format((int)($metrics['scheduled'])) }}</dd></div><div><dt>{{ __('Failed') }}</dt><dd>{{ number_format((int)($metrics['failed'])) }}</dd></div></dl>
    <x-ui.chart
                        :title="__('Daily publishing trend')"
                        :description="__('Posts created per day over the last 7 days.')"
                        type="line"
                        :categories="$dailyCategories"
                        :series="$dailySeries"
                        :height="220"
                        :footer-stats="$footerStats"
                    />
</x-ui.dashboard-module>
