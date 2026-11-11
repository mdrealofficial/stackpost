<?php

namespace Modules\PaymentSslCommerz\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Modules\AppPayments\Support\PaymentCheckout;

abstract class AbstractSslCommerzGateway
{
    protected string $storeId;
    protected string $storePassword;
    protected int $mode;
    protected string $apiBase;

    public function __construct()
    {
        $this->storeId = (string) get_option('sslcommerz_store_id', '');
        $this->storePassword = (string) get_option('sslcommerz_store_password', '');
        $this->mode = (int) get_option('sslcommerz_environment', 0);
        $this->apiBase = $this->mode === 1
            ? 'https://securepay.sslcommerz.com'
            : 'https://sandbox.sslcommerz.com';
    }

    protected function api(string $method, string $path, array $payload = [], array $query = []): array
    {
        $response = Http::withoutVerifying()
            ->timeout(30)
            ->send($method, rtrim($this->apiBase, '/').'/'.ltrim($path, '/'), [
                'query' => $query,
                'form_params' => $payload,
                'json' => $payload,
            ]);

        if (! $response->successful()) {
            throw new Exception($response->body() ?: __('SSLCOMMERZ request failed.'));
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    protected function shortDescription(PaymentCheckout $checkout): string
    {
        return trim((string) data_get($checkout->meta, 'plan_name', __('SSLCOMMERZ payment')));
    }

    protected function customerName(): string
    {
        $user = auth()->user();

        return (string) ($user?->name ?: 'Customer');
    }

    protected function customerEmail(): string
    {
        $user = auth()->user();

        return (string) ($user?->email ?: 'customer@example.com');
    }

    protected function customerPhone(): string
    {
        $user = auth()->user();

        return (string) (data_get($user, 'phone') ?: '0000000000');
    }

    protected function customerAddress(): array
    {
        return [
            'address' => 'N/A',
            'city' => 'Dhaka',
            'state' => 'Dhaka',
            'postcode' => '1000',
            'country' => 'Bangladesh',
        ];
    }
}
