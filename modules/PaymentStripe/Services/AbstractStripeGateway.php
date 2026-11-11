<?php

namespace Modules\PaymentStripe\Services;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\AdminSettings\Support\OptionStore;
use Stripe\Event;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;

abstract class AbstractStripeGateway
{
    protected StripeClient $stripe;

    protected string $publishableKey;

    protected string $secretKey;

    protected string $webhookSecret;

    public function __construct()
    {
        $options = app(OptionStore::class);

        $this->publishableKey = trim((string) $options->get('stripe_publishable_key', ''));
        $this->secretKey = trim((string) $options->get('stripe_secret_key', ''));
        $this->webhookSecret = trim((string) $options->get('stripe_webhook_secret', ''));

        if ($this->secretKey === '') {
            throw new Exception(__('Stripe is not configured.'));
        }

        $this->stripe = new StripeClient($this->secretKey);
    }

    protected function unitAmount(float $amount, string $currency): int
    {
        $normalized = strtoupper(trim($currency));

        if ($this->isZeroDecimalCurrency($normalized)) {
            return (int) round($amount);
        }

        return (int) round($amount * 100);
    }

    protected function majorAmount(int|float|null $amount, string $currency): float
    {
        $normalized = strtoupper(trim($currency));
        $raw = (float) ($amount ?? 0);

        if ($this->isZeroDecimalCurrency($normalized)) {
            return round($raw, 2);
        }

        return round($raw / 100, 2);
    }

    protected function normalizeCurrency(string $currency): string
    {
        $currency = strtoupper(trim($currency));

        return preg_match('/^[A-Z]{3}$/', $currency) === 1
            ? $currency
            : 'USD';
    }

    protected function shortText(string $value, int $maxLength = 127): string
    {
        $clean = trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?? $value);

        return Str::limit($clean, $maxLength, '');
    }

    protected function stripeError(\Throwable $e, string $fallback): string
    {
        if ($e instanceof ApiErrorException) {
            $message = trim((string) $e->getMessage());

            return $message !== '' ? $message : $fallback;
        }

        $message = trim((string) $e->getMessage());

        return $message !== '' ? $message : $fallback;
    }

    protected function stripeEvent(Request $request): Event|array
    {
        $payload = (string) $request->getContent();
        $signature = trim((string) $request->header('Stripe-Signature', ''));

        if ($this->webhookSecret !== '' && $signature !== '') {
            try {
                return Webhook::constructEvent($payload, $signature, $this->webhookSecret);
            } catch (SignatureVerificationException $e) {
                throw new Exception(__('Invalid Stripe webhook signature.'));
            } catch (\UnexpectedValueException $e) {
                throw new Exception(__('Invalid Stripe webhook payload.'));
            }
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function isZeroDecimalCurrency(string $currency): bool
    {
        return in_array(strtoupper($currency), [
            'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG',
            'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
        ], true);
    }
}
