@props([
    'plan',
    'pricing',
])

<x-ui.card>
    <div class="space-y-3 text-sm">
        <div class="flex items-center justify-between gap-4">
            <span style="color: var(--theme-muted-text-color);">{{ __('Subtotal') }}</span>
            <span class="font-medium" style="color: var(--theme-header-text-color);">
                {{ $plan->currency_symbol }}{{ number_format((float) data_get($pricing, 'subtotal', 0), 2) }}
            </span>
        </div>

        <div class="flex items-center justify-between gap-4">
            <span style="color: var(--theme-muted-text-color);">{{ __('Promotion') }}</span>
            <span class="font-medium" style="color: {{ (float) data_get($pricing, 'discount', 0) > 0 ? '#dc2626' : 'var(--theme-header-text-color)' }};">
                {{ (float) data_get($pricing, 'discount', 0) > 0 ? '-' : '' }}{{ $plan->currency_symbol }}{{ number_format((float) data_get($pricing, 'discount', 0), 2) }}
            </span>
        </div>
    </div>

    <div class="my-4 border-t" style="border-color: var(--theme-border-color);"></div>

    <div class="flex items-center justify-between gap-4">
        <span class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Total') }}</span>
        <span class="text-sm font-semibold" style="color: var(--theme-header-text-color);">
            {{ $plan->currency_symbol }}{{ number_format((float) data_get($pricing, 'total', 0), 2) }}
        </span>
    </div>
</x-ui.card>
