<?php

namespace Modules\PaymentPaypal\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\AdminSettings\Support\OptionStore;

abstract class AbstractPaypalGateway
{
    protected string $clientId;

    protected string $secret;

    protected bool $liveMode;

    protected string $apiBase;

    public function __construct()
    {
        $options = app(OptionStore::class);

        $this->clientId = trim((string) $options->get('paypal_client_id', ''));
        $this->secret = trim((string) $options->get('paypal_client_secret', ''));
        $this->liveMode = (int) $options->get('paypal_environment', 0) === 1;
        $this->apiBase = $this->liveMode
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';

        if ($this->clientId === '' || $this->secret === '') {
            throw new Exception(__('PayPal is not configured.'));
        }
    }

    protected function accessToken(): string
    {
        $response = Http::asForm()
            ->withBasicAuth($this->clientId, $this->secret)
            ->post($this->apiBase.'/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

        if (! $response->successful()) {
            $message = $this->paypalError($response->json(), __('Unable to authenticate with PayPal.'));
            $hint = $this->liveMode
                ? __('Check that the Live client ID and secret are copied from the Live PayPal app.')
                : __('Check that the Sandbox client ID and secret are copied from the Sandbox PayPal app.');

            throw new Exception(trim($message.' '.$hint));
        }

        $accessToken = (string) $response->json('access_token', '');

        if ($accessToken === '') {
            throw new Exception(__('PayPal access token was not returned.'));
        }

        return $accessToken;
    }

    protected function paypalError(mixed $payload, string $fallback): string
    {
        if (! is_array($payload)) {
            return $fallback;
        }

        $message = (string) (
            data_get($payload, 'details.0.description')
            ?: data_get($payload, 'message')
            ?: data_get($payload, 'error_description')
            ?: data_get($payload, 'name')
            ?: $fallback
        );

        $status = (string) data_get($payload, 'status');
        $error = (string) data_get($payload, 'error');
        $issue = (string) data_get($payload, 'details.0.issue');
        $field = (string) data_get($payload, 'details.0.field');

        if ($issue !== '') {
            $message = trim($message.' ['.$issue.']');
        }

        if ($field !== '') {
            $message = trim($message.' ['.$field.']');
        }

        if ($status !== '' || $error !== '') {
            return trim($message.' '.($status !== '' ? "[{$status}]" : '').' '.($error !== '' ? "[{$error}]" : ''));
        }

        return $message;
    }

    protected function logPaypalFailure(string $stage, array $requestPayload, mixed $responsePayload, int $statusCode = 0): void
    {
        Log::error('PayPal request failed', [
            'stage' => $stage,
            'status_code' => $statusCode,
            'request' => $requestPayload,
            'response' => $responsePayload,
            'live_mode' => $this->liveMode,
        ]);
    }

    protected function shortText(string $value, int $maxLength = 127): string
    {
        $clean = trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?? $value);

        return Str::limit($clean, $maxLength, '');
    }

    protected function paypalCurrencyCode(string $currency): string
    {
        $currency = strtoupper(trim($currency));

        if (preg_match('/^[A-Z]{3}$/', $currency) === 1) {
            return $currency;
        }

        return match ($currency) {
            '$', 'US$', 'USD$', 'US DOLLAR' => 'USD',
            '€', 'EUR€', 'EURO' => 'EUR',
            '£', 'GBP£', 'POUND', 'POUND STERLING' => 'GBP',
            '¥', 'JPY¥', 'YEN' => 'JPY',
            'A$', 'AUD$' => 'AUD',
            'C$', 'CAD$' => 'CAD',
            '₦' => 'NGN',
            '₹' => 'INR',
            default => 'USD',
        };
    }
}
