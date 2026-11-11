@props([
    'cancelHref' => null,
    'cancelLabel' => null,
    'submitLabel' => null,
])

<div {{ $attributes->merge(['style' => 'border-color: var(--theme-border-color);'])->class('flex items-center justify-end gap-3 border-t pt-5') }}>
    @if ($cancelHref)
        <x-ui.button :href="$cancelHref" variant="outline" wire:navigate>{{ $cancelLabel ?: __('Cancel') }}</x-ui.button>
    @endif

    @if (isset($before))
        {{ $before }}
    @endif

    @if ($submitLabel)
        <x-ui.button type="submit">{{ $submitLabel }}</x-ui.button>
    @endif

    @if (isset($slot) && trim((string) $slot) !== '')
        {{ $slot }}
    @endif
</div>
