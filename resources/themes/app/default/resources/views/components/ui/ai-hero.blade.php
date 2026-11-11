@props([
    'eyebrow' => null,
    'title',
    'description' => null,
    'icon' => 'fa-light fa-wand-magic-sparkles',
    'panelLabel' => null,
    'panelTitle' => null,
    'panelDescription' => null,
    'panelBadge' => null,
    'panelBadgeVariant' => 'neutral',
    'metrics' => [],
])

<x-ui.card padding="none" class="overflow-hidden shadow-none" style="border-color: var(--theme-card-border-color, var(--theme-border-color)); background-color: var(--theme-surface-base);">
    <div class="grid gap-5 px-4 py-4 sm:px-5 lg:grid-cols-[minmax(0,1fr)_minmax(24rem,34rem)] lg:items-center lg:px-6 lg:py-5">
        <div class="flex min-w-0 items-start gap-3 sm:gap-4">
            <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-[0.85rem] border" style="border-color: rgba(var(--theme-accent-rgb), 0.20); background: rgba(var(--theme-accent-rgb), 0.08); color: var(--theme-accent);">
                <i class="{{ $icon }} text-lg"></i>
            </span>

            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-2 rounded-lg border px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.20em]" style="border-color: rgba(var(--theme-border-color-rgb), 0.52); background: rgba(var(--theme-surface-overlay-rgb), 0.78); color: var(--theme-muted-text-color);">
                        {{ $eyebrow ?: __('AI Studio') }}
                    </span>
                    @if ($panelLabel)
                        <span class="text-xs font-medium" style="color: var(--theme-muted-text-color);">{{ $panelLabel }}</span>
                    @endif
                </div>

                <h1 class="mt-3 text-2xl font-semibold tracking-[-0.04em] sm:text-3xl" style="color: var(--theme-header-text-color);">{{ $title }}</h1>
                @if ($description)
                    <p class="mt-2 max-w-3xl text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ $description }}</p>
                @endif

                @if (trim((string) $slot) !== '')
                    <div class="mt-4 flex flex-wrap gap-3">
                        {{ $slot }}
                    </div>
                @endif
            </div>
        </div>

        <div class="min-w-0 rounded-[1rem] border p-3" style="border-color: var(--theme-divider-border-color, var(--theme-border-color)); background-color: var(--theme-surface-soft);">
            @if ($panelTitle || $panelDescription || $panelBadge)
                <div class="mb-3 flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        @if ($panelTitle)
                            <p class="truncate text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $panelTitle }}</p>
                        @endif
                        @if ($panelDescription)
                            <p class="mt-1 line-clamp-1 text-xs" style="color: var(--theme-muted-text-color);">{{ $panelDescription }}</p>
                        @endif
                    </div>
                    @if ($panelBadge)
                        <x-ui.badge :variant="$panelBadgeVariant">{{ $panelBadge }}</x-ui.badge>
                    @endif
                </div>
            @endif

            @if (!empty($metrics))
                <div class="grid gap-2 sm:grid-cols-3">
                    @foreach ($metrics as $metric)
                        <div class="min-w-0 rounded-[0.8rem] border px-3 py-2.5" style="border-color: rgba(var(--theme-border-color-rgb), 0.50); background: rgba(var(--theme-surface-base-rgb), 0.62);">
                            <p class="truncate text-[10px] font-semibold uppercase tracking-[0.14em]" style="color: var(--theme-muted-text-color);">{{ $metric['label'] ?? '' }}</p>
                            <p class="mt-1 truncate text-sm font-semibold sm:text-base" style="color: var(--theme-header-text-color);">{{ $metric['value'] ?? '' }}</p>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-ui.card>
