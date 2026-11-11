@props([
    'href' => null,
    'type' => 'button',
    'icon' => null,
    'trailing' => null,
    'trailingIcon' => null,
    'variant' => 'default',
    'close' => true,
])

@php
    $tag = $href ? 'a' : 'button';
    $variantClasses = match ($variant) {
        'muted' => 'cursor-default hover:bg-transparent',
        'danger' => '',
        default => '',
    };
    $baseClasses = 'flex w-full items-center gap-3 whitespace-nowrap rounded-[0.8rem] px-3 py-2 text-left text-[15px] font-medium leading-6 transition';
    $clickToClose = $close ? 'open = false' : null;
    $baseStyle = match ($variant) {
        'muted' => 'color: var(--theme-muted-text-color);',
        'danger' => 'color: var(--theme-danger-color);',
        default => 'color: var(--theme-header-text-color);',
    };
    $hoverStyle = match ($variant) {
        'muted' => 'background-color: transparent; color: var(--theme-muted-text-color);',
        'danger' => 'background-color: rgba(244, 63, 94, 0.08); color: var(--theme-danger-color);',
        default => 'background-color: var(--theme-surface-soft); color: var(--theme-header-text-color);',
    };
    $iconStyle = match ($variant) {
        'muted' => 'color: var(--theme-muted-text-color);',
        'danger' => 'color: var(--theme-danger-color);',
        default => 'color: var(--theme-muted-text-color);',
    };
@endphp

<{{ $tag }}
    @if ($href)
        href="{{ $href }}"
    @else
        type="{{ $type }}"
    @endif
    {{ $attributes->merge(['style' => $baseStyle])->class($baseClasses.' '.$variantClasses) }}
    @if ($clickToClose)
        x-on:click.stop="{{ $clickToClose }}"
    @endif
    x-data="{ hover: false }"
    x-on:mouseenter="hover = true"
    x-on:mouseleave="hover = false"
    x-bind:style="hover ? '{{ $hoverStyle }}' : '{{ $baseStyle }}'"
>
    @if ($icon)
        <i class="{{ $icon }} text-[14px]" style="{{ $iconStyle }}"></i>
    @endif

    <span class="min-w-0 flex-1 truncate">{{ $slot }}</span>

    @if ($trailing)
        <span class="pl-4 text-[14px] font-medium tracking-[0.02em]" style="color: var(--theme-muted-text-color);">{{ $trailing }}</span>
    @endif

    @if ($trailingIcon)
        <i class="{{ $trailingIcon }} pl-4 text-[13px]" style="color: var(--theme-muted-text-color);"></i>
    @endif
</{{ $tag }}>
