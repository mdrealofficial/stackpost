@props([
    'eyebrow' => null,
    'title' => null,
    'description' => null,
    'icon' => null,
    'accent' => 'primary',
    'padding' => 'md',
    'tone' => 'default',
])

@php
    $accentStyles = [
        'primary' => 'background-color: rgba(var(--theme-accent-rgb,37 99 235), 0.1); color: var(--theme-accent);',
        'success' => 'background-color: rgba(var(--theme-success-color-rgb,16 185 129), 0.1); color: var(--theme-success-color);',
        'warning' => 'background-color: rgba(var(--theme-warning-color-rgb,245 158 11), 0.1); color: var(--theme-warning-color);',
        'danger' => 'background-color: rgba(var(--theme-danger-color-rgb,244 63 94), 0.1); color: var(--theme-danger-color);',
        'neutral' => 'background-color: var(--theme-surface-soft); color: var(--theme-header-text-color);',
    ];
@endphp

<x-ui.surface-card
    :padding="$padding"
    :tone="$tone"
    :accent="$accent === 'neutral' ? 'none' : $accent"
    {{ $attributes }}
>
    @if ($icon)
        <span class="inline-flex h-11 w-11 items-center justify-center rounded-[0.95rem] text-base" style="{{ $accentStyles[$accent] ?? $accentStyles['primary'] }}">
            <i class="{{ $icon }}"></i>
        </span>
    @endif

    @if ($eyebrow)
        <p class="{{ $icon ? 'mt-5' : '' }} text-[11px] font-semibold uppercase tracking-[0.2em]" style="color: var(--theme-muted-text-color);">{{ $eyebrow }}</p>
    @endif

    @if ($title)
        <h3 class="mt-2 text-[1.35rem] font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">{{ $title }}</h3>
    @endif

    @if ($description)
        <p class="mt-3 text-sm leading-7" style="color: var(--theme-muted-text-color);">{{ $description }}</p>
    @endif

    @if (isset($actions))
        <div class="mt-5 flex flex-wrap gap-3">
            {{ $actions }}
        </div>
    @endif

    @if (trim((string) $slot) !== '')
        <div class="mt-5">
            {{ $slot }}
        </div>
    @endif
</x-ui.surface-card>
