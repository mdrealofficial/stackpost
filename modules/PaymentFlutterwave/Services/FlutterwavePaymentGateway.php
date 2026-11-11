<?php

namespace Modules\PaymentFlutterwave\Services;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Modules\AppPayments\Contracts\PaymentGateway;
use Modules\AppPayments\Support\PaymentCallbackResult;
use Modules\AppPayments\Support\PaymentCheckout;
use Modules\AppPayments\Support\PaymentStartResult;

class FlutterwavePaymentGateway implements PaymentGateway
{
    protected string $publicKey;
    protected string $secretKey;
    protected string $encryptionKey;
    protected int $mode;
    protected string $apiBase;

    public function __construct()
    {
        $this->publicKey = (string) get_option('flutterwave_public_key', '');
        $this->secretKey = (string) get_option('flutterwave_secret_key', '');
        $this->encryptionKey = (string) get_option('flutterwave_encryption_key', '');
        $this->mode = (int) get_option('flutterwave_environment', 0);
        $this->apiBase = $this->mode === 1
            ? 'https://api.flutterwave.com/v3'
            : 'https://api.flutterwave.cloud/developersandbox/v3';
    }

    public function start(PaymentCheckout $checkout): PaymentStartResult
    {
        if ($this->secretKey === '') {
            throw new Exception(__('Missing Flutterwave secret key.'));
        }

        $txRef = (string) data_get($checkout->meta, 'reference', 'flw_'.uniqid());
        $payload = [
            'tx_ref' => $txRef,
            'amount' => number_format((float) $checkout->amount, 2, '.', ''),
            'currency' => strtoupper($checkout->currency ?: 'NGN'),
            'redirect_url' => $checkout->returnUrl,
            'customer' => [
                'email' => auth()->user()?->email ?: 'user@example.com',
                'name' => auth()->user()?->name ?: __('Customer'),
            ],
            'customizations' => [
                'title' => (string) data_get($checkout->meta, 'plan_name', __('Payment')),
                'description' => (string) data_get($checkout->meta, 'plan_name', __('Order payment')),
            ],
        ];

        $response = Http::withToken($this->secretKey)->post($this->apiBase.'/payments', $payload);

        if (! $response->successful() || data_get($response->json(), 'status') !== 'success') {
            throw new Exception(__('Flutterwave init failed: :message', ['message' => $response->body()]));
        }

        $link = (string) data_get($response->json(), 'data.link', '');

        if ($link === '') {
            throw new Exception(__('Flutterwave checkout link not found.'));
        }

        return PaymentStartResult::redirect($link);
    }

    public function complete(Request $request, PaymentCheckout $checkout): PaymentCallbackResult
    {
        $transactionId = (string) ($request->query('transaction_id') ?: $request->query('transactionId') ?: '');

        if ($transactionId === '') {
            throw new Exception(__('Missing Flutterwave transaction ID.'));
        }

        $response = Http::withToken($this->secretKey)->get($this->apiBase.'/transactions/'.$transactionId.'/verify');

        if (! $response->successful() || data_get($response->json(), 'status') !== 'success') {
            return PaymentCallbackResult::failed(__('Flutterwave verification failed.'));
        }

        $data = (array) data_get($response->json(), 'data', []);

        if ((string) ($data['status'] ?? '') !== 'successful') {
            return PaymentCallbackResult::failed(__('Flutterwave payment was not successful.'));
        }

        return PaymentCallbackResult::success(
            transactionId: (string) ($data['id'] ?? $transactionId),
            amount: isset($data['amount']) ? (float) $data['amount'] : $checkout->amount,
            currency: strtoupper((string) ($data['currency'] ?? $checkout->currency ?: 'NGN')),
            meta: ['flutterwave_payload' => $data],
            message: __('Flutterwave payment captured successfully.')
        );
    }

    public function webhook(Request $request): mixed
    {
        if ($this->encryptionKey !== '' && $request->header('verif-hash') !== $this->encryptionKey) {
            return ['status' => 'ignored', 'reason' => 'invalid signature'];
        }

        return [
            'status' => 'ok',
            'payload' => $request->all(),
        ];
    }
}
