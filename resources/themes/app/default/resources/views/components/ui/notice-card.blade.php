@props([
    'title' => null,
    'description' => null,
    'variant' => 'info',
    'icon' => null,
])

@php
    $variants = [
        'info' => [
            'card' => 'border-color: rgba(var(--theme-accent-rgb,37 99 235),0.18); background-color: rgba(var(--theme-accent-rgb,37 99 235),0.06);',
            'icon' => 'background-color: rgba(var(--theme-accent-rgb,37 99 235),0.12); color: var(--theme-accent);',
            'title' => 'color: var(--theme-header-text-color);',
            'text' => 'color: var(--theme-muted-text-color);',
        ],
        'success' => [
            'card' => 'border-color: rgba(var(--theme-success-color-rgb,16 185 129),0.22); background-color: rgba(var(--theme-success-color-rgb,16 185 129),0.08);',
            'icon' => 'background-color: rgba(var(--theme-success-color-rgb,16 185 129),0.14); color: var(--theme-success-color);',
            'title' => 'color: var(--theme-header-text-color);',
            'text' => 'color: var(--theme-muted-text-color);',
        ],
        'warning' => [
            'card' => 'border-color: rgba(var(--theme-warning-color-rgb,245 158 11),0.22); background-color: rgba(var(--theme-warning-color-rgb,245 158 11),0.08);',
            'icon' => 'background-color: rgba(var(--theme-warning-color-rgb,245 158 11),0.14); color: var(--theme-warning-color);',
            'title' => 'color: var(--theme-header-text-color);',
            'text' => 'color: var(--theme-muted-text-color);',
        ],
        'danger' => [
            'card' => 'border-color: rgba(var(--theme-danger-color-rgb,244 63 94),0.22); background-color: rgba(var(--theme-danger-color-rgb,244 63 94),0.08);',
            'icon' => 'background-color: rgba(var(--theme-danger-color-rgb,244 63 94),0.14); color: var(--theme-danger-color);',
            'title' => 'color: var(--theme-header-text-color);',
            'text' => 'color: var(--theme-muted-text-color);',
        ],
    ];

    $styles = $variants[$variant] ?? $variants['info'];
@endphp

<div {{ $attributes->class('flex items-start gap-3 rounded-[1rem] border p-5')->merge(['style' => $styles['card']]) }}>
    <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full" style="{{ $styles['icon'] }}">
        <i class="{{ $icon ?: 'fa-light fa-circle-info' }}"></i>
    </span>

    <div class="min-w-0">
        @if ($title)
            <p class="text-sm font-semibold" style="{{ $styles['title'] }}">{{ $title }}</p>
        @endif

        @if ($description)
            <p class="mt-1 text-sm leading-6" style="{{ $styles['text'] }}">{{ $description }}</p>
        @endif

        @if (trim((string) $slot) !== '')
            <div class="mt-3">
                {{ $slot }}
            </div>
        @endif
    </div>
</div>
