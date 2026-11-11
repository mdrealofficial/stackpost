@props([
    'title' => null,
    'description' => null,
    'bodyClass' => 'p-5',
    'cardClass' => '',
    'headerClass' => '',
])

<x-ui.card class="border border-slate-200/80 shadow-none dark:border-slate-800 {{ $cardClass }}" padding="none">
    @if ($title || $description || isset($meta))
        <div class="flex items-start justify-between gap-4 border-b border-slate-200/80 px-5 py-4 dark:border-slate-800 {{ $headerClass }}">
            <div class="min-w-0">
                @if ($title)
                    <h3 class="text-[0.98rem] font-semibold tracking-[-0.01em] text-slate-950 dark:text-white">{{ $title }}</h3>
                @endif

                @if ($description)
                    <p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">{{ $description }}</p>
                @endif
            </div>

            @isset($meta)
                <div class="shrink-0">
                    {{ $meta }}
                </div>
            @endisset
        </div>
    @endif

    <div class="{{ $bodyClass }}">
        {{ $slot }}
    </div>
</x-ui.card>
