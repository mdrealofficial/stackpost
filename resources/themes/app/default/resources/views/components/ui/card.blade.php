@props([
    'padding' => 'md',
    'tone' => 'default',
])

@php
    $paddingClasses = [
        'none' => '',
        'sm' => 'p-4',
        'md' => 'p-5',
        'lg' => 'p-6',
    ];

    $toneClasses = [
        'default' => 'shadow-none',
        'soft' => 'shadow-none',
        'contrast' => 'shadow-none',
    ];

    $toneStyles = [
        'default' => 'border-width: var(--theme-card-border-width, 1px); border-color: var(--theme-card-border-color, var(--theme-border-color)); background-color: var(--theme-surface-base);',
        'soft' => 'border-width: var(--theme-card-border-width, 1px); border-color: var(--theme-card-border-color, var(--theme-border-color)); background-color: var(--theme-surface-soft);',
        'contrast' => 'border-color: var(--theme-card-contrast-bg); background-color: var(--theme-card-contrast-bg); color: var(--theme-card-contrast-text);',
    ];
@endphp

<div {{ $attributes->merge(['style' => 'border-radius: var(--theme-card-radius, 1rem); '.($toneStyles[$tone] ?? $toneStyles['default'])])->class('app-ui-card min-w-0 max-w-full border '.($toneClasses[$tone] ?? $toneClasses['default']).' '.($paddingClasses[$padding] ?? 'p-6')) }}>
    {{ $slot }}
</div>
