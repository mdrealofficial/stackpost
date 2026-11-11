@props([
    'align' => 'left',
])

@php
    $alignClass = $align === 'right' ? 'right-0 origin-top-right' : 'left-0 origin-top-left';
@endphp

<div x-data="{ open: false }" class="relative inline-block" {{ $attributes }}>
    <div x-on:click="open = !open">
        {{ $trigger ?? '' }}
    </div>

    <div
        x-cloak
        x-show="open"
        x-transition.origin.top
        x-on:click.outside="open = false"
        class="absolute {{ $alignClass }} z-50 mt-2 min-w-72 rounded-[var(--theme-card-radius,0.9rem)] border p-4 shadow-[0_24px_50px_-24px_rgba(15,23,42,0.28)]"
        style="border-color: var(--theme-border-color); background-color: var(--theme-surface-overlay);"
    >
        {{ $slot }}
    </div>
</div>
