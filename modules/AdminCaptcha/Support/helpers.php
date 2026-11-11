<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\HtmlString;
use Modules\AdminSettings\Support\OptionStore;

if (! function_exists('captcha_provider')) {
    function captcha_provider(): string
    {
        $options = app(OptionStore::class);
        $type = (string) $options->get('captcha_type', 'disable');

        if ($type === 'recaptcha') {
            $enabled = (string) $options->get('auth_google_recaptcha_status', '1') === '1';
            $siteKey = trim((string) $options->get('auth_google_recaptcha_site_key', ''));
            $secretKey = trim((string) $options->get('auth_google_recaptcha_secret_key', ''));

            return $enabled && $siteKey !== '' && $secretKey !== '' ? 'recaptcha' : 'disable';
        }

        if ($type === 'turnstile') {
            $enabled = (string) $options->get('auth_cloudflare_turnstile_status', '1') === '1';
            $siteKey = trim((string) $options->get('auth_cloudflare_turnstile_site_key', ''));
            $secretKey = trim((string) $options->get('auth_cloudflare_turnstile_secret_key', ''));

            return $enabled && $siteKey !== '' && $secretKey !== '' ? 'turnstile' : 'disable';
        }

        return 'disable';
    }
}

if (! function_exists('captcha_enabled')) {
    function captcha_enabled(): bool
    {
        return captcha_provider() !== 'disable';
    }
}

if (! function_exists('captcha_token_field')) {
    function captcha_token_field(?string $provider = null): ?string
    {
        return match ($provider ?: captcha_provider()) {
            'recaptcha' => 'g-recaptcha-response',
            'turnstile' => 'cf-turnstile-response',
            default => null,
        };
    }
}

if (! function_exists('captcha_error_message')) {
    function captcha_error_message(): string
    {
        return __('It looks like the captcha was incorrect. Please try again.');
    }
}

if (! function_exists('captcha_site_key')) {
    function captcha_site_key(?string $provider = null): string
    {
        $provider = $provider ?: captcha_provider();
        $options = app(OptionStore::class);

        return match ($provider) {
            'recaptcha' => trim((string) $options->get('auth_google_recaptcha_site_key', '')),
            'turnstile' => trim((string) $options->get('auth_cloudflare_turnstile_site_key', '')),
            default => '',
        };
    }
}

if (! function_exists('captcha_render')) {
    function captcha_render(): HtmlString
    {
        $provider = captcha_provider();

        if ($provider === 'recaptcha') {
            $siteKey = captcha_site_key($provider);

            return new HtmlString(
                '<div class="g-recaptcha" data-sitekey="'.e($siteKey).'"></div>'.
                '<script src="https://www.google.com/recaptcha/api.js" async defer></script>'
            );
        }

        if ($provider === 'turnstile') {
            $siteKey = captcha_site_key($provider);

            return new HtmlString(
                '<div class="cf-turnstile" data-sitekey="'.e($siteKey).'"></div>'.
                '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>'
            );
        }

        return new HtmlString('');
    }
}

if (! function_exists('captcha_verify')) {
    function captcha_verify(Request $request, ?string $provider = null): bool
    {
        $provider = $provider ?: captcha_provider();
        $field = captcha_token_field($provider);
        $token = (string) ($request->input('captcha_token') ?: $request->input((string) $field, ''));

        return captcha_verify_token(
            token: $token,
            provider: $provider,
            host: (string) $request->getHost(),
            ip: (string) $request->ip(),
        );
    }
}

if (! function_exists('captcha_verify_token')) {
    function captcha_verify_token(
        string $token,
        ?string $provider = null,
        ?string $host = null,
        ?string $ip = null,
    ): bool {
        $provider = $provider ?: captcha_provider();
        $options = app(OptionStore::class);

        if ($provider === 'disable') {
            return true;
        }

        if ($token === '') {
            return false;
        }

        if ($provider === 'recaptcha') {
            $secret = trim((string) $options->get('auth_google_recaptcha_secret_key', ''));

            if ($secret === '') {
                return false;
            }

            $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $ip,
            ]);

            $verified = $response->ok() && $response->json('success') === true;

            if (! $verified) {
                Log::warning('Captcha verify failed', [
                    'provider' => 'recaptcha',
                    'status' => $response->status(),
                    'error_codes' => $response->json('error-codes'),
                    'host' => $host,
                    'ip' => $ip,
                    'token_length' => strlen($token),
                ]);
            }

            return $verified;
        }

        if ($provider === 'turnstile') {
            $secret = trim((string) $options->get('auth_cloudflare_turnstile_secret_key', ''));

            if ($secret === '') {
                return false;
            }

            $response = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $ip,
            ]);

            $verified = $response->ok() && $response->json('success') === true;

            if (! $verified) {
                Log::warning('Captcha verify failed', [
                    'provider' => 'turnstile',
                    'status' => $response->status(),
                    'error_codes' => $response->json('error-codes'),
                    'host' => $host,
                    'ip' => $ip,
                    'token_length' => strlen($token),
                ]);
            }

            return $verified;
        }

        return true;
    }
}

if (! function_exists('captcha_validation_rules')) {
    function captcha_validation_rules(): array
    {
        if (! captcha_enabled()) {
            return [];
        }

        $field = captcha_token_field();

        if (! $field) {
            return [];
        }

        return [
            $field => [
                'required',
                'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! captcha_verify(request())) {
                        $fail(captcha_error_message());
                    }
                },
            ],
        ];
    }
}

if (! function_exists('captcha_validation_messages')) {
    function captcha_validation_messages(): array
    {
        $field = captcha_token_field();

        if (! $field) {
            return [];
        }

        return [
            "{$field}.required" => captcha_error_message(),
        ];
    }
}

if (! function_exists('with_captcha_validation')) {
    function with_captcha_validation(array $rules): array
    {
        return array_merge($rules, captcha_validation_rules());
    }
}
