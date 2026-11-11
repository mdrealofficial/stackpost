@props([
    'title' => null,
    'description' => null,
    'variant' => 'info',
    'dismissible' => false,
    'inline' => false,
])

@php
    $variants = [
        'info' => 'border-color: rgba(var(--theme-accent-rgb), 0.24); background-color: rgba(var(--theme-accent-rgb), 0.1); color: var(--theme-header-text-color); --alert-icon-bg: rgba(var(--theme-accent-rgb), 0.14);',
        'success' => 'border-color: rgba(var(--theme-success-color-rgb), 0.28); background-color: rgba(var(--theme-success-color-rgb), 0.12); color: var(--theme-header-text-color); --alert-icon-bg: rgba(var(--theme-success-color-rgb), 0.14);',
        'warning' => 'border-color: rgba(var(--theme-warning-color-rgb), 0.28); background-color: rgba(var(--theme-warning-color-rgb), 0.12); color: var(--theme-header-text-color); --alert-icon-bg: rgba(var(--theme-warning-color-rgb), 0.14);',
        'danger' => 'border-color: rgba(var(--theme-danger-color-rgb), 0.28); background-color: rgba(var(--theme-danger-color-rgb), 0.12); color: var(--theme-header-text-color); --alert-icon-bg: rgba(var(--theme-danger-color-rgb), 0.14);',
        'neutral' => 'border-color: var(--theme-border-color); background-color: var(--theme-surface-base); color: var(--theme-header-text-color); --alert-icon-bg: var(--theme-surface-subtle);',
    ];
    $message = $description;

    if (! $message && trim((string) $slot) !== '') {
        $message = \Illuminate\Support\Str::of(strip_tags((string) $slot))->squish()->value();
    }

    $toastType = match ($variant) {
        'danger' => 'error',
        'warning' => 'warning',
        default => 'success',
    };

    $renderInline = $inline || $variant === 'neutral';
@endphp

@if ($renderInline)
    <div x-data="{ visible: true }" x-show="visible" {{ $attributes->merge(['style' => $variants[$variant] ?? $variants['info']])->class('rounded-[var(--theme-card-radius,0.9rem)] border px-4 py-4') }}>
        <div class="flex items-start gap-3">
            <div class="mt-0.5 flex-none">
                @if (isset($icon))
                    {{ $icon }}
                @else
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-full text-current" style="background-color: var(--alert-icon-bg);">
                        <i class="fa-light fa-circle-info"></i>
                    </span>
                @endif
            </div>

            <div class="min-w-0 flex-1">
                @if ($title)
                    <p class="text-sm font-semibold">{{ $title }}</p>
                @endif
                @if ($description)
                    <p class="mt-1 text-sm/6 opacity-90">{{ $description }}</p>
                @endif
                @if (trim((string) $slot) !== '')
                    <div class="mt-3 text-sm/6">
                        {{ $slot }}
                    </div>
                @endif
            </div>

            @if ($dismissible)
                <button type="button" class="flex-none text-current/70 transition hover:text-current" x-on:click="visible = false">
                    <i class="fa-light fa-xmark"></i>
                </button>
            @endif
        </div>
    </div>
@elseif ($title || $message)
    <div
        x-data
        x-init="window.dispatchEvent(new CustomEvent('app-toast', { detail: { type: @js($toastType), title: @js($title), message: @js($message) } }))"
        class="hidden"
        aria-hidden="true"
    ></div>
@endif
