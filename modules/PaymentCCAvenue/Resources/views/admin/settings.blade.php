<div class="space-y-5">
    <div class="space-y-4 rounded-[1rem] border p-5" style="border-color: var(--theme-border-color); background: var(--theme-surface-soft);">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Credentials') }}</p>
            <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Control CCAvenue availability and provide the merchant ID, access code, and working key used for hosted checkout.') }}</p>
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <x-ui.radio-group name="state.ccavenue_status" wire:model.defer="state.ccavenue_status" :label="__('Status')" :options="$toggleOptions" :value="data_get($state, 'ccavenue_status')" />
            <x-ui.radio-group name="state.ccavenue_environment" wire:model.defer="state.ccavenue_environment" :label="__('Environment')" :options="$environmentOptions" :value="data_get($state, 'ccavenue_environment')" />
        </div>

        <div class="grid gap-5 md:grid-cols-3">
            <x-ui.input wire:model.defer="state.ccavenue_merchant_id" :label="__('Merchant ID')" type="text" :error="$errors->first('state.ccavenue_merchant_id')" />
            <x-ui.input wire:model.defer="state.ccavenue_access_code" :label="__('Access Code')" type="text" :error="$errors->first('state.ccavenue_access_code')" />
            <x-ui.password-input wire:model.defer="state.ccavenue_working_key" :label="__('Working Key')" :error="$errors->first('state.ccavenue_working_key')" :placeholder="__('Paste CCAvenue working key')" />
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
            <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Register these URLs in your CCAvenue account.') }}</p>
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
            <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('CCAvenue checkout payloads are encrypted with the working key and the callback must be publicly reachable.') }}</p>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            @foreach ($requirementCards ?? [] as $card)
                <div class="rounded-[0.9rem] border px-4 py-4" style="border-color: var(--theme-border-color); background: var(--theme-surface-color);">
                    <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ is_scalar(data_get($card, 'title')) ? data_get($card, 'title') : '' }}</p>
                    <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ is_scalar(data_get($card, 'body')) ? data_get($card, 'body') : '' }}</p>
                </div>
            @endforeach
        </div>
    </div>
</div>

