@props([
    'gateway',
])

@php
    $gatewayTheme = [
        'border' => $gateway->display('theme_styles.border', 'var(--theme-border-color)'),
        'surface' => $gateway->display('theme_styles.surface', 'var(--theme-surface-base)'),
        'badge_border' => $gateway->display('theme_styles.badge_border', 'rgba(var(--theme-accent-rgb), 0.16)'),
        'badge_bg' => $gateway->display('theme_styles.badge_bg', 'rgba(var(--theme-accent-rgb), 0.05)'),
        'badge_text' => $gateway->display('theme_styles.badge_text', 'var(--theme-accent-color)'),
        'priority_bg' => $gateway->display('theme_styles.priority_bg', 'var(--theme-surface-soft)'),
        'priority_text' => $gateway->display('theme_styles.priority_text', 'var(--theme-muted-text-color)'),
    ];

    $fallbackIcon = $gateway->type === 'recurring'
        ? 'fa-light fa-rotate'
        : 'fa-light fa-credit-card';
@endphp

<button
    type="button"
    wire:click="checkout('{{ $gateway->key }}')"
    class="flex min-h-[5.5rem] w-full cursor-pointer items-center gap-4 rounded-[0.75rem] border px-4 py-4 text-left transition"
    style="border-color: {{ $gatewayTheme['border'] }}; background: {{ $gatewayTheme['surface'] }};"
>
    <span class="inline-flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-[0.75rem] border bg-white p-2" style="border-color: var(--theme-border-color);">
        @if (filled($gateway->display('logo')))
            <img src="{{ $gateway->display('logo') }}" alt="{{ $gateway->title }}" class="h-full w-full object-contain">
        @else
            <i class="{{ $gateway->icon ?: $fallbackIcon }} text-lg" style="color: var(--theme-muted-text-color);"></i>
        @endif
    </span>

    <span class="min-w-0">
        <span class="flex items-start justify-between gap-3">
            <span class="block text-sm font-semibold tracking-[-0.015em]" style="color: var(--theme-header-text-color);">
                {{ __($gateway->title) }}
            </span>

            @if (filled($gateway->display('badge')))
                <span class="shrink-0 rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.14em]" style="border-color: rgba(var(--theme-accent-rgb), 0.16); background: rgba(var(--theme-accent-rgb), 0.05); color: var(--theme-accent-color);">
                    {{ __($gateway->display('badge')) }}
                </span>
            @endif
        </span>

        @if ($gateway->description)
            <span class="mt-1 block text-xs leading-5" style="color: var(--theme-muted-text-color);">
                {{ __($gateway->description) }}
            </span>
        @endif

        @if (filled($gateway->display('checkout_note')))
            <span class="mt-1 block text-[11px] leading-5" style="color: var(--theme-muted-text-color);">
                {{ __($gateway->display('checkout_note')) }}
            </span>
        @endif

        <span class="mt-3 flex flex-wrap items-center gap-2">
            @if (filled($gateway->display('button_label')))
                <span class="rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.14em]" style="border-color: {{ $gatewayTheme['badge_border'] }}; background: {{ $gatewayTheme['badge_bg'] }}; color: {{ $gatewayTheme['badge_text'] }};">
                    {{ __($gateway->display('button_label')) }}
                </span>
            @endif

            @if (filled($gateway->display('priority_label')))
                <span class="rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.14em]" style="background: {{ $gatewayTheme['priority_bg'] }}; color: {{ $gatewayTheme['priority_text'] }};">
                    {{ __($gateway->display('priority_label')) }}
                </span>
            @endif
        </span>

        @if (filled($gateway->display('checkout_hint')))
            <span class="mt-2 block text-[11px] leading-5" style="color: var(--theme-muted-text-color);">
                {{ __($gateway->display('checkout_hint')) }}
            </span>
        @endif
    </span>
</button>
