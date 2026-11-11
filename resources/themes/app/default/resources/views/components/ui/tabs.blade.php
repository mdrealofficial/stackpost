@props([
    'items' => [],
    'default' => null,
])

@php
    $initial = $default ?? ($items[0]['key'] ?? 'tab-1');
@endphp

<div x-data="{ active: @js((string) $initial) }" {{ $attributes }}>
    <div class="inline-flex rounded-[var(--theme-card-radius,0.9rem)] border border-slate-200 bg-white p-1 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        @foreach ($items as $item)
            @php
                $key = (string) ($item['key'] ?? '');
                $label = $item['label'] ?? $key;
            @endphp
            <button
                type="button"
                class="rounded-[calc(var(--theme-card-radius,0.9rem)-6px)] px-4 py-2 text-sm font-semibold transition"
                x-on:click="active = @js($key)"
                x-bind:class="active === @js($key) ? 'bg-slate-950 text-white dark:bg-white dark:text-slate-950' : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'"
            >
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div class="mt-4">
        @foreach ($items as $item)
            @php
                $key = (string) ($item['key'] ?? '');
            @endphp
            <div x-cloak x-show="active === @js($key)">
                {!! $item['content'] ?? '' !!}
            </div>
        @endforeach
    </div>
</div>
