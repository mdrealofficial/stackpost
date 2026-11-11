@props([
    'title' => null,
    'description' => null,
    'icon' => 'fa-light fa-sliders',
    'hero' => false,
    'collapsible' => false,
    'open' => true,
])

@php
    $panelStyle = $hero
        ? 'border-color: var(--theme-card-border-color, var(--theme-border-color)); background-color: var(--theme-surface-soft);'
        : 'border-color: var(--theme-border-color); background-color: color-mix(in srgb, var(--theme-surface-base) 92%, transparent);';
@endphp

<div
    {{ $attributes->merge(['style' => $panelStyle])->class('rounded-[1.4rem] border p-5') }}
    @if ($collapsible)
        x-data="{ open: @js($open) }"
    @endif
>
    @if ($title || $description)
        @if ($collapsible)
            <button type="button" class="flex w-full items-start gap-3 text-left" x-on:click="open = !open">
                <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl" style="background-color: rgba(var(--theme-accent-rgb), 0.12); color: var(--theme-accent);">
                    <i class="{{ $icon }}"></i>
                </span>
                <div class="min-w-0 flex-1">
                    @if ($title)
                        <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $title }}</p>
                    @endif
                    @if ($description)
                        <p class="mt-1 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ $description }}</p>
                    @endif
                </div>
                <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full border transition" style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color); background-color: color-mix(in srgb, var(--theme-surface-base) 96%, transparent);">
                    <i class="fa-light fa-chevron-down text-xs transition" x-bind:style="open ? 'transform: rotate(180deg);' : ''"></i>
                </span>
            </button>
        @else
            <div class="flex items-start gap-3">
                <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl" style="background-color: rgba(var(--theme-accent-rgb), 0.12); color: var(--theme-accent);">
                    <i class="{{ $icon }}"></i>
                </span>
                <div class="min-w-0">
                    @if ($title)
                        <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $title }}</p>
                    @endif
                    @if ($description)
                        <p class="mt-1 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ $description }}</p>
                    @endif
                </div>
            </div>
        @endif
    @endif

    @if (trim((string) $slot) !== '')
        <div
            @class([
                'space-y-4',
                'mt-5' => $title || $description,
            ])
            @if ($collapsible)
                x-cloak
                x-show="open"
                x-transition.opacity.duration.150ms
            @endif
        >
            {{ $slot }}
        </div>
    @endif
</div>
