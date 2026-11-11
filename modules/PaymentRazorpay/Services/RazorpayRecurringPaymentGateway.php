<?php

namespace Modules\PaymentRazorpay\Services;

use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Modules\AdminPlans\Models\AdminPlan;
use Modules\AdminSettings\Support\OptionStore;
use Modules\AdminUser\Models\User;
use Modules\AppPayments\Contracts\PaymentGateway;
use Modules\AppPayments\Support\PaymentCallbackResult;
use Modules\AppPayments\Support\PaymentCheckout;
use Modules\AppPayments\Support\PaymentStartResult;

class RazorpayRecurringPaymentGateway extends AbstractRazorpayGateway implements PaymentGateway
{
    public function start(PaymentCheckout $checkout): PaymentStartResult
    {
        $plan = AdminPlan::query()->find($checkout->planId);
        $user = User::query()->find($checkout->userId);

        if (! $plan instanceof AdminPlan) {
            throw new Exception(__('The selected plan could not be found.'));
        }

        if (! in_array((int) $plan->type, [1, 2], true)) {
            throw new Exception(__('Razorpay recurring payment is only available for monthly or yearly plans.'));
        }

        $planId = $this->getOrCreatePlanId($plan, $checkout);
        $transitionMode = (string) data_get($checkout->meta, 'transition.mode', '');
        $effectiveAt = (string) data_get($checkout->meta, 'transition.effective_at', '');

        $payload = [
            'plan_id' => $planId,
            'total_count' => (int) $plan->type === 2 ? 100 : 1200,
            'quantity' => 1,
            'customer_notify' => true,
            'notes' => [
                'user_id' => (string) $checkout->userId,
                'plan_id' => (string) $checkout->planId,
                'gateway' => 'razorpay_recurring',
                'transition_mode' => $transitionMode,
            ],
        ];

        if ($transitionMode === 'downgrade_scheduled' && $effectiveAt !== '') {
            $payload['start_at'] = Carbon::parse($effectiveAt)->utc()->timestamp;
        }

        $subscription = $this->apiRequest('POST', '/subscriptions', $payload);
        $subscriptionId = trim((string) ($subscription['id'] ?? ''));

        if ($subscriptionId === '') {
            throw new Exception(__('Razorpay subscription ID was not returned.'));
        }

        $redirectUrl = route('payment.razorpay.checkout', [
            'gateway' => 'razorpay_recurring',
            'subscription_id' => $subscriptionId,
            'name' => $plan->name,
            'description' => $plan->desc ?: $plan->name,
            'callback_url' => $checkout->returnUrl,
            'cancel_url' => $checkout->cancelUrl,
            'prefill_name' => $user?->name ?: $user?->username ?: 'Customer',
            'prefill_email' => $user?->email ?: '',
        ]);

        return PaymentStartResult::redirect($redirectUrl, [
            'razorpay_subscription_id' => $subscriptionId,
        ]);
    }

    public function complete(Request $request, PaymentCheckout $checkout): PaymentCallbackResult
    {
        $subscriptionId = trim((string) $request->input('razorpay_subscription_id', ''));
        $paymentId = trim((string) $request->input('razorpay_payment_id', ''));
        $signature = trim((string) $request->input('razorpay_signature', ''));

        if (! $this->verifySignature($subscriptionId, $paymentId, $signature)) {
            throw new Exception(__('Invalid Razorpay subscription signature.'));
        }

        $subscription = $this->apiRequest('GET', '/subscriptions/'.$subscriptionId);
        $payment = $paymentId !== '' ? $this->apiRequest('GET', '/payments/'.$paymentId) : [];
        $status = strtolower((string) ($subscription['status'] ?? ''));

        if (! in_array($status, ['active', 'authenticated'], true)) {
            return PaymentCallbackResult::failed(
                __('Razorpay returned :status for this subscription.', ['status' => strtoupper($status ?: 'UNKNOWN')]),
                ['razorpay_subscription_id' => $subscriptionId]
            );
        }

        $currency = strtoupper((string) ($payment['currency'] ?? $checkout->currency));
        $amount = $payment !== []
            ? $this->majorAmount($payment['amount'] ?? null, $currency)
            : (float) $checkout->amount;

        return PaymentCallbackResult::success(
            transactionId: $paymentId !== '' ? $paymentId : $subscriptionId,
            amount: $amount,
            currency: $currency,
            meta: [
                'razorpay_subscription_id' => $subscriptionId,
                'razorpay_payment_id' => $paymentId,
                'razorpay_subscription_payload' => $subscription,
                'razorpay_payment_payload' => $payment,
            ],
            subscriptionId: $subscriptionId,
            customerId: (string) ($subscription['customer_id'] ?? ''),
            message: __('Razorpay recurring subscription approved successfully.')
        );
    }

    public function webhook(Request $request): mixed
    {
        $event = $this->parseWebhook($request);

        return [
            'event' => (string) ($event['event'] ?? ''),
            'entity' => (string) data_get($event, 'payload.subscription.entity.id', data_get($event, 'payload.payment.entity.id', '')),
            'status' => (string) data_get($event, 'payload.subscription.entity.status', data_get($event, 'payload.payment.entity.status', '')),
        ];
    }

    public function cancelSubscription(string $subscriptionId, bool $atPeriodEnd = false): bool
    {
        $subscriptionId = trim($subscriptionId);

        if ($subscriptionId === '') {
            return false;
        }

        try {
            $this->apiRequest('POST', '/subscriptions/'.$subscriptionId.'/cancel', [
                'cancel_at_cycle_end' => $atPeriodEnd,
            ]);

            return true;
        } catch (\Throwable $e) {
            return str_contains(strtolower($e->getMessage()), 'not cancellable');
        }
    }

    protected function getOrCreatePlanId(AdminPlan $plan, PaymentCheckout $checkout): string
    {
        $options = app(OptionStore::class);
        $planMap = json_decode((string) $options->get('razorpay_plan_map', '{}'), true);
        $planMap = is_array($planMap) ? $planMap : [];
        $cacheKey = $this->planCacheKey($plan, $checkout);
        $existingPlanId = trim((string) ($planMap[$cacheKey] ?? ''));

        if ($existingPlanId !== '') {
            return $existingPlanId;
        }

        $currency = $this->normalizeCurrency($checkout->currency);
        $created = $this->apiRequest('POST', '/plans', [
            'period' => (int) $plan->type === 2 ? 'yearly' : 'monthly',
            'interval' => 1,
            'item' => [
                'name' => $this->shortText($plan->name, 127),
                'amount' => $this->unitAmount($checkout->amount, $currency),
                'currency' => $currency,
                'description' => $this->shortText((string) ($plan->desc ?: $plan->name), 255),
            ],
            'notes' => [
                'plan_id' => (string) $plan->id,
                'plan_type' => (string) $plan->type,
            ],
        ]);

        $createdPlanId = trim((string) ($created['id'] ?? ''));

        if ($createdPlanId === '') {
            throw new Exception(__('Razorpay recurring plan ID was not returned.'));
        }

        $planMap[$cacheKey] = $createdPlanId;
        $options->set('razorpay_plan_map', json_encode($planMap));

        return $createdPlanId;
    }

    protected function planCacheKey(AdminPlan $plan, PaymentCheckout $checkout): string
    {
        return implode(':', [
            (int) $plan->id,
            (int) $plan->type,
            $this->normalizeCurrency($checkout->currency),
            number_format((float) $checkout->amount, 2, '.', ''),
        ]);
    }
}
