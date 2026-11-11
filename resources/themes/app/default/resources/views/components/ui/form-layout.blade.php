@props([
    'sidebarWidth' => '340px',
    'stickySidebar' => true,
])

@php
    $gridClass = "grid gap-6 xl:grid-cols-[minmax(0,1fr)_{$sidebarWidth}] xl:items-start";
@endphp

<div {{ $attributes->class($gridClass) }}>
    <div class="space-y-4">
        {{ $main ?? $slot }}
    </div>

    @if (isset($sidebar))
        <div @class([
            'space-y-4',
            'xl:sticky xl:top-6' => $stickySidebar,
        ])>
            {{ $sidebar }}
        </div>
    @endif
</div>
