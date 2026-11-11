@props([
    'variant' => 'info',
    'title',
    'message',
])

@php
    $palette = match ($variant) {
        'danger' => [
            'card' => 'border-rose-200 bg-rose-50/70 dark:border-rose-500/20 dark:bg-rose-500/10',
            'icon' => 'border-rose-200 bg-white text-rose-600 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-300',
        ],
        default => [
            'card' => '',
            'card_style' => 'border-color: rgba(var(--theme-accent-rgb), 0.18); background: rgba(var(--theme-accent-rgb), 0.045);',
            'icon_style' => 'border-color: rgba(var(--theme-accent-rgb), 0.18); color: var(--theme-accent-color);',
            'icon' => 'bg-white',
        ],
    };

    $cardStyle = $palette['card_style'] ?? null;
    $iconStyle = $palette['icon_style'] ?? null;
@endphp

<x-ui.card
    class="space-y-3 {{ $palette['card'] ?? '' }}"
    :style="$cardStyle"
>
    <div class="flex items-start gap-3">
        <span
            class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full border {{ $palette['icon'] }}"
            style="{{ $iconStyle }}"
        >
            @if ($variant === 'danger')
                <i class="fa-light fa-arrows-rotate"></i>
            @else
                <i class="fa-light fa-arrow-right-arrow-left"></i>
            @endif
        </span>
        <div class="space-y-2 text-left">
            <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">
                {{ $title }}
            </p>
            <p class="text-sm leading-6" style="color: var(--theme-muted-text-color);">
                {{ $message }}
            </p>
        </div>
    </div>
</x-ui.card>
