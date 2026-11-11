@props([
    'label' => null,
    'value' => null,
    'description' => null,
    'icon' => null,
    'accent' => 'primary',
    'padding' => 'md',
    'tone' => 'default',
])

@php
    $accentStyles = [
        'primary' => 'background-color: rgba(var(--theme-accent-rgb,37 99 235), 0.12); color: var(--theme-accent);',
        'success' => 'background-color: rgba(var(--theme-success-color-rgb,16 185 129), 0.12); color: var(--theme-success-color);',
        'warning' => 'background-color: rgba(var(--theme-warning-color-rgb,245 158 11), 0.12); color: var(--theme-warning-color);',
        'danger' => 'background-color: rgba(var(--theme-danger-color-rgb,244 63 94), 0.12); color: var(--theme-danger-color);',
        'neutral' => 'background-color: var(--theme-surface-soft); color: var(--theme-header-text-color);',
    ];
@endphp

<x-ui.surface-card
    :padding="$padding"
    :tone="$tone"
    :accent="$accent === 'neutral' ? 'none' : $accent"
    {{ $attributes->class('min-h-[132px]') }}
>
    <div class="flex h-full flex-col">
        @if ($icon)
            <span class="inline-flex h-9 w-9 items-center justify-center rounded-[0.95rem] text-base shadow-[inset_0_1px_0_rgba(255,255,255,0.7)]" style="{{ $accentStyles[$accent] ?? $accentStyles['primary'] }}">
                <i class="{{ $icon }}"></i>
            </span>
        @endif

        @if (!is_null($value) && $value !== '')
            <p class="{{ $icon ? 'mt-3' : '' }} text-[1.65rem] font-semibold tracking-[-0.045em]" style="color: var(--theme-header-text-color);">{{ $value }}</p>
        @endif

        @if ($label)
            <p class="mt-2 text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $label }}</p>
        @endif

        @if ($description)
            <p class="mt-1 text-xs leading-5" style="color: var(--theme-muted-text-color);">{{ $description }}</p>
        @endif

        @if (trim((string) $slot) !== '')
            <div class="mt-4">
                {{ $slot }}
            </div>
        @endif
    </div>
</x-ui.surface-card>
