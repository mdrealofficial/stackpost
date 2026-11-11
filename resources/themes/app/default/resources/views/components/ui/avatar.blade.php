@props([
    'src' => null,
    'name' => null,
    'seed' => null,
    'size' => 'md',
    'status' => null,
])

@php
    $sizes = [
        'xs' => 'h-8 w-8 text-xs',
        'sm' => 'h-9 w-9 text-sm',
        'md' => 'h-11 w-11 text-sm',
        'lg' => 'h-14 w-14 text-base',
        'xl' => 'h-16 w-16 text-lg',
    ];

    $statusClasses = [
        'online' => 'bg-emerald-500',
        'away' => 'bg-amber-400',
        'busy' => 'bg-rose-500',
        'offline' => 'bg-slate-300 dark:bg-slate-600',
    ];

    $fallbackTones = [
        'border-[#bfdbfe] bg-[#dbeafe] text-[#1d4ed8]',
        'border-[#c7d2fe] bg-[#e0e7ff] text-[#4338ca]',
        'border-[#ddd6fe] bg-[#ede9fe] text-[#7c3aed]',
        'border-[#f5d0fe] bg-[#fae8ff] text-[#a21caf]',
        'border-[#fecdd3] bg-[#ffe4e6] text-[#e11d48]',
        'border-[#fed7aa] bg-[#ffedd5] text-[#ea580c]',
        'border-[#fde68a] bg-[#fef3c7] text-[#ca8a04]',
        'border-[#bbf7d0] bg-[#dcfce7] text-[#16a34a]',
        'border-[#a7f3d0] bg-[#d1fae5] text-[#0f766e]',
        'border-[#bae6fd] bg-[#e0f2fe] text-[#0369a1]',
    ];

    $initials = collect(explode(' ', trim((string) $name)))
        ->filter()
        ->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');

    $fallbackSeed = (string) ($seed ?: $name ?: 'avatar');
    $toneIndex = (int) (sprintf('%u', crc32($fallbackSeed)) % count($fallbackTones));
    $fallbackTone = $fallbackTones[$toneIndex];
@endphp

<span {{ $attributes->class('relative inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full border font-semibold '.($src ? 'border-slate-200 bg-slate-100 text-slate-700 dark:border-slate-800 dark:bg-slate-800 dark:text-slate-200' : $fallbackTone).' '.($sizes[$size] ?? $sizes['md'])) }}>
    @if ($src)
        <img
            src="{{ $src }}"
            alt="{{ $name ?? 'Avatar' }}"
            class="h-full w-full object-cover"
            loading="eager"
            decoding="async"
            x-data
            x-on:error="$el.hidden = true; $el.nextElementSibling.style.display = 'flex'"
        >
        <span style="display: none;" class="h-full w-full items-center justify-center">{{ $initials ?: 'A' }}</span>
    @else
        <span class="h-full w-full items-center justify-center flex">{{ $initials ?: 'A' }}</span>
    @endif

    @if ($status)
        <span class="absolute bottom-0 right-0 h-3.5 w-3.5 rounded-full border-2 border-white dark:border-slate-900 {{ $statusClasses[$status] ?? $statusClasses['offline'] }}"></span>
    @endif
</span>
