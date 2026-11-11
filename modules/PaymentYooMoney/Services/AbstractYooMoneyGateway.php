<?php

namespace Modules\PaymentYooMoney\Services;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Modules\AdminSettings\Support\OptionStore;

abstract class AbstractYooMoneyGateway
{
    protected string $shopId;

    protected string $secretKey;

    protected int $mode;

    protected string $apiBase = 'https://api.yookassa.ru/v3';

    public function __construct()
    {
        $options = app(OptionStore::class);

        $this->shopId = trim((string) $options->get('yoomoney_shop_id', ''));
        $this->secretKey = trim((string) $options->get('yoomoney_secret_key', ''));
        $this->mode = (int) $options->get('yoomoney_environment', 0);

        if ($this->shopId === '' || $this->secretKey === '') {
            throw new Exception(__('YooMoney credentials are not configured.'));
        }
    }

    protected function normalizeCurrency(string $currency): string
    {
        $currency = strtoupper(trim($currency));

        return preg_match('/^[A-Z]{3}$/', $currency) === 1 ? $currency : 'RUB';
    }

    protected function apiRequest(string $method, string $path, array $payload = [], ?string $idempotenceKey = null): array
    {
        $request = Http::withBasicAuth($this->shopId, $this->secretKey)
            ->acceptJson()
            ->contentType('application/json');

        if ($idempotenceKey !== null) {
            $request = $request->withHeaders(['Idempotence-Key' => $idempotenceKey]);
        }

        $response = match (strtoupper($method)) {
            'GET' => $request->get($this->apiBase.$path, $payload),
            'POST' => $request->post($this->apiBase.$path, $payload),
            default => throw new Exception(__('Unsupported YooMoney request method.')),
        };

        if (! $response->successful()) {
            throw new Exception($this->yooMoneyError($response->json(), __('YooMoney request failed.')));
        }

        $decoded = $response->json();

        return is_array($decoded) ? $decoded : [];
    }

    protected function yooMoneyError(array $payload, string $fallback): string
    {
        $message = trim((string) data_get($payload, 'description', data_get($payload, 'message', '')));

        return $message !== '' ? $message : $fallback;
    }

    protected function generateIdempotenceKey(string $prefix): string
    {
        return $prefix.(auth()->id() ?? 'guest').'_'.time().'_'.bin2hex(random_bytes(6));
    }

    protected function verifyWebhook(array $payload): bool
    {
        return isset($payload['event'], $payload['object']['id']);
    }

    protected function fetchPayment(string $paymentId): array
    {
        return $this->apiRequest('GET', '/payments/'.$paymentId);
    }

    protected function mapPaymentSuccess(array $payment): array
    {
        return [
            'gateway' => 'YooMoney',
            'status' => 1,
            'transaction_id' => $payment['id'] ?? null,
            'amount' => $payment['amount']['value'] ?? null,
            'currency' => $payment['amount']['currency'] ?? null,
            'transaction_details' => $payment,
        ];
    }
}
