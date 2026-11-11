@props([
    'attached' => true,
    'size' => 'md',
])

@php
    $groupSizes = [
        'xs' => [
            'height' => '2rem',
            'paddingX' => '0.75rem',
            'fontSize' => '0.8125rem',
            'radius' => '0.75rem',
            'gap' => '0.375rem',
        ],
        'sm' => [
            'height' => '2.25rem',
            'paddingX' => '0.875rem',
            'fontSize' => '0.875rem',
            'radius' => '0.85rem',
            'gap' => '0.5rem',
        ],
        'md' => [
            'height' => '2.5rem',
            'paddingX' => '0.95rem',
            'fontSize' => '0.875rem',
            'radius' => '1rem',
            'gap' => '0.5rem',
        ],
        'lg' => [
            'height' => '2.75rem',
            'paddingX' => '1.1rem',
            'fontSize' => '0.9375rem',
            'radius' => '1.05rem',
            'gap' => '0.625rem',
        ],
        'xl' => [
            'height' => '3rem',
            'paddingX' => '1.25rem',
            'fontSize' => '1rem',
            'radius' => '1.125rem',
            'gap' => '0.75rem',
        ],
    ];

    $resolvedSize = $groupSizes[$size] ?? $groupSizes['md'];
    $inlineStyle = implode('; ', array_filter([
        "--ui-button-group-item-height: {$resolvedSize['height']}",
        "--ui-button-group-item-padding-x: {$resolvedSize['paddingX']}",
        "--ui-button-group-item-font-size: {$resolvedSize['fontSize']}",
        "--ui-button-group-radius: {$resolvedSize['radius']}",
        "--ui-button-group-gap: {$resolvedSize['gap']}",
        $attached ? 'border-color: var(--theme-border-color)' : null,
        $attached ? 'background-color: var(--theme-surface-base)' : null,
        $attributes->get('style'),
    ]));
@endphp

<div
    data-ui-button-group="{{ $attached ? 'attached' : 'free' }}"
    data-ui-button-group-size="{{ array_key_exists($size, $groupSizes) ? $size : 'md' }}"
    {{ $attributes->except('style')->class($attached ? 'inline-flex overflow-hidden rounded-[var(--ui-button-group-radius,var(--theme-button-radius,0.75rem))] border shadow-[0_1px_2px_rgba(15,23,42,0.04)]' : 'inline-flex flex-wrap gap-[var(--ui-button-group-gap,0.5rem)]') }}
    style="{{ $inlineStyle }}"
>
    {{ $slot }}
</div>
