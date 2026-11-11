@props([
    'title' => null,
    'description' => null,
    'compact' => false,
])

@php
    $hasSelection = isset($selection) && trim((string) $selection) !== '';
    $hasChips = isset($chips) && trim((string) $chips) !== '';
@endphp

@if ($compact)
    <div class="rounded-[1rem] border px-4 py-3" style="border-color: color-mix(in srgb, var(--theme-border-color) 84%, transparent); background-color: color-mix(in srgb, var(--theme-surface-base) 96%, transparent);">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            @if ($hasChips)
                <div class="flex min-w-0 flex-wrap items-center gap-2">
                    {{ $chips }}
                </div>
            @endif

            @if ($hasSelection)
                <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                    {{ $selection }}
                </div>
            @endif
        </div>
    </div>
@else
    <x-ui.card class="overflow-hidden border p-0" style="border-color: var(--theme-card-border-color, var(--theme-border-color)); background-color: var(--theme-surface-base);">
        <div class="grid gap-4 px-6 py-5 lg:grid-cols-[minmax(0,1.4fr)_minmax(18rem,0.9fr)] lg:items-center">
            <div class="space-y-3">
                @if ($title || $description)
                    <div class="space-y-1">
                        @if ($title)
                            <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $title }}</p>
                        @endif
                        @if ($description)
                            <p class="text-sm" style="color: var(--theme-muted-text-color);">{{ $description }}</p>
                        @endif
                    </div>
                @endif

                @if ($hasChips)
                    <div class="flex flex-wrap gap-2">
                        {{ $chips }}
                    </div>
                @endif
            </div>

            @if ($hasSelection)
                <div class="rounded-[1.15rem] border px-4 py-4" style="border-color: color-mix(in srgb, var(--theme-border-color) 88%, transparent); background-color: color-mix(in srgb, var(--theme-surface-base) 92%, transparent);">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        {{ $selection }}
                    </div>
                </div>
            @endif
        </div>
    </x-ui.card>
@endif
