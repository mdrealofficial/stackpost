<section class="w-full">
    <x-settings.layout :heading="__('Credits')" :subheading="__('Control credit top-ups, AI usage blocking, and the purchasable packs available in the portal.')">
        <form wire:submit="save" class="my-6 w-full space-y-6">
            <x-theme.section-card
                :title="__('Credit engine')"
                :description="__('Global rules for how credits are spent, when low-balance warnings appear, and whether actions can continue with an empty balance.')"
                body-class="space-y-5 p-6"
            >
                <div class="grid gap-5 md:grid-cols-2">
                    <x-ui.radio-group name="credit_topup_enabled" wire:model.live="credit_topup_enabled" :label="__('Allow top-up purchases')" :options="$toggleOptions" :value="$credit_topup_enabled" />
                    <x-ui.radio-group name="credit_negative_balance_allowed" wire:model.live="credit_negative_balance_allowed" :label="__('Allow negative credit balance')" :options="$toggleOptions" :value="$credit_negative_balance_allowed" />
                    <x-ui.radio-group name="credit_action_block_on_empty" wire:model.live="credit_action_block_on_empty" :label="__('Block actions when empty')" :options="$toggleOptions" :value="$credit_action_block_on_empty" />
                    <x-ui.radio-group name="credit_topup_requires_active_plan" wire:model.live="credit_topup_requires_active_plan" :label="__('Require active plan to buy packs')" :options="$toggleOptions" :value="$credit_topup_requires_active_plan" />
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    <x-ui.select wire:model="credit_spend_priority" :label="__('Credit spend priority')" :error="$errors->first('credit_spend_priority')">
                        @foreach ($spendPriorityOptions as $option)
                            <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.input wire:model="credit_low_balance_threshold" type="number" min="0" :label="__('Low-balance threshold')" :error="$errors->first('credit_low_balance_threshold')" />
                </div>

                <x-ui.input wire:model="credit_action_empty_message" type="text" :label="__('Insufficient-credit message')" :error="$errors->first('credit_action_empty_message')" />

                <div class="grid gap-5 md:grid-cols-2">
                    <x-ui.select wire:model="credit_action_empty_cta" :label="__('Default CTA when empty')" :error="$errors->first('credit_action_empty_cta')">
                        @foreach ($ctaOptions as $option)
                            <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                        @endforeach
                    </x-ui.select>

                    <x-ui.select wire:model="credit_pack_visibility" :label="__('Pack visibility')" :error="$errors->first('credit_pack_visibility')">
                        @foreach ($visibilityOptions as $option)
                            <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                        @endforeach
                    </x-ui.select>
                </div>
            </x-theme.section-card>

            <x-theme.section-card
                :title="__('Purchase eligibility')"
                :description="__('Decide which account states are allowed to buy extra credits.')"
                body-class="space-y-5 p-6"
            >
                <div class="grid gap-5 md:grid-cols-2">
                    <x-ui.radio-group name="credit_trial_user_can_buy_extra" wire:model.live="credit_trial_user_can_buy_extra" :label="__('Trial users can buy extra credits')" :options="$toggleOptions" :value="$credit_trial_user_can_buy_extra" />
                    <x-ui.radio-group name="credit_free_plan_user_can_buy_extra" wire:model.live="credit_free_plan_user_can_buy_extra" :label="__('Free-plan users can buy extra credits')" :options="$toggleOptions" :value="$credit_free_plan_user_can_buy_extra" />
                </div>
            </x-theme.section-card>

            <x-theme.section-card
                :title="__('Credit pack policy')"
                :description="__('Control pack availability and carry-over globally. Pack definitions themselves are managed from Admin Credits > Credit Packs.')"
                body-class="space-y-5 p-6"
            >
                <div class="grid gap-5 md:grid-cols-2">
                    <x-ui.radio-group name="credit_pack_enabled" wire:model.live="credit_pack_enabled" :label="__('Credit packs')" :options="$toggleOptions" :value="$credit_pack_enabled" />
                    <x-ui.radio-group name="credit_pack_carry_over" wire:model.live="credit_pack_carry_over" :label="__('Carry over unused pack balance')" :options="$toggleOptions" :value="$credit_pack_carry_over" />
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    <x-ui.input wire:model="credit_pack_expiry_days" type="number" min="0" :label="__('Pack expiry in days')" :help="__('Use 0 for no expiry.')" />
                </div>

                <div class="rounded-[1rem] border px-4 py-4 text-sm leading-6" style="border-color: var(--theme-border-color); background: var(--theme-surface-soft); color: var(--theme-muted-text-color);">
                    {{ __('Need to create, reorder, or feature credit packs? Use the dedicated Credit Packs module in the finance sidebar for operational management.') }}
                </div>
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
