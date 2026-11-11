<?php

namespace Modules\PaymentPayTR\Services;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\AdminPlans\Models\AdminPlan;
use Modules\AdminUser\Models\User;
use Modules\AppPayments\Contracts\PaymentGateway;
use Modules\AppPayments\Support\PaymentCallbackResult;
use Modules\AppPayments\Support\PaymentCheckout;
use Modules\AppPayments\Support\PaymentStartResult;

class PayTRPaymentGateway implements PaymentGateway
{
    protected string $merchantId;

    protected string $merchantKey;

    protected string $merchantSalt;

    protected bool $live;

    protected string $apiBase;

    public function __construct()
    {
        $this->merchantId = (string) get_option('paytr_merchant_id', '');
        $this->merchantKey = (string) get_option('paytr_merchant_key', '');
        $this->merchantSalt = (string) get_option('paytr_merchant_salt', '');
        $this->live = (bool) get_option('paytr_environment', 0);
        $this->apiBase = 'https://www.paytr.com';
    }

    public function start(PaymentCheckout $checkout): PaymentStartResult
    {
        $plan = AdminPlan::query()->find($checkout->planId);
        $user = User::query()->find($checkout->userId);

        $currency = $this->normalizeCurrency($checkout->currency);

        if ($currency !== 'TRY') {
            throw new Exception(__('PayTR currently supports TRY only in this module.'));
        }

        $merchantOid = $this->merchantOid($checkout);
        $amount = $this->paymentAmount($checkout->amount);
        $basket = base64_encode(json_encode([
            [
                trim((string) ($plan?->name ?: data_get($checkout->meta, 'plan_name', __('Subscription')))),
                number_format((float) $checkout->amount, 2, '.', ''),
                1,
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]');
        $testMode = $this->live ? 0 : 1;
        $noInstallment = (int) get_option('paytr_no_installment', 0);
        $maxInstallment = (int) get_option('paytr_max_installment', 0);
        $userIp = request()->ip() ?: '127.0.0.1';

        $payload = [
            'merchant_id' => $this->merchantId,
            'user_ip' => $userIp,
            'merchant_oid' => $merchantOid,
            'email' => (string) ($user?->email ?: 'customer@example.com'),
            'payment_amount' => (string) $amount,
            'user_basket' => $basket,
            'no_installment' => (string) $noInstallment,
            'max_installment' => (string) $maxInstallment,
            'currency' => $currency,
            'test_mode' => (string) $testMode,
            'debug_on' => (string) ($this->live ? 0 : 1),
            'lang' => $this->language(),
            'user_name' => trim((string) ($user?->name ?: $user?->username ?: __('Customer'))),
            'user_address' => trim((string) ($user?->address ?: $user?->location ?: __('Stackposts customer'))),
            'user_phone' => trim((string) ($user?->phone ?: '+905555555555')),
            'merchant_ok_url' => route('payment.success', ['gateway' => 'paytr']),
            'merchant_fail_url' => route('payment.cancel', ['gateway' => 'paytr']),
            'timeout_limit' => '30',
        ];

        $payload['paytr_token'] = $this->token($payload);

        $response = Http::timeout(30)
            ->asForm()
            ->post($this->apiBase.'/odeme/api/get-token', $payload);

        if (! $response->successful()) {
            throw new Exception(sprintf('PayTR API error: %s', $response->body()));
        }

        $result = $response->json() ?: [];
        $status = (string) data_get($result, 'status', '');
        $token = (string) data_get($result, 'token', '');

        if ($status !== 'success' || $token === '') {
            throw new Exception((string) data_get($result, 'reason', __('Failed to create PayTR iframe token.')));
        }

        session([
            'paytr_checkout' => [
                'merchant_oid' => $merchantOid,
                'token' => $token,
                'payment_url' => $this->apiBase.'/odeme/guvenli/'.$token,
                'plan_name' => $plan?->name ?? data_get($checkout->meta, 'plan_name', __('Subscription')),
            ],
        ]);

        return PaymentStartResult::redirect(route('payment.paytr.checkout'));
    }

    public function complete(Request $request, PaymentCheckout $checkout): PaymentCallbackResult
    {
        $merchantOid = (string) ($request->input('merchant_oid') ?: $request->query('merchant_oid') ?: session('paytr_checkout.merchant_oid', ''));

        if ($merchantOid === '') {
            throw new Exception(__('Missing PayTR merchant oid.'));
        }

        $response = Http::timeout(90)
            ->asForm()
            ->post($this->apiBase.'/odeme/durum-sorgu', [
                'merchant_id' => $this->merchantId,
                'merchant_oid' => $merchantOid,
                'paytr_token' => $this->statusToken($merchantOid),
            ]);

        if (! $response->successful()) {
            throw new Exception(sprintf('PayTR status inquiry error: %s', $response->body()));
        }

        $result = $response->json() ?: [];
        $status = strtolower((string) data_get($result, 'status', 'failed'));

        if ($status !== 'success') {
            return PaymentCallbackResult::failed(
                __('PayTR payment did not complete successfully.'),
                ['paytr_payload' => $result]
            );
        }

        $transactionId = (string) (data_get($result, 'trans_id') ?: data_get($result, 'merchant_oid') ?: $merchantOid);
        $amount = (float) data_get($result, 'payment_total', data_get($result, 'payment_amount', $checkout->amount));
        $currency = strtoupper((string) data_get($result, 'currency', $this->normalizeCurrency($checkout->currency)));

        session()->forget('paytr_checkout');

        return PaymentCallbackResult::success(
            transactionId: $transactionId,
            amount: $amount,
            currency: $currency,
            meta: [
                'paytr_payload' => $result,
                'paytr_merchant_oid' => $merchantOid,
            ],
            subscriptionId: $merchantOid,
            customerId: (string) data_get($result, 'email', ''),
            message: __('PayTR payment captured successfully.')
        );
    }

    public function webhook(Request $request): mixed
    {
        $status = (string) $request->input('status', '');
        $merchantOid = (string) $request->input('merchant_oid', '');
        $totalAmount = (string) $request->input('total_amount', '');
        $hash = (string) $request->input('hash', '');

        if ($merchantOid === '' || $status === '' || $totalAmount === '' || $hash === '') {
            throw new Exception(__('Missing PayTR callback data.'));
        }

        $expectedHash = base64_encode(hash_hmac('sha256', $merchantOid.$this->merchantSalt.$status.$totalAmount, $this->merchantKey, true));

        if (! hash_equals($expectedHash, $hash)) {
            throw new Exception(__('Invalid PayTR callback hash.'));
        }

        return 'OK';
    }

    protected function token(array $payload): string
    {
        $hashString = implode('', [
            $payload['merchant_id'] ?? '',
            $payload['user_ip'] ?? '',
            $payload['merchant_oid'] ?? '',
            $payload['email'] ?? '',
            $payload['payment_amount'] ?? '',
            $payload['user_basket'] ?? '',
            $payload['no_installment'] ?? '',
            $payload['max_installment'] ?? '',
            $payload['currency'] ?? '',
            $payload['test_mode'] ?? '',
        ]);

        return base64_encode(hash_hmac('sha256', $hashString.$this->merchantSalt, $this->merchantKey, true));
    }

    protected function statusToken(string $merchantOid): string
    {
        return base64_encode(hash_hmac('sha256', $this->merchantId.$merchantOid.$this->merchantSalt, $this->merchantKey, true));
    }

    protected function merchantOid(PaymentCheckout $checkout): string
    {
        return 'PTR'.strtoupper(substr(hash('sha256', implode('|', [
            $checkout->userId,
            $checkout->planId,
            $checkout->amount,
            microtime(true),
            random_int(1000, 999999),
        ])), 0, 18));
    }

    protected function paymentAmount(float $amount): int
    {
        return (int) round(max(0, $amount) * 100);
    }

    protected function normalizeCurrency(string $currency): string
    {
        $currency = strtoupper(trim($currency));

        return $currency === 'TL' ? 'TRY' : $currency;
    }

    protected function language(): string
    {
        $locale = strtolower((string) get_option('paytr_lang', 'en'));

        return in_array($locale, ['en', 'tr'], true) ? $locale : 'en';
    }
}
