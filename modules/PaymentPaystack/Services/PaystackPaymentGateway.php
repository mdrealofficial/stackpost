<?php

namespace Modules\PaymentPaystack\Services;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Modules\AppPayments\Contracts\PaymentGateway;
use Modules\AppPayments\Support\PaymentCallbackResult;
use Modules\AppPayments\Support\PaymentCheckout;
use Modules\AppPayments\Support\PaymentStartResult;

class PaystackPaymentGateway implements PaymentGateway
{
    protected string $secretKey;
    protected string $baseUrl = 'https://api.paystack.co';

    public function __construct()
    {
        $this->secretKey = (string) get_option('paystack_secret_key', '');
    }

    public function start(PaymentCheckout $checkout): PaymentStartResult
    {
        if ($this->secretKey === '') {
            throw new Exception(__('Missing Paystack secret key.'));
        }

        $reference = (string) data_get($checkout->meta, 'reference', 'ps_'.uniqid());
        $payload = [
            'email' => auth()->user()?->email ?: 'user@example.com',
            'amount' => (int) round($checkout->amount * 100),
            'currency' => strtoupper($checkout->currency ?: 'NGN'),
            'reference' => $reference,
            'callback_url' => $checkout->returnUrl,
            'metadata' => [
                'uid' => (int) $checkout->userId,
                'plan_id' => (int) $checkout->planId,
                'plan_type' => (string) $checkout->gatewayType,
                'plan_name' => (string) data_get($checkout->meta, 'plan_name', ''),
            ],
        ];

        $response = Http::withToken($this->secretKey)->post($this->baseUrl.'/transaction/initialize', $payload);

        if (! $response->successful() || ! data_get($response->json(), 'data.authorization_url')) {
            throw new Exception(__('Paystack init failed: :message', ['message' => $response->body()]));
        }

        return PaymentStartResult::redirect((string) data_get($response->json(), 'data.authorization_url'));
    }

    public function complete(Request $request, PaymentCheckout $checkout): PaymentCallbackResult
    {
        $reference = (string) ($request->query('reference') ?: $request->input('reference', ''));

        if ($reference === '') {
            throw new Exception(__('Missing Paystack reference.'));
        }

        $response = Http::withToken($this->secretKey)->get($this->baseUrl.'/transaction/verify/'.$reference);

        if (! $response->successful() || data_get($response->json(), 'data.status') !== 'success') {
            return PaymentCallbackResult::failed(__('Paystack verify failed.'));
        }

        $data = (array) data_get($response->json(), 'data', []);

        return PaymentCallbackResult::success(
            transactionId: (string) ($data['id'] ?? $reference),
            amount: isset($data['amount']) ? ((float) $data['amount'] / 100) : $checkout->amount,
            currency: strtoupper((string) ($data['currency'] ?? $checkout->currency ?: 'NGN')),
            meta: ['paystack_payload' => $data],
            message: __('Paystack payment captured successfully.')
        );
    }

    public function webhook(Request $request): mixed
    {
        $signature = (string) $request->header('x-paystack-signature', '');
        $payload = $request->getContent();
        $expected = hash_hmac('sha512', $payload, $this->secretKey);

        if ($signature === '' || ! hash_equals($expected, $signature)) {
            return ['status' => 'ignored', 'reason' => 'invalid signature'];
        }

        $json = $request->json()->all();

        return [
            'status' => 'ok',
            'event' => (string) ($json['event'] ?? ''),
        ];
    }
}
