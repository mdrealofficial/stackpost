@props([
    'fieldsClass' => 'lg:grid-cols-[minmax(0,1.5fr)_180px_180px_180px_160px_auto]',
    'contentClass' => '',
    'panelStyle' => null,
    'overflowVisible' => false,
    'compact' => false,
])

@php
    $resolvedPanelStyle = $panelStyle ?: 'border-color: var(--theme-card-border-color, var(--theme-border-color)); background-color: var(--theme-surface-base);';

    $hasActions = isset($actions) && trim((string) $actions) !== '';
    $hasChips = isset($chips) && trim((string) $chips) !== '';
@endphp

<x-ui.card padding="none" class="space-y-5 {{ $overflowVisible ? 'overflow-visible' : 'overflow-hidden' }} border p-0" style="{{ $resolvedPanelStyle }}">
    <div {{ $attributes->class([$compact ? 'space-y-3 p-4' : 'space-y-4 px-6 py-6', $contentClass]) }}>
        <div class="grid gap-3 {{ $fieldsClass }}">
            {{ $fields ?? $slot }}

            @if ($hasActions)
                <div class="flex items-end gap-2">
                    {{ $actions }}
                </div>
            @endif
        </div>

        @if ($hasChips)
            <div class="flex flex-wrap gap-2">
                {{ $chips }}
            </div>
        @endif
    </div>
</x-ui.card>
