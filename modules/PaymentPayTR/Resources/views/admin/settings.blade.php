<div class="space-y-5">
    <div class="space-y-4 rounded-[1rem] border p-5" style="border-color: var(--theme-border-color); background: var(--theme-surface-soft);">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Credentials') }}</p>
            <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Control PayTR availability and provide the merchant credentials used for hosted iFrame checkout.') }}</p>
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <x-ui.radio-group name="state.paytr_status" wire:model.defer="state.paytr_status" :label="__('Status')" :options="$toggleOptions" :value="data_get($state, 'paytr_status')" />
            <x-ui.radio-group name="state.paytr_environment" wire:model.defer="state.paytr_environment" :label="__('Environment')" :options="$environmentOptions" :value="data_get($state, 'paytr_environment')" />
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <x-ui.input wire:model.defer="state.paytr_merchant_id" :label="__('Merchant ID')" type="text" :error="$errors->first('state.paytr_merchant_id')" />
            <x-ui.select wire:model.defer="state.paytr_lang" :label="__('Language')" :error="$errors->first('state.paytr_lang')">
                @foreach ($languageOptions ?? [] as $option)
                    <option value="{{ is_scalar(data_get($option, 'value')) ? data_get($option, 'value') : '' }}" @selected(data_get($state, 'paytr_lang') === (string) data_get($option, 'value'))>
                        {{ is_scalar(data_get($option, 'label')) ? data_get($option, 'label') : '' }}
                    </option>
                @endforeach
            </x-ui.select>
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <x-ui.password-input wire:model.defer="state.paytr_merchant_key" :label="__('Merchant Key')" :error="$errors->first('state.paytr_merchant_key')" :placeholder="__('Paste PayTR merchant key')" />
            <x-ui.password-input wire:model.defer="state.paytr_merchant_salt" :label="__('Merchant Salt')" :error="$errors->first('state.paytr_merchant_salt')" :placeholder="__('Paste PayTR merchant salt')" />
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <x-ui.radio-group name="state.paytr_no_installment" wire:model.defer="state.paytr_no_installment" :label="__('Installments')" :options="$installmentOptions" :value="data_get($state, 'paytr_no_installment')" />
            <x-ui.input wire:model.defer="state.paytr_max_installment" :label="__('Maximum Installment')" type="number" :error="$errors->first('state.paytr_max_installment')" />
        </div>

        <div class="rounded-[0.9rem] border px-4 py-4" style="border-color: var(--theme-border-color); background: var(--theme-surface-color);">
            <ul class="grid gap-4 md:grid-cols-3">
                @foreach ($credentialTips ?? [] as $tip)
                    <li class="space-y-1">
                        <p class="text-xs font-semibold uppercase tracking-[0.14em]" style="color: var(--theme-muted-text-color);">{{ is_scalar(data_get($tip, 'title')) ? data_get($tip, 'title') : '' }}</p>
                        <p class="text-sm leading-6" style="color: var(--theme-header-text-color);">{{ is_scalar(data_get($tip, 'body')) ? data_get($tip, 'body') : '' }}</p>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    <div class="space-y-4 rounded-[1rem] border p-5" style="border-color: var(--theme-border-color); background: var(--theme-surface-soft);">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Endpoints') }}</p>
            <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Register these URLs in your PayTR merchant panel.') }}</p>
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
            <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('PayTR callback notifications must return OK after the callback hash is verified and the order id is recognized.') }}</p>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            @foreach ($requirementCards ?? [] as $card)
                <div class="rounded-[0.9rem] border px-4 py-4" style="border-color: var(--theme-border-color); background: var(--theme-surface-color);">
                    <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ is_scalar(data_get($card, 'title')) ? data_get($card, 'title') : '' }}</p>
                    <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ is_scalar(data_get($card, 'body')) ? data_get($card, 'body') : '' }}</p>
                </div>
            @endforeach
        </div>

        <div class="rounded-[0.9rem] border px-4 py-4" style="border-color: var(--theme-border-color); background: var(--theme-surface-color);">
            <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Recommended webhook events') }}</p>
            <div class="mt-4 rounded-[0.85rem] border px-4 py-4" style="border-color: var(--theme-border-color); background: var(--theme-surface-base);">
                <ul class="mt-2 space-y-2 text-sm" style="color: var(--theme-header-text-color);">
                    @foreach (($eventGroups[0]['events'] ?? []) as $event)
                        <li>{{ $event }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</div>
