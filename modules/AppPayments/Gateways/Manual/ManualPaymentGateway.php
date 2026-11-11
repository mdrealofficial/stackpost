<?php

namespace Modules\AppPayments\Gateways\Manual;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\AdminSettings\Support\OptionStore;
use Modules\AdminManualPayments\Models\ManualPayment;
use Modules\AppPayments\Contracts\PaymentGateway;
use Modules\AppPayments\Support\PaymentCallbackResult;
use Modules\AppPayments\Support\PaymentCheckout;
use Modules\AppPayments\Support\PaymentStartResult;

class ManualPaymentGateway implements PaymentGateway
{
    public function start(PaymentCheckout $checkout): PaymentStartResult
    {
        $prefix = (string) app(OptionStore::class)->get('payment_manual_prefix', 'PAY-');
        $paymentId = $prefix.Str::upper(Str::random(8));

        $manualPayment = ManualPayment::query()->create([
            'id_secure' => Str::random(32),
            'uid' => $checkout->userId,
            'plan_id' => $checkout->planId,
            'payment_id' => $paymentId,
            'payment_info' => null,
            'amount' => $checkout->amount,
            'currency' => $checkout->currency,
            'notes' => __('Created from the manual payment gateway checkout.'),
            'status' => 0,
            'created' => time(),
            'changed' => time(),
        ]);

        return PaymentStartResult::redirect(
            route('payment.success', ['gateway' => 'manual', 'manual_payment' => $manualPayment->id_secure])
        );
    }

    public function complete(Request $request, PaymentCheckout $checkout): PaymentCallbackResult
    {
        return PaymentCallbackResult::pending(
            __('Your manual payment request has been created and is waiting for admin approval.'),
            array_filter([
                'manual_payment' => $request->string('manual_payment')->toString(),
            ])
        );
    }

    public function webhook(Request $request): mixed
    {
        return [
            'status' => 'ignored',
            'message' => 'Manual gateway does not use webhooks.',
        ];
    }
}
