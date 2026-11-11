@props([
    'model' => 'captchaToken',
    'errorKey' => 'captchaToken',
])

@php
    $captchaProvider = captcha_provider();
    $captchaSiteKey = captcha_site_key($captchaProvider);
@endphp

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
                        callback: (token) => $wire.set(@js($model), token),
                        'expired-callback': () => $wire.set(@js($model), ''),
                        'error-callback': () => $wire.set(@js($model), ''),
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
            $wire.set(@js($model), '');
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
                        callback: (token) => $wire.set(@js($model), token),
                        'expired-callback': () => $wire.set(@js($model), ''),
                        'error-callback': () => $wire.set(@js($model), ''),
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
            $wire.set(@js($model), '');
            if (window.turnstile && widgetId !== null) {
                window.turnstile.reset(widgetId);
            }
        "
    >
        <div x-ref="turnstile"></div>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit" async defer></script>
    </div>
@endif

@error($errorKey)
    <p class="-mt-2 text-sm font-medium" style="color: var(--theme-danger-color);">
        {{ $message }}
    </p>
@enderror
