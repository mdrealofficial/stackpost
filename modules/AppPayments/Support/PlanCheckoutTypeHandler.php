<?php

namespace Modules\AppPayments\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\AdminPaymentHistory\Models\PaymentHistory;
use Modules\AdminPaymentSubscriptions\Models\PaymentSubscription;
use Modules\AdminPlans\Models\AdminPlan;
use Modules\AdminUser\Models\User;
use Modules\AppAffiliate\Support\AffiliateService;
use Modules\AppPayments\Contracts\PaymentCheckoutTypeHandler;

class PlanCheckoutTypeHandler implements PaymentCheckoutTypeHandler
{
    public function __construct(
        protected AffiliateService $affiliate,
        protected UserPlanTransitionService $planTransitions,
        protected PaymentManager $payments,
    ) {}

    public function finalize(PaymentCheckout $checkout, PaymentCallbackResult $result): PaymentHistory
    {
        $user = User::query()->findOrFail($checkout->userId);
        $plan = AdminPlan::query()->findOrFail($checkout->planId);
        $transition = $this->planTransitions->applyPurchasedPlan($user, $plan);

        $transactionId = $result->transactionId ?: Str::upper(Str::random(20));
        $amount = $result->amount ?? $checkout->amount;
        $currency = strtoupper($result->currency ?: $checkout->currency);
        $now = time();

        $paymentHistory = PaymentHistory::query()->firstOrCreate(
            ['transaction_id' => $transactionId],
            [
                'id_secure' => Str::random(32),
                'uid' => $user->id,
                'plan_id' => $plan->id,
                'from' => $checkout->gateway,
                'currency' => $currency,
                'by' => $checkout->gatewayType,
                'amount' => $amount,
                'status' => 1,
                'changed' => $now,
                'created' => $now,
                'meta' => array_filter([
                    'checkout' => $checkout->toArray(),
                    'gateway_meta' => $result->meta,
                    'message' => $result->message,
                    'transition' => $transition,
                ]),
            ]
        );

        PaymentSubscription::query()->updateOrCreate(
            [
                'uid' => $user->id,
                'subscription_id' => (string) ($result->subscriptionId ?: $transactionId),
            ],
            [
                'id_secure' => Str::random(32),
                'plan_id' => $plan->id,
                'type' => (int) ($plan->type ?? 1),
                'service' => $checkout->gateway,
                'source' => $checkout->gateway,
                'customer_id' => $result->customerId ?: 'user-'.$user->id,
                'amount' => $amount,
                'currency' => $currency,
                'status' => 1,
                'changed' => $now,
                'created' => $now,
            ]
        );

        $this->cancelLegacyRecurringSubscriptions($user, $checkout, $result);
        $this->affiliate->applyCommissionFromPaymentHistory($paymentHistory);

        return $paymentHistory;
    }

    public function retryUrl(PaymentCheckout $checkout): string
    {
        return route('payment.index', $checkout->meta['plan_slug'] ?? $checkout->gateway);
    }

    protected function cancelLegacyRecurringSubscriptions(User $user, PaymentCheckout $checkout, PaymentCallbackResult $result): void
    {
        if ($checkout->gatewayType !== 'recurring' || blank($result->subscriptionId)) {
            return;
        }

        $currentSubscriptionId = (string) $result->subscriptionId;
        $subscriptions = PaymentSubscription::query()
            ->where('uid', $user->id)
            ->where('status', 1)
            ->where('subscription_id', '!=', $currentSubscriptionId)
            ->orderBy('id')
            ->get();

        foreach ($subscriptions as $subscription) {
            if (! $this->isRecurringService((string) $subscription->service)) {
                continue;
            }

            $cancelled = $this->cancelRemoteRecurringSubscription($subscription, $checkout);

            if (! $cancelled) {
                continue;
            }

            $subscription->forceFill([
                'status' => 2,
                'changed' => time(),
            ])->save();
        }
    }

    protected function cancelRemoteRecurringSubscription(PaymentSubscription $subscription, PaymentCheckout $checkout): bool
    {
        try {
            $service = (string) $subscription->service;

            if (! $this->payments->has($service) || ! $this->payments->supports($service, 'system_cancel_recurring')) {
                return $this->logUnsupportedRecurringCancellation($subscription);
            }

            return (bool) $this->payments->call($service, 'cancel_recurring', [
                'subscriptionId' => (string) $subscription->subscription_id,
                'reason' => 'Subscription replaced by a new confirmed billing agreement.',
                'atPeriodEnd' => data_get($checkout->meta, 'transition.mode') === 'downgrade_scheduled',
            ]);
        } catch (\Throwable $e) {
            Log::error('Recurring subscription auto-cancel failed.', [
                'subscription_id' => $subscription->subscription_id,
                'service' => $subscription->service,
                'uid' => $subscription->uid,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    protected function logUnsupportedRecurringCancellation(PaymentSubscription $subscription): bool
    {
        Log::warning('Recurring subscription could not be auto-cancelled because the gateway does not expose a cancellation handler.', [
            'subscription_id' => $subscription->subscription_id,
            'service' => $subscription->service,
            'uid' => $subscription->uid,
        ]);

        return false;
    }

    protected function isRecurringService(string $service): bool
    {
        return str_contains($service, 'recurring');
    }
}
