@props([
    'errors' => null,
])

<x-ui.card class="space-y-4">
    <div class="space-y-1">
        <h2 class="text-xl font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">
            {{ __('Coupon') }}
        </h2>
        <p class="text-sm leading-6" style="color: var(--theme-muted-text-color);">
            {{ __('Enter coupon code and secure exclusive savings today.') }}
        </p>
    </div>

    <form wire:submit="applyCoupon">
        <div class="flex flex-col gap-3 sm:flex-row">
            <div class="flex-1">
                <x-ui.input
                    name="coupon_code"
                    wire:model.defer="couponCode"
                    placeholder="{{ __('Enter coupon') }}"
                    :error="$errors?->first('coupon_code')"
                />
            </div>

            <x-ui.button type="submit" variant="outline" class="sm:h-11 sm:px-5">
                {{ __('Apply') }}
            </x-ui.button>
        </div>
    </form>
</x-ui.card>
