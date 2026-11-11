@props([
    'compact' => false,
    'label' => null,
    'value' => null,
    'description' => null,
    'icon' => null,
    'iconStyle' => 'background-color: rgba(var(--theme-accent-rgb), 0.10); color: var(--theme-accent);',
    'valueStyle' => 'color: var(--theme-header-text-color);',
    'minHeight' => 'min-h-[132px]',
])

@php($resolvedMinHeight = $compact ? 'min-h-0' : $minHeight)
<div {{ $attributes->class("sp-stat-card rounded-[1rem] border p-4 {$resolvedMinHeight}") }} style="{{ $compact ? 'min-height:0;' : '' }} border-color: var(--theme-card-border-color, var(--theme-border-color)); background-color: var(--theme-surface-base);">
    <div class="flex h-full flex-col">
        <div class="flex items-start justify-between gap-4">
            <div>
                @if ($label)
                    <p class="text-[12px] font-medium" style="color: var(--theme-muted-text-color);">{{ $label }}</p>
                @endif

                @if (! is_null($value) && $value !== '')
                    <p class="{{ $compact ? 'mt-2 text-2xl' : 'mt-3 text-[1.9rem]' }} font-semibold leading-none tracking-[-0.05em]" style="{{ $valueStyle }}">{{ $value }}</p>
                @endif
            </div>

            @if ($icon)
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-[1rem] text-base" style="{{ $iconStyle }}">
                    <i class="{{ $icon }}"></i>
                </span>
            @endif
        </div>

        @if ($description)
            <p class="mt-3 max-w-[22rem] text-xs leading-5" style="color: var(--theme-muted-text-color);">{{ $description }}</p>
        @endif

        @if (trim((string) $slot) !== '')
            <div class="mt-4">
                {{ $slot }}
            </div>
        @endif
    </div>
</div>
