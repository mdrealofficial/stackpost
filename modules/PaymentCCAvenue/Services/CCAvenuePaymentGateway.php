<?php

namespace Modules\PaymentCCAvenue\Services;

use Exception;
use Illuminate\Http\Request;
use Modules\AdminPlans\Models\AdminPlan;
use Modules\AdminUser\Models\User;
use Modules\AppPayments\Contracts\PaymentGateway;
use Modules\AppPayments\Support\PaymentCallbackResult;
use Modules\AppPayments\Support\PaymentCheckout;
use Modules\AppPayments\Support\PaymentStartResult;

class CCAvenuePaymentGateway implements PaymentGateway
{
    protected string $merchantId;

    protected string $accessCode;

    protected string $workingKey;

    protected bool $live;

    protected string $paymentUrl;

    public function __construct()
    {
        $this->merchantId = (string) get_option('ccavenue_merchant_id', '');
        $this->accessCode = (string) get_option('ccavenue_access_code', '');
        $this->workingKey = (string) get_option('ccavenue_working_key', '');
        $this->live = (bool) get_option('ccavenue_environment', 0);
        $this->paymentUrl = $this->live
            ? 'https://secure.ccavenue.com/transaction/transaction.do?command=initiateTransaction'
            : 'https://test.ccavenue.com/transaction/transaction.do?command=initiateTransaction';
    }

    public function start(PaymentCheckout $checkout): PaymentStartResult
    {
        if (strtoupper($checkout->currency) !== 'INR') {
            throw new Exception(__('CCAvenue currently supports INR only in this module.'));
        }

        $plan = AdminPlan::query()->find($checkout->planId);
        $user = User::query()->find($checkout->userId);
        $orderId = $this->orderId($checkout);

        $params = [
            'merchant_id' => $this->merchantId,
            'order_id' => $orderId,
            'currency' => 'INR',
            'amount' => number_format((float) $checkout->amount, 2, '.', ''),
            'redirect_url' => $checkout->returnUrl,
            'cancel_url' => $checkout->cancelUrl,
            'language' => 'EN',
            'billing_name' => trim((string) ($user?->name ?: $user?->username ?: __('Customer'))),
            'billing_email' => trim((string) ($user?->email ?: 'customer@example.com')),
        ];

        $encrypted = $this->encrypt(http_build_query($params), $this->workingKey);

        session([
            'ccavenue_checkout' => [
                'order_id' => $orderId,
                'params' => $params,
                'enc_request' => $encrypted,
                'checkout_url' => $this->paymentUrl.'&encRequest='.$encrypted.'&access_code='.$this->accessCode,
                'plan_name' => $plan?->name ?? data_get($checkout->meta, 'plan_name', __('Subscription')),
            ],
        ]);

        return PaymentStartResult::redirect(route('payment.ccavenue.checkout'));
    }

    public function complete(Request $request, PaymentCheckout $checkout): PaymentCallbackResult
    {
        $encResp = (string) ($request->input('encResp') ?: session('ccavenue_checkout.enc_response', ''));

        if ($encResp === '') {
            throw new Exception(__('CCAvenue response missing.'));
        }

        $decrypted = $this->decrypt($encResp, $this->workingKey);
        parse_str($decrypted, $response);

        $status = (string) ($response['order_status'] ?? 'Unknown');

        if ($status !== 'Success') {
            return PaymentCallbackResult::failed(
                __('CCAvenue payment did not complete successfully.'),
                ['ccavenue_payload' => $response]
            );
        }

        session()->forget('ccavenue_checkout');

        return PaymentCallbackResult::success(
            transactionId: (string) ($response['tracking_id'] ?? $response['order_id'] ?? ''),
            amount: (float) ($response['amount'] ?? $checkout->amount),
            currency: strtoupper((string) ($response['currency'] ?? $checkout->currency)),
            meta: [
                'ccavenue_payload' => $response,
            ],
            subscriptionId: (string) ($response['order_id'] ?? ''),
            customerId: (string) ($response['billing_email'] ?? ''),
            message: __('CCAvenue payment captured successfully.')
        );
    }

    public function webhook(Request $request): mixed
    {
        return [
            'status' => 'ignored',
            'payload' => $request->all(),
        ];
    }

    protected function encrypt(string $plainText, string $key): string
    {
        $key = pack('H*', md5($key));
        $iv = pack('C*', ...range(0, 15));
        $encrypted = openssl_encrypt($plainText, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);

        return bin2hex($encrypted ?: '');
    }

    protected function decrypt(string $encryptedText, string $key): string
    {
        $key = pack('H*', md5($key));
        $iv = pack('C*', ...range(0, 15));
        $encrypted = hex2bin($encryptedText);

        return (string) openssl_decrypt($encrypted ?: '', 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
    }

    protected function orderId(PaymentCheckout $checkout): string
    {
        return 'CCA'.strtoupper(substr(hash('sha256', implode('|', [
            $checkout->userId,
            $checkout->planId,
            $checkout->amount,
            microtime(true),
            random_int(1000, 999999),
        ])), 0, 18));
    }
}
