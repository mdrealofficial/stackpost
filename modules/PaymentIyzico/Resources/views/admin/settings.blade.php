<div class="space-y-5">
    <div class="space-y-4 rounded-[1rem] border p-5" style="border-color: var(--theme-border-color); background: var(--theme-surface-soft);">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Credentials') }}</p>
            <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Control iyzico availability and provide the API credentials used for hosted checkout.') }}</p>
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <x-ui.radio-group name="state.iyzico_status" wire:model.defer="state.iyzico_status" :label="__('Status')" :options="$toggleOptions" :value="data_get($state, 'iyzico_status')" />
            <x-ui.radio-group name="state.iyzico_environment" wire:model.defer="state.iyzico_environment" :label="__('Environment')" :options="$environmentOptions" :value="data_get($state, 'iyzico_environment')" />
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <x-ui.input wire:model.defer="state.iyzico_api_key" :label="__('API Key')" type="text" :error="$errors->first('state.iyzico_api_key')" />
            <x-ui.password-input wire:model.defer="state.iyzico_secret_key" :label="__('Secret Key')" :error="$errors->first('state.iyzico_secret_key')" :placeholder="__('Paste iyzico secret key')" />
        </div>

        <x-ui.radio-group name="state.iyzico_locale" wire:model.defer="state.iyzico_locale" :label="__('Locale')" :options="$localeOptions" :value="data_get($state, 'iyzico_locale')" />

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
            <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Register these URLs in your iyzico dashboard or checkout form configuration.') }}</p>
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
            <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('iyzico callbacks must be reachable from the public internet and verified with the checkout token before confirming the order.') }}</p>
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
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                @foreach ($eventGroups ?? [] as $group)
                    <div class="rounded-[0.85rem] border px-4 py-4" style="border-color: var(--theme-border-color); background: var(--theme-surface-base);">
                        <p class="text-xs font-semibold uppercase tracking-[0.14em]" style="color: var(--theme-muted-text-color);">{{ $group['title'] }}</p>
                        <ul class="mt-3 space-y-2 text-sm" style="color: var(--theme-header-text-color);">
                            @foreach (($group['events'] ?? []) as $event)
                                <li>{{ $event }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
