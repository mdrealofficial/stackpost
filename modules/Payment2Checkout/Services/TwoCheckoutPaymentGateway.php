<?php

namespace Modules\Payment2Checkout\Services;

use Exception;
use Illuminate\Http\Request;
use Modules\AdminPlans\Models\AdminPlan;
use Modules\AdminUser\Models\User;
use Modules\AppPayments\Contracts\PaymentGateway;
use Modules\AppPayments\Support\PaymentCallbackResult;
use Modules\AppPayments\Support\PaymentCheckout;
use Modules\AppPayments\Support\PaymentStartResult;

class TwoCheckoutPaymentGateway implements PaymentGateway
{
    protected string $sellerId;

    protected string $secretKey;

    protected bool $live;

    protected string $paymentUrl;

    public function __construct()
    {
        $this->sellerId = (string) get_option('2checkout_seller_id', '');
        $this->secretKey = (string) get_option('2checkout_secret_key', '');
        $this->live = (bool) get_option('2checkout_environment', 0);
        $this->paymentUrl = 'https://secure.2checkout.com/order/checkout.php';
    }

    public function start(PaymentCheckout $checkout): PaymentStartResult
    {
        $plan = AdminPlan::query()->find($checkout->planId);
        $user = User::query()->find($checkout->userId);
        $orderId = $this->orderId($checkout);

        $params = [
            'sid' => $this->sellerId,
            'mode' => '2CO',
            'li_0_type' => 'product',
            'li_0_name' => trim((string) ($plan?->name ?: data_get($checkout->meta, 'plan_name', __('Subscription')))),
            'li_0_price' => number_format((float) $checkout->amount, 2, '.', ''),
            'li_0_quantity' => 1,
            'li_0_tangible' => 'N',
            'merchant_order_id' => $orderId,
            'currency_code' => strtoupper($checkout->currency),
            'return_url' => $checkout->returnUrl,
            'x_receipt_link_url' => $checkout->returnUrl,
            'email' => (string) ($user?->email ?: 'customer@example.com'),
        ];

        if (! $this->live) {
            $params['demo'] = 'Y';
        }

        session([
            'twocheckout_checkout' => [
                'order_id' => $orderId,
                'params' => $params,
                'checkout_url' => $this->paymentUrl,
                'plan_name' => $plan?->name ?? data_get($checkout->meta, 'plan_name', __('Subscription')),
            ],
        ]);

        return PaymentStartResult::redirect(route('payment.2checkout.checkout'));
    }

    public function complete(Request $request, PaymentCheckout $checkout): PaymentCallbackResult
    {
        $orderId = (string) ($request->input('merchant_order_id') ?: $request->query('merchant_order_id') ?: session('twocheckout_checkout.order_id'));
        $saleId = (string) ($request->input('sale_id') ?: $request->query('sale_id') ?: '');
        $invoiceId = (string) ($request->input('invoice_id') ?: $request->query('invoice_id') ?: '');
        $status = strtoupper((string) ($request->input('sale_status') ?: $request->query('sale_status') ?: ''));

        if ($orderId === '' && $saleId === '' && $invoiceId === '') {
            throw new Exception(__('Missing 2Checkout callback data.'));
        }

        if (! in_array($status, ['APPROVED', 'COMPLETE', 'SUCCESS', ''], true)) {
            return PaymentCallbackResult::failed(
                __('2Checkout payment did not complete successfully.'),
                ['twocheckout_payload' => $request->all()]
            );
        }

        $amount = (float) ($request->input('total') ?: $checkout->amount);
        $currency = strtoupper((string) ($request->input('currency_code') ?: $checkout->currency));
        $transactionId = $saleId !== '' ? $saleId : ($invoiceId !== '' ? $invoiceId : $orderId);

        session()->forget('twocheckout_checkout');

        return PaymentCallbackResult::success(
            transactionId: $transactionId,
            amount: $amount,
            currency: $currency,
            meta: [
                'twocheckout_payload' => $request->all(),
            ],
            subscriptionId: $orderId !== '' ? $orderId : null,
            customerId: (string) ($request->input('email') ?: ''),
            message: __('2Checkout payment captured successfully.')
        );
    }

    public function webhook(Request $request): mixed
    {
        return [
            'status' => 'ignored',
            'payload' => $request->all(),
        ];
    }

    protected function orderId(PaymentCheckout $checkout): string
    {
        return 'TCO'.strtoupper(substr(hash('sha256', implode('|', [
            $checkout->userId,
            $checkout->planId,
            $checkout->amount,
            microtime(true),
            random_int(1000, 999999),
        ])), 0, 18));
    }
}
