@props([
    'orientation' => 'horizontal',
])

@if ($orientation === 'vertical')
    <div {{ $attributes->class('mx-1 h-full w-px bg-slate-200 dark:bg-slate-800') }}></div>
@else
    <div {{ $attributes->class('h-px w-full bg-slate-200 dark:bg-slate-800') }}></div>
@endif
