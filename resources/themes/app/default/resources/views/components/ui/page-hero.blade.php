@props([
    'eyebrow' => null,
    'title' => null,
    'description' => null,
    'count' => null,
    'icon' => 'fa-light fa-layer-group',
    'iconStyle' => null,
    'panelStyle' => null,
])

@php
    $resolvedPanelStyle = $panelStyle ?: 'border-color: var(--theme-card-border-color, var(--theme-border-color)); background-color: var(--theme-surface-base);';

    $resolvedIconStyle = $iconStyle ?: 'background-color: rgba(var(--theme-accent-rgb), 0.14); color: var(--theme-accent);';
@endphp

<x-ui.card class="app-page-hero overflow-hidden shadow-none" style="{{ $resolvedPanelStyle }}">
    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
        <div class="flex items-start gap-4">
            <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-base" style="{{ $resolvedIconStyle }}">
                <i class="{{ $icon }}"></i>
            </span>
            <div class="min-w-0">
                <x-ui.sub-header
                    :eyebrow="$eyebrow"
                    :title="$title"
                    :description="$description"
                    :count="$count"
                />
            </div>
        </div>

        @if (isset($actions))
            <div class="flex min-w-0 flex-wrap items-center justify-start gap-2 lg:justify-end">
                {{ $actions }}
            </div>
        @endif
    </div>
</x-ui.card>
