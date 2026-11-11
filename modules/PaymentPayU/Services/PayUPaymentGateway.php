<?php

namespace Modules\PaymentPayU\Services;

use Exception;
use Illuminate\Http\Request;
use Modules\AdminPlans\Models\AdminPlan;
use Modules\AdminUser\Models\User;
use Modules\AppPayments\Contracts\PaymentGateway;
use Modules\AppPayments\Support\PaymentCallbackResult;
use Modules\AppPayments\Support\PaymentCheckout;
use Modules\AppPayments\Support\PaymentStartResult;

class PayUPaymentGateway implements PaymentGateway
{
    protected string $merchantKey;

    protected string $salt;

    protected bool $live;

    protected string $paymentUrl;

    public function __construct()
    {
        $this->merchantKey = (string) get_option('payu_merchant_key', '');
        $this->salt = (string) get_option('payu_salt', '');
        $this->live = (bool) get_option('payu_environment', 0);
        $this->paymentUrl = $this->live
            ? 'https://secure.payu.in/_payment'
            : 'https://test.payu.in/_payment';
    }

    public function start(PaymentCheckout $checkout): PaymentStartResult
    {
        $plan = AdminPlan::query()->find($checkout->planId);
        $user = User::query()->find($checkout->userId);

        if (strtoupper($checkout->currency) !== 'INR') {
            throw new Exception(__('PayU currently supports INR only in this module.'));
        }

        $txnid = $this->txnid($checkout);
        $params = [
            'key' => $this->merchantKey,
            'txnid' => $txnid,
            'amount' => number_format((float) $checkout->amount, 2, '.', ''),
            'productinfo' => trim((string) ($plan?->name ?: data_get($checkout->meta, 'plan_name', __('Subscription')))),
            'firstname' => trim((string) ($user?->name ?: $user?->username ?: __('Customer'))),
            'email' => trim((string) ($user?->email ?: 'customer@example.com')),
            'phone' => trim((string) ($user?->phone ?: '9999999999')),
            'surl' => $checkout->returnUrl,
            'furl' => $checkout->cancelUrl,
            'udf1' => (string) $checkout->userId,
            'udf2' => (string) $checkout->planId,
            'udf3' => (string) data_get($checkout->meta, 'plan_slug', ''),
            'udf4' => '',
            'udf5' => '',
        ];

        $params['hash'] = $this->generateRequestHash($params);

        session([
            'payu_checkout' => $params,
            'payu_txnid' => $txnid,
            'payu_checkout_url' => $this->paymentUrl,
        ]);

        return PaymentStartResult::redirect(route('payment.payu.checkout'));
    }

    public function complete(Request $request, PaymentCheckout $checkout): PaymentCallbackResult
    {
        $posted = $request->all();

        if (! filled($posted['txnid'] ?? null) || ! filled($posted['hash'] ?? null)) {
            throw new Exception(__('Invalid PayU callback data.'));
        }

        $hash = (string) $posted['hash'];
        $calcHash = $this->generateResponseHash($posted);

        if (! hash_equals(strtolower($hash), strtolower($calcHash))) {
            throw new Exception(__('Invalid PayU hash verification.'));
        }

        $status = strtolower((string) ($posted['status'] ?? ''));

        if ($status !== 'success') {
            return PaymentCallbackResult::failed(
                __('PayU payment did not complete successfully.'),
                ['payu_payload' => $posted]
            );
        }

        $amount = (float) ($posted['amount'] ?? $checkout->amount);
        $currency = strtoupper((string) ($posted['currency'] ?? $checkout->currency));
        $transactionId = (string) ($posted['mihpayid'] ?? $posted['txnid']);

        session()->forget(['payu_checkout', 'payu_txnid']);

        return PaymentCallbackResult::success(
            transactionId: $transactionId,
            amount: $amount,
            currency: $currency,
            meta: [
                'payu_payload' => $posted,
                'payu_txnid' => (string) $posted['txnid'],
            ],
            subscriptionId: (string) $posted['txnid'],
            customerId: (string) ($posted['email'] ?? ''),
            message: __('PayU payment captured successfully.')
        );
    }

    public function webhook(Request $request): mixed
    {
        return [
            'status' => 'ignored',
            'payload' => $request->all(),
        ];
    }

    protected function generateRequestHash(array $params): string
    {
        $hashString = implode('|', [
            $params['key'] ?? '',
            $params['txnid'] ?? '',
            $params['amount'] ?? '',
            $params['productinfo'] ?? '',
            $params['firstname'] ?? '',
            $params['email'] ?? '',
            $params['udf1'] ?? '',
            $params['udf2'] ?? '',
            $params['udf3'] ?? '',
            $params['udf4'] ?? '',
            $params['udf5'] ?? '',
            '',
            '',
            '',
            '',
            '',
            $this->salt,
        ]);

        return strtolower(hash('sha512', $hashString));
    }

    protected function generateResponseHash(array $payload): string
    {
        $amount = isset($payload['amount']) ? number_format((float) $payload['amount'], 2, '.', '') : '';

        $hashString = implode('|', [
            $this->salt,
            $payload['status'] ?? '',
            '',
            '',
            '',
            '',
            '',
            $payload['udf5'] ?? '',
            $payload['udf4'] ?? '',
            $payload['udf3'] ?? '',
            $payload['udf2'] ?? '',
            $payload['udf1'] ?? '',
            $payload['email'] ?? '',
            $payload['firstname'] ?? '',
            $payload['productinfo'] ?? '',
            $amount,
            $payload['txnid'] ?? '',
            $this->merchantKey,
        ]);

        return strtolower(hash('sha512', $hashString));
    }

    protected function txnid(PaymentCheckout $checkout): string
    {
        return 'PAYU'.strtoupper(substr(hash('sha256', implode('|', [
            $checkout->userId,
            $checkout->planId,
            $checkout->amount,
            microtime(true),
            random_int(1000, 999999),
        ])), 0, 18));
    }
}
