<div class="space-y-5">
    <div class="space-y-4 rounded-[1rem] border p-5" style="border-color: var(--theme-border-color); background: var(--theme-surface-soft);">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Credentials') }}</p>
            <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Control Stripe Checkout availability and provide the API credentials used for one-time and recurring billing.') }}</p>
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <x-ui.radio-group
                name="state.stripe_status"
                wire:model.defer="state.stripe_status"
                :label="__('Status')"
                :options="$toggleOptions"
                :value="data_get($state, 'stripe_status')"
            />

            <x-ui.radio-group
                name="state.stripe_recurring_status"
                wire:model.defer="state.stripe_recurring_status"
                :label="__('Recurring')"
                :options="$toggleOptions"
                :value="data_get($state, 'stripe_recurring_status')"
            />
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <x-ui.input wire:model.defer="state.stripe_publishable_key" :label="__('Publishable Key')" type="text" :error="$errors->first('state.stripe_publishable_key')" :placeholder="__('pk_test_... or pk_live_...')" />
            <x-ui.password-input wire:model.defer="state.stripe_secret_key" :label="__('Secret Key')" :error="$errors->first('state.stripe_secret_key')" :placeholder="__('sk_test_... or sk_live_...')" />
        </div>

        <div class="grid gap-5 md:grid-cols-1">
            <x-ui.password-input wire:model.defer="state.stripe_webhook_secret" :label="__('Webhook Signing Secret')" :error="$errors->first('state.stripe_webhook_secret')" :placeholder="__('whsec_...')" />
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
            <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Use these URLs in Stripe Checkout, webhook configuration, and local integration testing.') }}</p>
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
            <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Complete these Stripe requirements before relying on checkout callbacks or recurring billing events.') }}</p>
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
                    {{ __('Subscribe these Stripe events so payment and recurring billing states can be tracked consistently.') }}
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
