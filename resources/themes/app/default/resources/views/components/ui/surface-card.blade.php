@props([
    'padding' => 'md',
    'tone' => 'default',
    'accent' => 'none',
    'featured' => false,
])

@php
    $accentRails = [
        'none' => null,
        'primary' => 'background-color: var(--theme-accent);',
        'success' => 'background-color: var(--theme-success-color);',
        'warning' => 'background-color: var(--theme-warning-color);',
        'danger' => 'background-color: var(--theme-danger-color);',
    ];
@endphp

<x-ui.card
    :padding="$padding"
    :tone="$tone"
    {{ $attributes->class('relative overflow-hidden') }}
>
    @if ($featured && ($accentRails[$accent] ?? null))
        <div class="pointer-events-none absolute inset-y-0 left-0 w-1" style="{{ $accentRails[$accent] }}"></div>
    @endif

    <div class="relative">
        {{ $slot }}
    </div>
</x-ui.card>
