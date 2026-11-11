@props([
    'label' => null,
    'help' => null,
    'error' => null,
])

<x-ui.field :label="$label" :help="$help" :error="$error" {{ $attributes->only('class') }}>
    <div class="flex items-stretch overflow-hidden rounded-[var(--theme-input-radius,0.75rem)] border shadow-[0_1px_2px_rgba(15,23,42,0.04)]" style="border-color: var(--theme-border-color); background-color: var(--theme-input-surface); color: var(--theme-input-text);">
        @isset($prefix)
            <div class="inline-flex items-center border-r px-3 text-sm" style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color);">
                {{ $prefix }}
            </div>
        @endisset

        <div class="min-w-0 flex-1">
            {{ $slot }}
        </div>

        @isset($suffix)
            <div class="inline-flex items-center border-l px-3 text-sm" style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color);">
                {{ $suffix }}
            </div>
        @endisset
    </div>
</x-ui.field>
