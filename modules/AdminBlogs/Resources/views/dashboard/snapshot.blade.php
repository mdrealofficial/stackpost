@php
    $blogSeries = [[
        'name' => __('Posts'),
        'data' => [
            (int) $metrics['total'],
            (int) $metrics['published'],
            (int) $metrics['drafts'],
            (int) $metrics['recent'],
        ],
    ]];
@endphp
<x-ui.dashboard-module :title="__('Blog publishing')" :description="__('Published articles and work in progress.')" class="sp-overview-module">
    <x-slot:actions><x-ui.button :href="$route" size="sm" wire:navigate>{{ __('Open blog desk') }}</x-ui.button></x-slot:actions>
    <dl class="sp-overview-metrics"><div><dt>{{ __('Articles') }}</dt><dd>{{ number_format((int)($metrics['total'])) }}</dd></div><div><dt>{{ __('Published') }}</dt><dd>{{ number_format((int)($metrics['published'])) }}</dd></div><div><dt>{{ __('Drafts') }}</dt><dd>{{ number_format((int)($metrics['drafts'])) }}</dd></div><div><dt>{{ __('Last 30 days') }}</dt><dd>{{ number_format((int)($metrics['recent'])) }}</dd></div></dl>
    <x-ui.chart
            :title="__('Publishing overview')"
            :description="__('A quick editorial read across total, published, drafts, and recent posts.')"
            type="column"
            :categories="[__('Total'), __('Published'), __('Drafts'), __('Recent')]"
            :series="$blogSeries"
            :height="220"
        />
</x-ui.dashboard-module>
