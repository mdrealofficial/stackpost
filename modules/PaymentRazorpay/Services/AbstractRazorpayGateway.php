<?php

namespace Modules\PaymentRazorpay\Services;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\AdminSettings\Support\OptionStore;

abstract class AbstractRazorpayGateway
{
    protected string $keyId;

    protected string $keySecret;

    protected string $webhookSecret;

    protected string $apiBase = 'https://api.razorpay.com/v1';

    public function __construct()
    {
        $options = app(OptionStore::class);

        $this->keyId = trim((string) $options->get('razorpay_key_id', ''));
        $this->keySecret = trim((string) $options->get('razorpay_key_secret', ''));
        $this->webhookSecret = trim((string) $options->get('razorpay_webhook_secret', ''));

        if ($this->keyId === '' || $this->keySecret === '') {
            throw new Exception(__('Razorpay is not configured.'));
        }
    }

    protected function apiRequest(string $method, string $path, array $payload = []): array
    {
        $request = Http::withBasicAuth($this->keyId, $this->keySecret)
            ->acceptJson()
            ->contentType('application/json');

        $response = match (strtoupper($method)) {
            'GET' => $request->get($this->apiBase.$path, $payload),
            'POST' => $request->post($this->apiBase.$path, $payload),
            default => throw new Exception(__('Unsupported Razorpay request method.')),
        };

        if (! $response->successful()) {
            throw new Exception($this->razorpayError($response->json(), __('Razorpay request failed.')));
        }

        $decoded = $response->json();

        return is_array($decoded) ? $decoded : [];
    }

    protected function unitAmount(float $amount, string $currency): int
    {
        $currency = $this->normalizeCurrency($currency);

        if ($this->isZeroDecimalCurrency($currency)) {
            return (int) round($amount);
        }

        return (int) round($amount * 100);
    }

    protected function majorAmount(int|float|null $amount, string $currency): float
    {
        $currency = $this->normalizeCurrency($currency);
        $raw = (float) ($amount ?? 0);

        if ($this->isZeroDecimalCurrency($currency)) {
            return round($raw, 2);
        }

        return round($raw / 100, 2);
    }

    protected function normalizeCurrency(string $currency): string
    {
        $currency = strtoupper(trim($currency));

        return preg_match('/^[A-Z]{3}$/', $currency) === 1 ? $currency : 'INR';
    }

    protected function shortText(string $value, int $maxLength = 127): string
    {
        $clean = trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?? $value);

        return Str::limit($clean, $maxLength, '');
    }

    protected function razorpayError(array $payload, string $fallback): string
    {
        $message = trim((string) data_get($payload, 'error.description', data_get($payload, 'error.reason', '')));

        return $message !== '' ? $message : $fallback;
    }

    protected function verifySignature(string $left, string $paymentId, string $signature): bool
    {
        if ($left === '' || $paymentId === '' || $signature === '') {
            return false;
        }

        $generated = hash_hmac('sha256', $left.'|'.$paymentId, $this->keySecret);

        return hash_equals($generated, $signature);
    }

    protected function parseWebhook(Request $request): array
    {
        $payload = (string) $request->getContent();
        $decoded = json_decode($payload, true);

        if (! is_array($decoded)) {
            throw new Exception(__('Invalid Razorpay webhook payload.'));
        }

        $signature = trim((string) $request->header('X-Razorpay-Signature', ''));

        if ($this->webhookSecret !== '' && $signature !== '') {
            $generated = hash_hmac('sha256', $payload, $this->webhookSecret);

            if (! hash_equals($generated, $signature)) {
                throw new Exception(__('Invalid Razorpay webhook signature.'));
            }
        }

        return $decoded;
    }

    protected function isZeroDecimalCurrency(string $currency): bool
    {
        return in_array(strtoupper($currency), [
            'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG',
            'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
        ], true);
    }
}
