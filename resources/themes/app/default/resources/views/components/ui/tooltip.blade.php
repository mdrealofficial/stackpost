@props([
    'text' => null,
    'placement' => 'top',
    'maxWidth' => '18rem',
])

@php
    $placementClasses = match ($placement) {
        'bottom' => 'top-full mt-2 left-1/2 -translate-x-1/2',
        'left' => 'right-full mr-2 top-1/2 -translate-y-1/2',
        'right' => 'left-full ml-2 top-1/2 -translate-y-1/2',
        default => 'bottom-full mb-2 left-1/2 -translate-x-1/2',
    };

    $arrowClasses = match ($placement) {
        'bottom' => '-top-1 left-1/2 -translate-x-1/2',
        'left' => '-right-1 top-1/2 -translate-y-1/2',
        'right' => '-left-1 top-1/2 -translate-y-1/2',
        default => '-bottom-1 left-1/2 -translate-x-1/2',
    };

    $tooltipContent = trim((string) ($text ?? ''));
@endphp

<div
    x-data="{ open: false }"
    class="relative inline-flex"
    x-on:mouseenter="open = true"
    x-on:mouseleave="open = false"
    x-on:focusin="open = true"
    x-on:focusout="open = false"
    {{ $attributes->except(['text', 'placement', 'maxWidth']) }}
>
    {{ $trigger ?? '' }}

    @if ($tooltipContent !== '' || trim((string) $slot) !== '')
        <div
            x-cloak
            x-show="open"
            x-transition.opacity.duration.120ms
            class="pointer-events-none absolute {{ $placementClasses }} z-[80]"
            style="max-width: {{ $maxWidth }};"
        >
            <div
                class="relative rounded-[0.8rem] px-3 py-2 text-[0.82rem] font-medium leading-5 text-white shadow-[0_18px_40px_-20px_rgba(15,23,42,0.55)]"
                style="background-color: rgba(15, 15, 18, 0.96); white-space: pre-line;"
            >
                <span class="absolute h-2.5 w-2.5 rotate-45 rounded-[2px] {{ $arrowClasses }}" style="background-color: rgba(15, 15, 18, 0.96);"></span>
                @if ($tooltipContent !== '')
                    {{ $tooltipContent }}
                @else
                    {{ $slot }}
                @endif
            </div>
        </div>
    @endif
</div>
