@props([
    'plan',
    'manualInfo',
    'manualReference',
    'errors' => null,
])

<x-ui.card class="space-y-5">
    @php
        $manualInfoContent = trim((string) $manualInfo);
        $captchaProvider = captcha_provider();
        $captchaSiteKey = captcha_site_key($captchaProvider);
    @endphp

    <div class="space-y-1">
        <h3 class="text-lg font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">
            {{ __('Manual Payment') }}
        </h3>
        <p class="text-sm leading-6" style="color: var(--theme-muted-text-color);">
            {{ __('Bank transfer, cash, or manual confirmation.') }}
        </p>
    </div>

    <div class="space-y-5">
        <div class="rounded-[0.9rem] border px-4 py-4" style="border-color: var(--theme-border-color); background: var(--theme-surface-soft); color: var(--theme-header-text-color);">
            @if ($manualInfoContent !== '')
                <div class="text-sm leading-7 break-words [&>*:first-child]:mt-0 [&>*:last-child]:mb-0" style="color: var(--theme-header-text-color);">{!! $manualInfoContent !!}</div>
            @else
                <p class="text-sm leading-7" style="color: var(--theme-muted-text-color);">{{ __('No manual payment instructions configured yet.') }}</p>
            @endif
        </div>

        <div class="rounded-[0.9rem] border px-4 py-4" style="border-color: var(--theme-border-color); background: var(--theme-surface-soft);">
            <p class="text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">
                {{ __('Transfer content') }}
            </p>
            <div class="mt-3 rounded-[0.75rem] border px-4 py-3 text-center text-sm font-semibold tracking-[0.12em]" style="border-color: var(--theme-border-color); background: var(--theme-surface-base); color: var(--theme-header-text-color);">
                {{ $manualReference }}
            </div>
        </div>

        <x-ui.textarea
            label="{{ __('Your transfer information') }}"
            name="payment_info"
            wire:model.defer="paymentInfo"
            rows="4"
            placeholder="{{ __('E.g. I transferred, account name John Doe, at 09:30 AM') }}"
            :error="$errors?->first('paymentInfo')"
        />

        <form
            wire:submit="submitManualPayment"
            class="space-y-4"
            x-data
        >
            @if ($captchaProvider === 'recaptcha' && $captchaSiteKey !== '')
                <div
                    wire:ignore
                    x-data="{
                        widgetId: null,
                        init() {
                            const renderWidget = () => {
                                if (! window.grecaptcha || ! this.$refs.recaptcha) {
                                    return;
                                }

                                this.$refs.recaptcha.innerHTML = '';
                                this.widgetId = window.grecaptcha.render(this.$refs.recaptcha, {
                                    sitekey: @js($captchaSiteKey),
                                    callback: (token) => $wire.set('captchaToken', token),
                                    'expired-callback': () => $wire.set('captchaToken', ''),
                                    'error-callback': () => $wire.set('captchaToken', ''),
                                });
                            };

                            if (window.grecaptcha && typeof window.grecaptcha.render === 'function') {
                                renderWidget();
                                return;
                            }

                            const intervalId = window.setInterval(() => {
                                if (window.grecaptcha && typeof window.grecaptcha.render === 'function') {
                                    window.clearInterval(intervalId);
                                    renderWidget();
                                }
                            }, 200);
                        }
                    }"
                    x-on:captcha-reset.window="
                        $wire.set('captchaToken', '');
                        if (window.grecaptcha && widgetId !== null) {
                            window.grecaptcha.reset(widgetId);
                        }
                    "
                >
                    <div x-ref="recaptcha"></div>
                    <script src="https://www.google.com/recaptcha/api.js?render=explicit" async defer></script>
                </div>
            @elseif ($captchaProvider === 'turnstile' && $captchaSiteKey !== '')
                <div
                    wire:ignore
                    x-data="{
                        widgetId: null,
                        init() {
                            const renderWidget = () => {
                                if (! window.turnstile || ! this.$refs.turnstile) {
                                    return;
                                }

                                this.$refs.turnstile.innerHTML = '';
                                this.widgetId = window.turnstile.render(this.$refs.turnstile, {
                                    sitekey: @js($captchaSiteKey),
                                    callback: (token) => $wire.set('captchaToken', token),
                                    'expired-callback': () => $wire.set('captchaToken', ''),
                                    'error-callback': () => $wire.set('captchaToken', ''),
                                });
                            };

                            if (window.turnstile && typeof window.turnstile.render === 'function') {
                                renderWidget();
                                return;
                            }

                            const intervalId = window.setInterval(() => {
                                if (window.turnstile && typeof window.turnstile.render === 'function') {
                                    window.clearInterval(intervalId);
                                    renderWidget();
                                }
                            }, 200);
                        }
                    }"
                    x-on:captcha-reset.window="
                        $wire.set('captchaToken', '');
                        if (window.turnstile && widgetId !== null) {
                            window.turnstile.reset(widgetId);
                        }
                    "
                >
                    <div x-ref="turnstile"></div>
                    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit" async defer></script>
                </div>
            @endif

            @if ($errors?->first('captchaToken'))
                <p class="-mt-2 text-sm font-medium" style="color: var(--theme-danger-color);">
                    {{ $errors->first('captchaToken') }}
                </p>
            @endif

            <div class="flex justify-end">
                <x-ui.button type="submit" class="w-full sm:w-auto sm:min-w-[18rem]">
                    {{ __('I have transferred') }}
                </x-ui.button>
            </div>
        </form>
    </div>
</x-ui.card>
