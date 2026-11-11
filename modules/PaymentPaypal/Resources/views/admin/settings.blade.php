<div class="space-y-5">
    <div class="space-y-4 rounded-[1rem] border p-5" style="border-color: var(--theme-border-color); background: var(--theme-surface-soft);">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Credentials') }}</p>
            <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Control PayPal availability and provide the API credentials used for one-time and recurring checkout.') }}</p>
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <x-ui.radio-group
                name="state.paypal_status"
                wire:model.defer="state.paypal_status"
                :label="__('Status')"
                :options="$toggleOptions"
                :value="data_get($state, 'paypal_status')"
            />

            <x-ui.radio-group
                name="state.paypal_recurring_status"
                wire:model.defer="state.paypal_recurring_status"
                :label="__('Recurring')"
                :options="$toggleOptions"
                :value="data_get($state, 'paypal_recurring_status')"
            />
        </div>

        <div class="grid gap-5 md:grid-cols-1">
            <x-ui.select wire:model.defer="state.paypal_environment" :label="__('Environment')" :error="$errors->first('state.paypal_environment')">
                @foreach ($environmentOptions as $option)
                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                @endforeach
            </x-ui.select>
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <x-ui.input wire:model.defer="state.paypal_client_id" :label="__('Client ID')" type="text" :error="$errors->first('state.paypal_client_id')" />
            <x-ui.password-input wire:model.defer="state.paypal_client_secret" :label="__('Client Secret')" :error="$errors->first('state.paypal_client_secret')" :placeholder="__('Paste PayPal client secret')" />
        </div>

        @if (! empty($credentialTips))
            <div class="rounded-[0.9rem] border px-4 py-4" style="border-color: var(--theme-border-color); background: var(--theme-surface-color);">
                <ul class="grid gap-4 md:grid-cols-3">
                    @foreach ($credentialTips as $tip)
                        <li class="space-y-1">
                            <p class="text-xs font-semibold uppercase tracking-[0.14em]" style="color: var(--theme-muted-text-color);">{{ $tip['title'] }}</p>
                            <p class="text-sm leading-6" style="color: var(--theme-header-text-color);">{{ $tip['body'] }}</p>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    <div class="space-y-4 rounded-[1rem] border p-5" style="border-color: var(--theme-border-color); background: var(--theme-surface-soft);">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Endpoints') }}</p>
            <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Use these URLs in your PayPal app configuration and webhook registration.') }}</p>
        </div>

        <div class="space-y-3">
            @foreach ($endpointItems as $item)
                <x-payments.endpoint-url-card :label="$item['label']" :value="$item['value']" />
            @endforeach
        </div>
    </div>

    <div class="space-y-5 rounded-[1rem] border p-5" style="border-color: var(--theme-border-color); background: var(--theme-surface-soft);">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Webhook requirements') }}</p>
            <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Configure these requirements on the PayPal app before testing checkout or relying on recurring billing events.') }}</p>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            @foreach ($requirementCards as $card)
                <div class="rounded-[0.9rem] border px-4 py-4" style="border-color: var(--theme-border-color); background: var(--theme-surface-color);">
                    <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $card['title'] }}</p>
                    <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ $card['body'] }}</p>
                </div>
            @endforeach
        </div>

        <div class="rounded-[0.9rem] border px-4 py-4" style="border-color: var(--theme-border-color); background: var(--theme-surface-color);">
            <div class="max-w-xl">
                <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Recommended webhook events') }}</p>
                <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">
                    {{ __('Subscribe these events in PayPal so one-time and recurring billing states can be tracked correctly.') }}
                </p>
            </div>

            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                @foreach ($eventGroups as $group)
                    <div class="rounded-[0.85rem] border px-4 py-4" style="border-color: var(--theme-border-color); background: var(--theme-surface-soft);">
                        <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $group['title'] }}</p>
                        <ul class="mt-3 space-y-2 text-sm font-mono" style="color: var(--theme-muted-text-color);">
                            @foreach ($group['events'] as $event)
                                <li>{{ $event }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
