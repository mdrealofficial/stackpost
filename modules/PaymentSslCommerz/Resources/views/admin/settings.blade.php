<div class="space-y-5">
    <div class="space-y-4 rounded-[1rem] border p-5" style="border-color: var(--theme-border-color); background: var(--theme-surface-soft);">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Credentials') }}</p>
            <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Control SSLCOMMERZ availability and provide the Store ID and password used for hosted checkout.') }}</p>
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <x-ui.radio-group name="state.sslcommerz_status" wire:model.defer="state.sslcommerz_status" :label="__('Status')" :options="$toggleOptions" :value="data_get($state, 'sslcommerz_status')" />
            <x-ui.radio-group name="state.sslcommerz_environment" wire:model.defer="state.sslcommerz_environment" :label="__('Environment')" :options="$environmentOptions" :value="data_get($state, 'sslcommerz_environment')" />
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <x-ui.input wire:model.defer="state.sslcommerz_store_id" :label="__('Store ID')" type="text" :error="$errors->first('state.sslcommerz_store_id')" />
            <x-ui.password-input wire:model.defer="state.sslcommerz_store_password" :label="__('Store Password')" :error="$errors->first('state.sslcommerz_store_password')" :placeholder="__('Paste SSLCOMMERZ store password')" />
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
            <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Use these URLs for callbacks and manual testing.') }}</p>
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
            <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Confirm the payment before finalizing the order.') }}</p>
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
