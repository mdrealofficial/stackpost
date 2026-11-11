@props([
    'href' => null,
    'type' => null,
    'variant' => 'primary',
    'size' => 'md',
    'block' => false,
    'multiline' => false,
])

@php
    $buttonStyle = theme_setting('button_style', 'app', 'solid');
    $primaryVariant = match ($buttonStyle) {
        'soft' => 'border border-[color:rgba(var(--theme-accent-rgb),0.16)] bg-[color:rgba(var(--theme-accent-rgb),0.12)] text-[var(--theme-accent)] hover:bg-[color:rgba(var(--theme-accent-rgb),0.18)]',
        'outline' => 'border border-[var(--theme-accent)] bg-transparent text-[var(--theme-accent)] hover:bg-[color:rgba(var(--theme-accent-rgb),0.06)]',
        default => 'border border-[var(--theme-accent)] bg-[var(--theme-accent)] text-white hover:opacity-95',
    };
    $primaryShadow = 'shadow-none';

    $base = 'app-ui-button inline-flex cursor-pointer items-center justify-center gap-2 font-semibold tracking-[-0.01em] transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-[color:rgba(var(--theme-accent-rgb),0.18)] focus:ring-offset-2 focus:ring-offset-[var(--theme-app-canvas)] disabled:pointer-events-none disabled:opacity-50';

    $variants = [
        'primary' => $primaryVariant.' '.$primaryShadow,
        'secondary' => 'border text-[var(--theme-button-soft-text)] shadow-none hover:bg-[var(--theme-button-soft-hover)]',
        'outline' => 'border bg-transparent shadow-none hover:bg-[var(--theme-button-outline-hover)]',
        'ghost' => 'text-slate-600 hover:bg-slate-100 hover:text-slate-950 dark:text-slate-300 dark:hover:bg-slate-900 dark:hover:text-white',
        'success' => 'border border-emerald-600 bg-emerald-600 text-white shadow-none hover:border-emerald-500 hover:bg-emerald-500 dark:border-emerald-500 dark:bg-emerald-500 dark:hover:bg-emerald-400',
        'warning' => 'border border-amber-500 bg-amber-500 text-slate-950 shadow-none hover:border-amber-400 hover:bg-amber-400 dark:border-amber-400 dark:bg-amber-400 dark:hover:bg-amber-300',
        'info' => 'border border-sky-600 bg-sky-600 text-white shadow-none hover:border-sky-500 hover:bg-sky-500 dark:border-sky-500 dark:bg-sky-500 dark:hover:bg-sky-400',
        'danger' => 'border border-rose-600 bg-rose-600 text-white shadow-none hover:border-rose-500 hover:bg-rose-500 dark:border-rose-500 dark:bg-rose-500 dark:hover:bg-rose-400',
    ];

    $sizes = [
        'sm' => 'h-9 px-3.5 text-sm',
        'md' => 'h-10 px-4.5 text-sm',
        'lg' => 'h-11 px-5 text-[15px]',
    ];

    $classes = implode(' ', [
        $base,
        $multiline ? 'min-w-0 max-w-full whitespace-normal break-words' : 'whitespace-nowrap',
        $variants[$variant] ?? $variants['primary'],
        $sizes[$size] ?? $sizes['md'],
        $block ? 'w-full' : '',
    ]);

    $styles = match ($variant) {
        'secondary' => 'border-radius: var(--theme-button-radius, 0.75rem); border-color: var(--theme-border-color); background-color: var(--theme-button-soft-bg); color: var(--theme-button-soft-text);',
        'outline' => '--button-outline-text: var(--theme-header-text-color); --button-outline-bg: transparent; --button-outline-hover-bg: var(--theme-button-outline-hover); --button-outline-border: var(--theme-border-color); --button-outline-hover-border: rgba(var(--theme-accent-rgb), 0.35); border-radius: var(--theme-button-radius, 0.75rem); border-color: var(--theme-border-color); color: var(--theme-header-text-color); background-color: transparent;',
        default => 'border-radius: var(--theme-button-radius, 0.75rem);',
    };
    if ($multiline) {
        $styles .= ' height: auto; min-height: 2.5rem; white-space: normal; overflow-wrap: anywhere;';
    }
@endphp

@if ($href)
    <a href="{{ $href }}" data-variant="{{ $variant }}" data-button-style="{{ $buttonStyle }}"
       {{ $attributes->merge(['style' => $styles])->class($classes) }}>
        {{ $slot }}
    </a>
@else
    <button data-variant="{{ $variant }}" data-button-style="{{ $buttonStyle }}"
        @if ($type !== null) type="{{ $type }}" @endif
        {{ $attributes->merge(['style' => $styles])->class($classes) }}>
        {{ $slot }}
    </button>
@endif
