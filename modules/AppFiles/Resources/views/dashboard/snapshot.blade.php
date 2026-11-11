@php
    $storageLabel = $metrics['storage_bytes'] >= 1073741824
        ? number_format($metrics['storage_bytes'] / 1073741824, 2).' GB'
        : ($metrics['storage_bytes'] >= 1048576
            ? number_format($metrics['storage_bytes'] / 1048576, 2).' MB'
            : number_format($metrics['storage_bytes'] / 1024, 2).' KB');

    $otherFiles = max(0, (int) $metrics['total'] - (int) $metrics['folders'] - (int) $metrics['images']);
    $fileSeries = [[
        'name' => __('Assets'),
        'data' => [
            ['name' => __('Folders'), 'y' => (int) $metrics['folders']],
            ['name' => __('Images'), 'y' => (int) $metrics['images']],
            ['name' => __('Other'), 'y' => $otherFiles],
        ],
    ]];
@endphp
<x-ui.dashboard-module :title="__('Media library')" :description="__('Storage usage and uploaded assets.')" class="sp-overview-module">
    <x-slot:actions><x-ui.button :href="$route" size="sm" wire:navigate>{{ __('Open files') }}</x-ui.button></x-slot:actions>
    <dl class="sp-overview-metrics"><div><dt>{{ __('Storage') }}</dt><dd>{{ $storageLabel }}</dd></div><div><dt>{{ __('Entries') }}</dt><dd>{{ number_format((int)($metrics['total'])) }}</dd></div><div><dt>{{ __('Images') }}</dt><dd>{{ number_format((int)($metrics['images'])) }}</dd></div><div><dt>{{ __('Folders') }}</dt><dd>{{ number_format((int)($metrics['folders'])) }}</dd></div></dl>
    <x-ui.chart
            :title="__('Asset mix')"
            :description="__('Folder, image, and other file distribution inside the workspace.')"
            type="donut"
            :series="$fileSeries"
            :height="220"
        />
</x-ui.dashboard-module>
