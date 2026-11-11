<section class="w-full">
    <x-settings.layout :heading="__('Affiliate Settings')" :subheading="__('Configure referral tracking, commission rules, and payout thresholds for the affiliate system.')">
        <form wire:submit="save" class="my-6 w-full space-y-6">
            <x-theme.section-card
                :title="__('Affiliate status')"
                :description="__('Turn the affiliate system on or off globally. When disabled, referral capture and commission generation stop.')"
                body-class="space-y-5 p-6"
            >
                <x-ui.field :label="__('Affiliate program')" :error="$errors->first('affiliate_status')">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-ui.radio name="affiliate-status" value="1" wire:model="affiliate_status" :label="__('Enable')" />
                        <x-ui.radio name="affiliate-status" value="0" wire:model="affiliate_status" :label="__('Disable')" />
                    </div>
                </x-ui.field>
            </x-theme.section-card>

            <x-theme.section-card
                :title="__('Commission rules')"
                :description="__('Set the commission percentage and which payment sources can generate affiliate commissions.')"
                body-class="space-y-5 p-6"
            >
                <x-ui.input wire:model.defer="affiliate_commission_percentage" type="number" step="0.01" min="0" max="100" :label="__('Commission percentage')" :error="$errors->first('affiliate_commission_percentage')" />
                <x-ui.input wire:model.defer="affiliate_types_of_payments" :label="__('Allowed payment sources')" :error="$errors->first('affiliate_types_of_payments')" :help="__('Comma separated values. Example: manual,stripe,paypal')" />

                <x-ui.field :label="__('One-time commission')" :error="$errors->first('affiliate_onetime_commission_status')">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-ui.radio name="affiliate-onetime" value="1" wire:model="affiliate_onetime_commission_status" :label="__('Only first payment')" />
                        <x-ui.radio name="affiliate-onetime" value="0" wire:model="affiliate_onetime_commission_status" :label="__('Every eligible payment')" />
                    </div>
                </x-ui.field>
            </x-theme.section-card>

            <x-theme.section-card
                :title="__('Withdrawal rules')"
                :description="__('Define the minimum amount affiliates must accumulate before they can request a payout.')"
                body-class="space-y-5 p-6"
            >
                <x-ui.input wire:model.defer="affiliate_minimum_withdrawal" type="number" step="0.01" min="0" :label="__('Minimum withdrawal amount')" :error="$errors->first('affiliate_minimum_withdrawal')" />
            </x-theme.section-card>

            <div class="flex items-center gap-4">
                <x-ui.button type="submit">{{ __('Save changes') }}</x-ui.button>

                <x-action-message class="text-emerald-600 dark:text-emerald-400" on="settings-saved">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </form>
    </x-settings.layout>
</section>
