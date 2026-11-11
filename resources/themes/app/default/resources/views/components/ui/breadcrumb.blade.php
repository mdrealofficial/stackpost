@props([
    'items' => [],
])

<nav aria-label="Breadcrumb" {{ $attributes->class('flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400') }}>
    @foreach ($items as $index => $item)
        @php
            $label = $item['label'] ?? '';
            $href = $item['href'] ?? null;
            $isCurrent = $item['current'] ?? ($index === count($items) - 1);
        @endphp

        @if ($index > 0)
            <i class="fa-light fa-chevron-right text-[10px] text-slate-300 dark:text-slate-700"></i>
        @endif

        @if ($href && ! $isCurrent)
            <a href="{{ $href }}" class="transition hover:text-slate-900 dark:hover:text-white">{{ $label }}</a>
        @else
            <span class="{{ $isCurrent ? 'font-semibold text-slate-900 dark:text-white' : '' }}">{{ $label }}</span>
        @endif
    @endforeach
</nav>
