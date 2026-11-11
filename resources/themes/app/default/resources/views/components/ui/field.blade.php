@props([
    'label' => null,
    'help' => null,
    'error' => null,
])

<div {{ $attributes->only('class')->class('space-y-2.5') }}>
    @if ($label)
        <x-ui.label>{{ $label }}</x-ui.label>
    @endif

    {{ $slot }}

    @if ($help)
        <p class="text-sm" style="color: var(--theme-muted-text-color);">{{ $help }}</p>
    @endif

    @if ($error)
        <p class="text-sm font-medium" style="color: var(--theme-danger-color);">{{ $error }}</p>
    @endif
</div>
