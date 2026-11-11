@props([
    'size' => 'md',
    'tone' => 'accent',
])

@php
    $sizes = [
        'sm' => 'h-4 w-4 border-2',
        'md' => 'h-5 w-5 border-2',
        'lg' => 'h-7 w-7 border-[3px]',
    ];
    $tones = [
        'accent' => 'border-[color:rgba(var(--theme-accent-rgb),0.18)] border-t-[var(--theme-accent)]',
        'slate' => 'border-[color:var(--theme-border-color)] border-t-[color:var(--theme-muted-text-color)]',
        'white' => 'border-white/25 border-t-white',
    ];
@endphp

<span {{ $attributes->class('inline-block animate-spin rounded-full '.($sizes[$size] ?? $sizes['md']).' '.($tones[$tone] ?? $tones['accent'])) }}></span>
