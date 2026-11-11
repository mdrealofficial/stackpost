@props([
    'variant' => 'neutral',
])

@php
    $variants = [
        'neutral' => 'border-slate-200 bg-slate-50 text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200',
        'primary' => 'border-[color:rgba(var(--theme-accent-rgb),0.24)] bg-[color:rgba(var(--theme-accent-rgb),0.10)] text-[var(--theme-accent)] dark:border-[color:rgba(var(--theme-accent-rgb),0.24)] dark:bg-[color:rgba(var(--theme-accent-rgb),0.16)] dark:text-white',
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300',
        'danger' => 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-300',
    ];
@endphp

<span {{ $attributes->class('inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] '.($variants[$variant] ?? $variants['neutral'])) }}>
    {{ $slot }}
</span>
