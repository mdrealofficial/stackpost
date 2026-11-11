@props([
    'label' => null,
    'help' => null,
    'error' => null,
    'attached' => true,
])

@php
    $groupClasses = $attached
        ? 'flex w-full items-stretch overflow-hidden rounded-[var(--theme-input-radius,0.95rem)] border shadow-[0_1px_2px_rgba(15,23,42,0.04)]'
        : 'flex w-full flex-wrap gap-2';
@endphp

<x-ui.field :label="$label" :help="$help" :error="$error" {{ $attributes->only('class') }}>
    <div
        {{ $attributes->except('class')->merge(['style' => 'border-color: var(--theme-border-color); background-color: var(--theme-input-surface); color: var(--theme-input-text);'])->class($groupClasses) }}
    >
        {{ $slot }}
    </div>
</x-ui.field>
