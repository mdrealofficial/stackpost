@php
    $featuredLabel = $pack->featured ? __('Featured') : null;
    $priceLabel = $pack->currency_symbol.number_format((float) $pack->price, 2);
@endphp

<div class="mx-auto w-full max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
    <div class="space-y-5 text-center">
        <div class="flex items-center justify-center gap-3">
            <x-ui.badge variant="primary">{{ __('Credit pack') }}</x-ui.badge>
            @if ($featuredLabel)
                <x-ui.badge variant="success">{{ $featuredLabel }}</x-ui.badge>
            @endif

            <span class="inline-flex items-center rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] shadow-[0_10px_24px_-20px_rgba(var(--theme-accent-rgb),0.38)]" style="border-color: rgba(var(--theme-accent-rgb), 0.18); color: var(--theme-muted-text-color); background: rgba(var(--theme-accent-rgb), 0.05);">
                {{ __('Total today') }} {{ $priceLabel }}
            </span>
        </div>

        <div class="space-y-3">
            <h1 class="text-[3rem] font-semibold leading-none tracking-[-0.055em]" style="color: var(--theme-header-text-color);">
                {{ $pack->name }}
            </h1>

            <p class="mx-auto max-w-2xl text-[15px] leading-7" style="color: var(--theme-muted-text-color);">
                {{ $pack->description ?: __('Top up your AI balance without changing the current subscription plan.') }}
            </p>
        </div>

        <div class="flex flex-wrap items-center justify-center gap-3 text-sm">
            <div class="inline-flex items-center gap-2 rounded-[0.75rem] border px-3 py-2 shadow-[0_10px_24px_-22px_rgba(var(--theme-accent-rgb),0.32)]" style="border-color: rgba(var(--theme-accent-rgb), 0.14); background: rgba(var(--theme-accent-rgb), 0.04); color: var(--theme-header-text-color);">
                <i class="fa-light fa-sparkles text-xs" style="color: var(--theme-accent-color);"></i>
                <span class="font-medium">{{ __('Non-expiring credit top-up') }}</span>
            </div>

            <div class="inline-flex items-center gap-2 rounded-[0.75rem] border px-3 py-2 shadow-[0_10px_24px_-22px_rgba(var(--theme-accent-rgb),0.32)]" style="border-color: rgba(var(--theme-accent-rgb), 0.14); background: rgba(var(--theme-accent-rgb), 0.04); color: var(--theme-header-text-color);">
                <i class="fa-light fa-bolt text-xs" style="color: var(--theme-accent-color);"></i>
                <span class="font-medium">{{ __('Instant balance update after payment') }}</span>
            </div>
        </div>
    </div>

    <div class="mt-8 space-y-6">
        <x-payments.status-notice
            variant="info"
            :title="__('How purchased credits work')"
            :message="__('Purchased credits accumulate on your account, do not expire, and are consumed before plan credits when the current engine priority is set to top-up first. Unlimited plans keep using unlimited mode and will not consume extra packs.')"
        />

        <x-ui.card class="space-y-5" style="background: var(--theme-surface-soft);">
            <div class="space-y-1">
                <h2 class="text-2xl font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">
                    {{ __('Pack summary') }}
                </h2>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-[1rem] border p-4" style="border-color: var(--theme-border-color); background: var(--theme-surface-base);">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Credits') }}</p>
                    <p class="mt-3 text-2xl font-semibold tracking-[-0.04em]" style="color: var(--theme-header-text-color);">{{ number_format((int) $pack->credits) }}</p>
                </div>
                <div class="rounded-[1rem] border p-4" style="border-color: var(--theme-border-color); background: var(--theme-surface-base);">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Price') }}</p>
                    <p class="mt-3 text-2xl font-semibold tracking-[-0.04em]" style="color: var(--theme-header-text-color);">{{ $priceLabel }}</p>
                </div>
                <div class="rounded-[1rem] border p-4" style="border-color: var(--theme-border-color); background: var(--theme-surface-base);">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Terms') }}</p>
                    <p class="mt-3 text-base font-semibold" style="color: var(--theme-header-text-color);">{{ __('Non-expiring top-up') }}</p>
                </div>
            </div>

            <div class="flex justify-end">
                <x-ui.button :href="route('portal.credits')" variant="outline" wire:navigate>
                    {{ __('Back to credits') }}
                </x-ui.button>
            </div>
        </x-ui.card>

        <div class="space-y-5">
            <div class="space-y-1">
                <h2 class="text-2xl font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">
                    {{ __('Payment methods') }}
                </h2>
            </div>

            @if (! empty($gateways))
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($gateways as $gateway)
                        <x-payments.gateway-card :gateway="$gateway" wire:key="credit-pack-gateway-{{ $gateway->key }}" />
                    @endforeach
                </div>
            @else
                <x-ui.card class="space-y-3" style="background: var(--theme-surface-soft);">
                    <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">
                        {{ __('No payment methods available for this checkout') }}
                    </p>
                    <p class="text-sm leading-6" style="color: var(--theme-muted-text-color);">
                        {{ __('No gateway is currently available for this credit pack purchase.') }}
                    </p>
                </x-ui.card>
            @endif
        </div>
    </div>
</div>
