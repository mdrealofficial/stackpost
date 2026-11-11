@props([
    'value' => 0,
    'max' => 100,
    'label' => null,
])

@php
    $percentage = $max > 0 ? min(100, max(0, ($value / $max) * 100)) : 0;
@endphp

<div {{ $attributes->class('space-y-2') }}>
    @if ($label)
        <div class="flex items-center justify-between gap-4 text-sm">
            <span class="font-medium" style="color: var(--theme-header-text-color);">{{ $label }}</span>
            <span style="color: var(--theme-muted-text-color);">{{ number_format($percentage, 0) }}%</span>
        </div>
    @endif

    <div class="h-2.5 overflow-hidden rounded-full" style="background-color: var(--theme-surface-subtle);">
        <div class="h-full rounded-full bg-[var(--theme-accent)] transition-all duration-300" style="width: {{ $percentage }}%"></div>
    </div>
</div>
