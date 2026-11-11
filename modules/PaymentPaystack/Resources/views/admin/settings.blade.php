<div class="space-y-5">
    <div class="space-y-4 rounded-[1rem] border p-5" style="border-color: var(--theme-border-color); background: var(--theme-surface-soft);">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Credentials') }}</p>
            <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Control Paystack availability and provide the public and secret keys used for card payments.') }}</p>
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <x-ui.radio-group name="state.paystack_status" wire:model.defer="state.paystack_status" :label="__('Status')" :options="$toggleOptions" :value="data_get($state, 'paystack_status')" />
            <x-ui.radio-group name="state.paystack_environment" wire:model.defer="state.paystack_environment" :label="__('Environment')" :options="$environmentOptions" :value="data_get($state, 'paystack_environment')" />
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <x-ui.input wire:model.defer="state.paystack_public_key" :label="__('Public Key')" type="text" :error="$errors->first('state.paystack_public_key')" />
            <x-ui.password-input wire:model.defer="state.paystack_secret_key" :label="__('Secret Key')" :error="$errors->first('state.paystack_secret_key')" :placeholder="__('Paste Paystack secret key')" />
        </div>

        <div class="rounded-[0.9rem] border px-4 py-4" style="border-color: var(--theme-border-color); background: var(--theme-surface-color);">
            <ul class="grid gap-4 md:grid-cols-3">
                @foreach ($credentialTips ?? [] as $tip)
                    <li class="space-y-1">
                        <p class="text-xs font-semibold uppercase tracking-[0.14em]" style="color: var(--theme-muted-text-color);">
                            {{ is_scalar(data_get($tip, 'title')) ? data_get($tip, 'title') : '' }}
                        </p>
                        <p class="text-sm leading-6" style="color: var(--theme-header-text-color);">
                            {{ is_scalar(data_get($tip, 'body')) ? data_get($tip, 'body') : '' }}
                        </p>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    <div class="space-y-4 rounded-[1rem] border p-5" style="border-color: var(--theme-border-color); background: var(--theme-surface-soft);">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Endpoints') }}</p>
            <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Register these URLs on your Paystack dashboard.') }}</p>
        </div>

        <div class="space-y-3">
            @foreach ($endpointItems ?? [] as $item)
                <x-payments.endpoint-url-card :label="$item['label']" :value="$item['value']" />
            @endforeach
        </div>
    </div>

    <div class="space-y-5 rounded-[1rem] border p-5" style="border-color: var(--theme-border-color); background: var(--theme-surface-soft);">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Webhook requirements') }}</p>
            <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Confirm the Paystack webhook signature with the secret key before processing events.') }}</p>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            @foreach ($requirementCards ?? [] as $card)
                <div class="rounded-[0.9rem] border px-4 py-4" style="border-color: var(--theme-border-color); background: var(--theme-surface-color);">
                    <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">
                        {{ is_scalar(data_get($card, 'title')) ? data_get($card, 'title') : '' }}
                    </p>
                    <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">
                        {{ is_scalar(data_get($card, 'body')) ? data_get($card, 'body') : '' }}
                    </p>
                </div>
            @endforeach
        </div>
    </div>
</div>

