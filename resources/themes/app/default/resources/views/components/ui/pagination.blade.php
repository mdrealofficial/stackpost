@props([
    'current' => 1,
    'last' => 1,
    'baseUrl' => null,
])

@php
    $current = max(1, (int) $current);
    $last = max(1, (int) $last);
    $start = max(1, $current - 2);
    $end = min($last, $current + 2);

    $urlFor = function (int $page) use ($baseUrl) {
        if (! $baseUrl) {
            return '#';
        }

        return str_contains($baseUrl, '?')
            ? $baseUrl.'&page='.$page
            : $baseUrl.'?page='.$page;
    };
@endphp

<nav aria-label="Pagination" {{ $attributes->class('flex items-center gap-2') }}>
    <a href="{{ $urlFor(max(1, $current - 1)) }}" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border text-slate-500 transition hover:text-slate-900 dark:hover:text-white" style="border-color: var(--theme-border-color); background-color: var(--theme-surface-base);">
        <i class="fa-light fa-chevron-left text-xs"></i>
    </a>

    @for ($page = $start; $page <= $end; $page++)
        <a
            href="{{ $urlFor($page) }}"
            class="inline-flex h-10 min-w-10 items-center justify-center rounded-xl px-3 text-sm font-semibold transition {{ $page === $current ? 'bg-[var(--theme-accent)] text-white' : 'border text-slate-600 hover:text-slate-900 dark:hover:text-white' }}"
            @if ($page !== $current) style="border-color: var(--theme-border-color); background-color: var(--theme-surface-base);" @endif
        >
            {{ $page }}
        </a>
    @endfor

    <a href="{{ $urlFor(min($last, $current + 1)) }}" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border text-slate-500 transition hover:text-slate-900 dark:hover:text-white" style="border-color: var(--theme-border-color); background-color: var(--theme-surface-base);">
        <i class="fa-light fa-chevron-right text-xs"></i>
    </a>
</nav>
