@props([
    'itemKey',
    'title',
    'description' => null,
    'statusLabel' => null,
    'statusVariant' => 'neutral',
    'defaultOpen' => false,
    'bodyClass' => 'p-6',
])

<x-ui.card
    padding="none"
    class="border border-slate-200/80 shadow-none dark:border-slate-800"
>
    <details
        class="group"
        @if ($defaultOpen) open @endif
        x-bind:open="activePaymentGateway === @js((string) $itemKey)"
    >
        <summary
            class="flex w-full cursor-pointer list-none items-start justify-between gap-4 border-b border-slate-200/80 px-5 py-4 text-left transition hover:bg-slate-50/70 dark:border-slate-800 dark:hover:bg-slate-950/40"
            x-on:click.prevent="activePaymentGateway = activePaymentGateway === @js((string) $itemKey) ? null : @js((string) $itemKey)"
        >
            <div class="min-w-0">
                <h3 class="text-[0.98rem] font-semibold tracking-[-0.01em] text-slate-950 dark:text-white">
                    {{ $title }}
                </h3>

                @if ($description)
                    <p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">
                        {{ $description }}
                    </p>
                @endif
            </div>

            <div class="flex shrink-0 items-center gap-3">
                @if ($statusLabel)
                    <x-ui.badge :variant="$statusVariant">
                        {{ $statusLabel }}
                    </x-ui.badge>
                @endif

                <span
                    class="inline-flex h-8 w-8 items-center justify-center rounded-full border text-slate-500 transition group-open:rotate-180 group-open:text-slate-700 dark:group-open:text-slate-200"
                    style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color);"
                >
                    <i class="fa-light fa-chevron-down text-xs"></i>
                </span>
            </div>
        </summary>

        <div class="{{ $bodyClass }}">
            {{ $slot }}
        </div>
    </details>
</x-ui.card>
