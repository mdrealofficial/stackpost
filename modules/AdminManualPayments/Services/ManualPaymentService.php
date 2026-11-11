<?php

namespace Modules\AdminManualPayments\Services;

use Illuminate\Support\Str;
use Modules\AdminManualPayments\Models\ManualPayment;
use Modules\AdminPaymentHistory\Models\PaymentHistory;
use Modules\AdminPaymentSubscriptions\Models\PaymentSubscription;
use Modules\AdminPlans\Models\AdminPlan;
use Modules\AppAffiliate\Support\AffiliateService;
use Modules\AppPayments\Support\PaymentNotificationService;
use Modules\AppPayments\Support\UserPlanTransitionService;

class ManualPaymentService
{
    public function __construct(
        protected AffiliateService $affiliate,
        protected UserPlanTransitionService $planTransitions,
        protected PaymentNotificationService $notifications,
    ) {}

    public function approve(ManualPayment $manualPayment): void
    {
        $manualPayment->loadMissing(['user', 'plan']);

        $user = $manualPayment->user;
        $plan = $manualPayment->plan;

        if (! $user || ! $plan) {
            return;
        }

        $transition = $this->planTransitions->applyPurchasedPlan($user, $plan);

        $paymentHistory = PaymentHistory::query()->firstOrCreate(
            ['transaction_id' => (string) $manualPayment->payment_id],
            [
                'id_secure' => Str::random(32),
                'uid' => $user->id,
                'plan_id' => $plan->id,
                'from' => 'manual',
                'currency' => strtoupper((string) $manualPayment->currency),
                'by' => 'admin',
                'amount' => $manualPayment->amount,
                'status' => 1,
                'changed' => time(),
                'created' => $manualPayment->created ?: time(),
                'meta' => [
                    'payment_info' => $manualPayment->payment_info,
                    'notes' => $manualPayment->notes,
                    'manual_payment_id' => $manualPayment->id,
                    'transition' => $transition,
                ],
            ]
        );

        PaymentSubscription::query()->updateOrCreate(
            [
                'uid' => $user->id,
                'subscription_id' => (string) $manualPayment->payment_id,
            ],
            [
                'id_secure' => Str::random(32),
                'plan_id' => $plan->id,
                'type' => (int) ($plan->type ?? 1),
                'service' => 'manual',
                'source' => 'manual',
                'customer_id' => 'user-'.$user->id,
                'amount' => $manualPayment->amount,
                'currency' => strtoupper((string) $manualPayment->currency),
                'status' => 1,
                'changed' => time(),
                'created' => $manualPayment->created ?: time(),
            ]
        );

        $manualPayment->forceFill([
            'status' => 1,
            'changed' => time(),
        ])->save();

        $this->affiliate->applyCommissionFromPaymentHistory($paymentHistory);

        $this->notifications->notify(
            $user,
            'success',
            $this->notifications->buildContext(
                user: $user,
                plan: $plan,
                paymentHistory: $paymentHistory,
                extra: [
                    'gateway' => 'manual',
                    'status' => 'success',
                    'message' => __('Your manual payment was approved successfully.'),
                ],
            )
        );
    }
}
