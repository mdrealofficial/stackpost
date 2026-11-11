<?php

namespace Modules\PaymentRazorpay\Services;

use Exception;
use Illuminate\Http\Request;
use Modules\AdminPlans\Models\AdminPlan;
use Modules\AdminUser\Models\User;
use Modules\AppPayments\Contracts\PaymentGateway;
use Modules\AppPayments\Support\PaymentCallbackResult;
use Modules\AppPayments\Support\PaymentCheckout;
use Modules\AppPayments\Support\PaymentStartResult;

class RazorpayPaymentGateway extends AbstractRazorpayGateway implements PaymentGateway
{
    public function start(PaymentCheckout $checkout): PaymentStartResult
    {
        $plan = AdminPlan::query()->find($checkout->planId);
        $user = User::query()->find($checkout->userId);
        $currency = $this->normalizeCurrency($checkout->currency);

        $order = $this->apiRequest('POST', '/orders', [
            'amount' => $this->unitAmount($checkout->amount, $currency),
            'currency' => $currency,
            'receipt' => $this->shortText((string) ($checkout->meta['plan_slug'] ?? 'rzp-'.$checkout->userId.'-'.time()), 40),
            'payment_capture' => 1,
            'notes' => [
                'user_id' => (string) $checkout->userId,
                'plan_id' => (string) $checkout->planId,
                'gateway' => 'razorpay',
            ],
        ]);

        $orderId = trim((string) ($order['id'] ?? ''));

        if ($orderId === '') {
            throw new Exception(__('Razorpay order ID was not returned.'));
        }

        $redirectUrl = route('payment.razorpay.checkout', [
            'gateway' => 'razorpay',
            'order_id' => $orderId,
            'amount' => $this->unitAmount($checkout->amount, $currency),
            'currency' => $currency,
            'name' => $plan?->name ?: data_get($checkout->meta, 'plan_name', 'Plan purchase'),
            'description' => $plan?->desc ?: data_get($checkout->meta, 'plan_name', 'Razorpay payment'),
            'callback_url' => $checkout->returnUrl,
            'cancel_url' => $checkout->cancelUrl,
            'prefill_name' => $user?->name ?: $user?->username ?: 'Customer',
            'prefill_email' => $user?->email ?: '',
        ]);

        return PaymentStartResult::redirect($redirectUrl, [
            'razorpay_order_id' => $orderId,
        ]);
    }

    public function complete(Request $request, PaymentCheckout $checkout): PaymentCallbackResult
    {
        $orderId = trim((string) $request->input('razorpay_order_id', ''));
        $paymentId = trim((string) $request->input('razorpay_payment_id', ''));
        $signature = trim((string) $request->input('razorpay_signature', ''));

        if (! $this->verifySignature($orderId, $paymentId, $signature)) {
            throw new Exception(__('Invalid Razorpay payment signature.'));
        }

        $payment = $this->apiRequest('GET', '/payments/'.$paymentId);
        $status = strtolower((string) ($payment['status'] ?? ''));

        if (! in_array($status, ['captured', 'authorized'], true)) {
            return PaymentCallbackResult::failed(
                __('Razorpay returned :status for this payment.', ['status' => strtoupper($status ?: 'UNKNOWN')]),
                ['razorpay_payment_id' => $paymentId]
            );
        }

        $currency = strtoupper((string) ($payment['currency'] ?? $checkout->currency));
        $amount = $this->majorAmount($payment['amount'] ?? null, $currency);

        return PaymentCallbackResult::success(
            transactionId: $paymentId,
            amount: $amount,
            currency: $currency,
            meta: [
                'razorpay_order_id' => $orderId,
                'razorpay_payment_id' => $paymentId,
                'razorpay_payload' => $payment,
            ],
            customerId: (string) ($payment['customer_id'] ?? ''),
            message: __('Razorpay payment captured successfully.')
        );
    }

    public function webhook(Request $request): mixed
    {
        $event = $this->parseWebhook($request);

        return [
            'event' => (string) ($event['event'] ?? ''),
            'entity' => (string) data_get($event, 'payload.payment.entity.id', data_get($event, 'payload.order.entity.id', '')),
            'status' => (string) data_get($event, 'payload.payment.entity.status', data_get($event, 'payload.subscription.entity.status', '')),
        ];
    }
}
