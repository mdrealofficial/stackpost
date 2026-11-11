@props([
    'uppercase' => false,
])

<div
    {{ $attributes->class(
        ($uppercase
            ? 'px-3 pb-1 pt-2 text-[11px] font-semibold uppercase tracking-[0.14em]'
            : 'px-3 pb-1 pt-2 text-[13px] font-medium')
        .' text-slate-400'
    ) }}
>
    {{ $slot }}
</div>
