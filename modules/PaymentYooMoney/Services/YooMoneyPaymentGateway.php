<?php

namespace Modules\PaymentYooMoney\Services;

use Exception;
use Illuminate\Http\Request;
use Modules\AdminPlans\Models\AdminPlan;
use Modules\AdminSettings\Support\OptionStore;
use Modules\AdminUser\Models\User;
use Modules\AppPayments\Contracts\PaymentGateway;
use Modules\AppPayments\Support\PaymentCallbackResult;
use Modules\AppPayments\Support\PaymentCheckout;
use Modules\AppPayments\Support\PaymentStartResult;

class YooMoneyPaymentGateway extends AbstractYooMoneyGateway implements PaymentGateway
{
    public function start(PaymentCheckout $checkout): PaymentStartResult
    {
        $plan = AdminPlan::query()->find($checkout->planId);
        $user = User::query()->find($checkout->userId);
        $currency = $this->normalizeCurrency($checkout->currency);

        if ($currency !== 'RUB') {
            throw new Exception(__('YooMoney currently supports RUB only. Please switch the system currency to RUB before using this gateway.'));
        }

        $options = app(OptionStore::class);
        $paymentMethod = (string) $options->get('yoomoney_payment_method', 'yoo_money');
        $paymentMethod = in_array($paymentMethod, ['yoo_money', 'bank_card'], true) ? $paymentMethod : 'yoo_money';

        $payload = [
            'amount' => [
                'value' => number_format((float) $checkout->amount, 2, '.', ''),
                'currency' => $currency,
            ],
            'capture' => true,
            'confirmation' => [
                'type' => 'redirect',
                'return_url' => $checkout->returnUrl,
            ],
            'description' => $this->shortDescription($plan, $checkout),
            'payment_method_data' => [
                'type' => $paymentMethod,
            ],
            'metadata' => [
                'uid' => (string) $checkout->userId,
                'plan_id' => (string) $checkout->planId,
                'plan_type' => (string) ($plan?->type ?? ''),
                'gateway' => 'YooMoney',
                'mode' => (string) $this->mode,
            ],
        ];

        $result = $this->apiRequest('POST', '/payments', $payload, $this->generateIdempotenceKey('yoomoney_pay_'));

        $paymentId = (string) ($result['id'] ?? '');
        $confirmationUrl = (string) data_get($result, 'confirmation.confirmation_url', '');

        if ($paymentId === '' || $confirmationUrl === '') {
            throw new Exception(__('YooMoney confirmation URL was not returned.'));
        }

        session([
            'yoomoney_payment_id' => $paymentId,
            'yoomoney_plan_id' => $checkout->planId,
            'yoomoney_plan_type' => $plan?->type,
        ]);

        return PaymentStartResult::redirect($confirmationUrl, [
            'yoomoney_payment_id' => $paymentId,
        ]);
    }

    public function complete(Request $request, PaymentCheckout $checkout): PaymentCallbackResult
    {
        $paymentId = trim((string) ($request->query('payment_id') ?: $request->query('paymentId') ?: session('yoomoney_payment_id')));

        if ($paymentId === '') {
            throw new Exception(__('Missing YooMoney payment ID.'));
        }

        $payment = $this->fetchPayment($paymentId);

        if (($payment['status'] ?? '') !== 'succeeded' || ! ($payment['paid'] ?? false)) {
            return PaymentCallbackResult::pending(
                __('YooMoney payment has not been completed yet.'),
                ['yoomoney_payment_id' => $paymentId]
            );
        }

        session()->forget(['yoomoney_payment_id', 'yoomoney_plan_id', 'yoomoney_plan_type']);

        $currency = strtoupper((string) data_get($payment, 'amount.currency', $checkout->currency));
        $amount = (float) data_get($payment, 'amount.value', $checkout->amount);

        return PaymentCallbackResult::success(
            transactionId: (string) $payment['id'],
            amount: $amount,
            currency: $currency,
            meta: [
                'yoomoney_payment_id' => (string) $payment['id'],
                'yoomoney_payload' => $payment,
            ],
            subscriptionId: (string) $payment['id'],
            message: __('YooMoney payment captured successfully.')
        );
    }

    public function webhook(Request $request): mixed
    {
        $payload = $request->json()->all();

        if (! $this->verifyWebhook($payload) || (string) ($payload['event'] ?? '') !== 'payment.succeeded') {
            return [
                'status' => 'ignored',
                'event' => (string) ($payload['event'] ?? ''),
            ];
        }

        $paymentId = (string) data_get($payload, 'object.id', '');

        if ($paymentId === '') {
            return [
                'status' => 'ignored',
                'reason' => 'missing payment id',
            ];
        }

        $payment = $this->fetchPayment($paymentId);

        if (($payment['status'] ?? '') !== 'succeeded' || ! ($payment['paid'] ?? false)) {
            return [
                'status' => 'ignored',
                'payment_id' => $paymentId,
            ];
        }

        return [
            'status' => 'ok',
            'payment_id' => $paymentId,
            'payload' => $payment,
        ];
    }

    protected function shortDescription(?AdminPlan $plan, PaymentCheckout $checkout): string
    {
        return trim((string) ($plan?->name ?: data_get($checkout->meta, 'plan_name', 'YooMoney payment')));
    }
}
