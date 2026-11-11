<section class="w-full space-y-6">
    <x-ui.sub-header
        :eyebrow="__('Finance')"
        :title="__('Payment Manual Configurations')"
        :description="__('Set up offline payment availability, reference prefixes, and the customer-facing instructions shown during manual checkout.')"
    />

    <x-ui.form-page
        :eyebrow="__('Manual payments')"
        :title="__('Manual payment settings')"
        :description="__('Control whether offline payment is offered and define the instructions customers will follow when submitting payment proof.')"
        card-class="space-y-6"
    >
        <form wire:submit="save" class="space-y-6">
            <x-ui.form-section
                :title="__('Manual Payment Settings')"
                :description="__('Control whether offline payment is offered and define how references are generated.')"
            >
                <x-ui.radio-group
                    name="payment_manual_status"
                    wire:model.live="payment_manual_status"
                    :label="__('Status')"
                    :options="$toggleOptions"
                    :value="$payment_manual_status"
                />

                <div class="space-y-2.5">
                    <x-ui.label>{{ __('Prefix') }}</x-ui.label>
                    <x-ui.input wire:model="payment_manual_prefix" type="text" :error="$errors->first('payment_manual_prefix')" />
                    <p class="text-sm" style="color: var(--theme-muted-text-color);">{{ __('Prefix prepended to manual payment references or invoices.') }}</p>
                </div>
            </x-ui.form-section>

            <x-ui.form-section
                :title="__('Manual Payment Information')"
                :description="__('Provide the account holder, bank account, branch, SWIFT or BIC, reference instructions, and payment amount guidance customers should see.')"
            >
                <div class="space-y-2.5">
                    <x-ui.label>{{ __('Instructions') }}</x-ui.label>
                    <x-ui.textarea
                        wire:model="payment_manual_info"
                        rows="10"
                        :error="$errors->first('payment_manual_info')"
                        :placeholder="__('Please enter your manual payment details, including Account Name, Account Number, Branch, SWIFT/BIC Code, Reference Information, and Payment Amount. This ensures that your clients can complete their transactions accurately.')"
                    />
                </div>
            </x-ui.form-section>

            <div class="flex items-center gap-4 border-t pt-5" style="border-color: var(--theme-border-color);">
                <x-ui.button type="submit">{{ __('Save changes') }}</x-ui.button>
                <x-action-message class="text-emerald-600 dark:text-emerald-400" on="settings-saved">{{ __('Saved.') }}</x-action-message>
            </div>
        </form>
    </x-ui.form-page>
</section>
