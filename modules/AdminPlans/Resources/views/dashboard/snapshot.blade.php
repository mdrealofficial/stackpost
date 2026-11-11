@php
    $totalPlans = (int) ($metrics['total'] ?? 0);
    $activePlans = (int) ($metrics['active'] ?? 0);
    $featuredPlans = (int) ($metrics['featured'] ?? 0);
    $freePlans = (int) ($metrics['free'] ?? 0);
    $inactivePlans = max(0, $totalPlans - $activePlans);
    $activeCoverage = $totalPlans > 0 ? (int) round(($activePlans / max(1, $totalPlans)) * 100) : 0;
    $featuredShare = $totalPlans > 0 ? (int) round(($featuredPlans / max(1, $totalPlans)) * 100) : 0;
    $freeShare = $totalPlans > 0 ? (int) round(($freePlans / max(1, $totalPlans)) * 100) : 0;
    $planSeries = [[
        'name' => __('Plans'),
        'data' => [
            ['name' => __('Active'), 'y' => $activePlans],
            ['name' => __('Inactive'), 'y' => $inactivePlans],
        ],
    ]];
@endphp
<x-ui.dashboard-module :title="__('Plans')" :description="__('Pricing catalog and offer availability.')" class="sp-overview-module">
    <x-slot:actions><x-ui.button :href="$route" size="sm" wire:navigate>{{ __('Open plans') }}</x-ui.button></x-slot:actions>
    <dl class="sp-overview-metrics"><div><dt>{{ __('Total plans') }}</dt><dd>{{ number_format((int)($totalPlans)) }}</dd></div><div><dt>{{ __('Active') }}</dt><dd>{{ number_format((int)($activePlans)) }}</dd></div><div><dt>{{ __('Featured') }}</dt><dd>{{ number_format((int)($featuredPlans)) }}</dd></div><div><dt>{{ __('Free') }}</dt><dd>{{ number_format((int)($freePlans)) }}</dd></div></dl>
    <x-ui.chart
                    :title="__('Plan mix')"
                    :description="__('Active and inactive plans in the catalog.')"
                    type="donut"
                    :series="$planSeries"
                    :height="220"
                    class="w-full"
                />
</x-ui.dashboard-module>
