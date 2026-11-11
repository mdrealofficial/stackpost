<?php

namespace Modules\PaymentPaytm\Services;

use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Modules\AdminPlans\Models\AdminPlan;
use Modules\AdminUser\Models\User;
use Modules\AppPayments\Contracts\PaymentGateway;
use Modules\AppPayments\Support\PaymentCallbackResult;
use Modules\AppPayments\Support\PaymentCheckout;
use Modules\AppPayments\Support\PaymentStartResult;

class PaytmPaymentGateway implements PaymentGateway
{
    protected string $merchantId;

    protected string $merchantKey;

    protected bool $live;

    protected string $apiBase;

    public function __construct()
    {
        $this->merchantId = (string) get_option('paytm_merchant_id', '');
        $this->merchantKey = (string) get_option('paytm_merchant_key', '');
        $this->live = (bool) get_option('paytm_environment', 0);
        $this->apiBase = $this->live
            ? 'https://securegw.paytm.in'
            : 'https://securegw-stage.paytm.in';
    }

    public function start(PaymentCheckout $checkout): PaymentStartResult
    {
        if (strtoupper($checkout->currency) !== 'INR') {
            throw new Exception(__('Paytm currently supports INR only in this module.'));
        }

        $plan = AdminPlan::query()->find($checkout->planId);
        $user = User::query()->find($checkout->userId);
        $orderId = $this->orderId($checkout);

        $body = [
            'requestType' => 'Payment',
            'mid' => $this->merchantId,
            'websiteName' => (string) (get_option('paytm_website', $this->live ? 'DEFAULT' : 'WEBSTAGING')),
            'orderId' => $orderId,
            'callbackUrl' => $checkout->returnUrl,
            'txnAmount' => [
                'value' => number_format((float) $checkout->amount, 2, '.', ''),
                'currency' => 'INR',
            ],
            'userInfo' => [
                'custId' => (string) ($user?->id ?: $checkout->userId),
                'email' => (string) ($user?->email ?: 'customer@example.com'),
                'mobile' => (string) ($user?->phone ?: '9999999999'),
            ],
        ];

        $payload = [
            'body' => $body,
            'head' => [
                'signature' => $this->generateChecksum($body),
            ],
        ];

        $response = $this->postJson("/theia/api/v1/initiateTransaction?mid={$this->merchantId}&orderId={$orderId}", $payload);

        $txnToken = (string) data_get($response, 'body.txnToken', '');

        if ($txnToken === '') {
            throw new Exception(__('Failed to create Paytm transaction token.'));
        }

        $checkoutUrl = $this->apiBase.'/theia/api/v1/showPaymentPage?mid='.$this->merchantId.'&orderId='.$orderId.'&txnToken='.$txnToken;

        session([
            'paytm_checkout' => [
                'order_id' => $orderId,
                'txn_token' => $txnToken,
                'amount' => number_format((float) $checkout->amount, 2, '.', ''),
                'currency' => 'INR',
                'checkout_url' => $checkoutUrl,
                'plan_name' => $plan?->name ?? data_get($checkout->meta, 'plan_name', __('Subscription')),
            ],
        ]);

        return PaymentStartResult::redirect(route('payment.paytm.checkout'));
    }

    public function complete(Request $request, PaymentCheckout $checkout): PaymentCallbackResult
    {
        $orderId = (string) ($request->input('ORDERID') ?: $request->query('ORDERID') ?: session('paytm_checkout.order_id'));

        if ($orderId === '') {
            throw new Exception(__('Missing Paytm ORDERID in callback.'));
        }

        $body = [
            'mid' => $this->merchantId,
            'orderId' => $orderId,
        ];

        $payload = [
            'body' => $body,
            'head' => [
                'signature' => $this->generateChecksum($body),
            ],
        ];

        $response = $this->postJson('/v3/order/status', $payload);
        $status = (string) data_get($response, 'body.resultInfo.resultStatus', 'UNKNOWN');

        if ($status !== 'TXN_SUCCESS') {
            return PaymentCallbackResult::failed(
                __('Paytm payment did not complete successfully.'),
                ['paytm_payload' => $response, 'paytm_status' => $status]
            );
        }

        $txnId = (string) data_get($response, 'body.txnId', $orderId);
        $amount = (float) data_get($response, 'body.txnAmount.value', $checkout->amount);
        $currency = strtoupper((string) data_get($response, 'body.txnAmount.currency', 'INR'));

        session()->forget('paytm_checkout');

        return PaymentCallbackResult::success(
            transactionId: $txnId,
            amount: $amount,
            currency: $currency,
            meta: [
                'paytm_payload' => $response,
                'paytm_order_id' => $orderId,
            ],
            subscriptionId: $orderId,
            customerId: (string) data_get($response, 'body.userInfo.custId', ''),
            message: __('Paytm payment captured successfully.')
        );
    }

    public function webhook(Request $request): mixed
    {
        return [
            'status' => 'ignored',
            'payload' => $request->all(),
        ];
    }

    protected function postJson(string $uri, array $payload): array
    {
        $response = Http::timeout(30)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($this->apiBase.$uri, $payload);

        if (! $response->successful()) {
            throw new Exception(sprintf('Paytm API error: %s', $response->body()));
        }

        return $response->json() ?: [];
    }

    protected function generateChecksum(array $params): string
    {
        ksort($params);

        return base64_encode(hash_hmac('sha256', json_encode($params, JSON_UNESCAPED_SLASHES), $this->merchantKey, true));
    }

    protected function orderId(PaymentCheckout $checkout): string
    {
        return 'PTM'.strtoupper(substr(hash('sha256', implode('|', [
            $checkout->userId,
            $checkout->planId,
            $checkout->amount,
            microtime(true),
            random_int(1000, 999999),
        ])), 0, 18));
    }
}
