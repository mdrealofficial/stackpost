@props([
    'icon' => null,
    'wrapperClass' => '',
])

@php
    $inputClasses = 'flex h-10 w-full border pr-4 text-[13px] font-medium shadow-[0_1px_2px_rgba(15,23,42,0.04)] outline-none transition duration-200 placeholder:font-normal placeholder:text-[var(--theme-input-placeholder)] focus:border-[var(--theme-accent)] focus:ring-4 focus:ring-[color:rgba(var(--theme-accent-rgb),0.10)]';
    $paddingClass = $icon ? 'pl-10' : 'pl-4';
@endphp

<div class="relative min-w-0 {{ $wrapperClass }}">
    @if ($icon)
        <i class="{{ $icon }} pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-[13px]" style="color: var(--theme-muted-text-color);"></i>
    @endif
    <input
        {{ $attributes->merge(['style' => 'border-radius: var(--theme-input-radius, 0.9rem); border-color: var(--theme-border-color); background-color: var(--theme-input-surface); color: var(--theme-input-text);'])->class($inputClasses.' '.$paddingClass) }}
    >
</div>
