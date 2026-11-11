<?php

namespace Modules\AppPayments\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Component;
use Modules\AdminCoupons\Models\Coupon;
use Modules\AdminManualPayments\Models\ManualPayment;
use Modules\AdminPaymentSubscriptions\Models\PaymentSubscription;
use Modules\AdminPlans\Models\AdminPlan;
use Modules\AdminSettings\Support\OptionStore;
use Modules\AppPayments\Support\PaymentCheckout;
use Modules\AppPayments\Support\PaymentCheckoutStore;
use Modules\AppPayments\Support\PaymentLifecycleService;
use Modules\AppPayments\Support\PaymentManager;
use Modules\AppPayments\Support\PaymentPlanResolver;
use Modules\AppPayments\Support\UserPlanTransitionService;

class CheckoutPage extends Component
{
    protected PaymentManager $payments;

    protected PaymentPlanResolver $plans;

    protected PaymentCheckoutStore $checkoutStore;

    protected PaymentLifecycleService $lifecycle;

    protected OptionStore $options;

    protected UserPlanTransitionService $planTransitions;

    public string $planSource = '';

    public string $couponCode = '';

    public string $paymentInfo = '';

    public string $captchaToken = '';

    public function boot(
        PaymentManager $payments,
        PaymentPlanResolver $plans,
        PaymentCheckoutStore $checkoutStore,
        PaymentLifecycleService $lifecycle,
        OptionStore $options,
        UserPlanTransitionService $planTransitions,
    ): void {
        $this->payments = $payments;
        $this->plans = $plans;
        $this->checkoutStore = $checkoutStore;
        $this->lifecycle = $lifecycle;
        $this->options = $options;
        $this->planTransitions = $planTransitions;
    }

    public function mount(string $plan)
    {
        $this->planSource = $plan;

        $resolvedPlan = $this->plans->resolve($plan);

        if (! $resolvedPlan) {
            return redirect()->route('guest.pricing');
        }

        if ($resolvedPlan->free_plan) {
            return redirect()->route('guest.pricing')->with('warning', __('This plan does not require a payment gateway.'));
        }

        $this->couponCode = strtoupper(trim((string) session()->get('payment_coupon_code', '')));

        return null;
    }

    public function applyCoupon(): void
    {
        $plan = $this->currentPlanOrFail();
        $code = strtoupper(trim($this->couponCode));

        if ($code === '') {
            $hadCoupon = filled(session()->get('payment_coupon_code'));
            session()->forget('payment_coupon_code');

            if ($hadCoupon) {
                $this->notifySuccess(__('Coupon removed.'));
            }

            return;
        }

        $coupon = $this->findApplicableCoupon($code, $plan);

        if (! $coupon) {
            $this->notifyError(__('The coupon code is invalid or not available for this plan.'));

            return;
        }

        session()->put('payment_coupon_code', $coupon->code);
        $this->couponCode = (string) $coupon->code;

        $this->notifySuccess(__('Coupon applied successfully.'));
    }

    public function submitManualPayment(): void
    {
        $plan = $this->currentPlanOrFail();

        abort_if((string) $this->options->get('payment_manual_status', '0') !== '1', 404);

        $validated = $this->validate([
            'paymentInfo' => ['required', 'string', 'max:2000'],
        ], [], [
            'paymentInfo' => __('payment info'),
        ]);

        if (
            function_exists('captcha_enabled')
            && captcha_enabled()
            && ! captcha_verify_token(
                token: $this->captchaToken,
                host: (string) request()->getHost(),
                ip: (string) request()->ip(),
            )
        ) {
            $this->addError('captchaToken', captcha_error_message());
            $this->captchaToken = '';
            $this->dispatch('captcha-reset');

            return;
        }

        [, $pricing] = $this->resolvePricing($plan);
        $reference = $this->manualReference($plan);

        ManualPayment::query()->create([
            'id_secure' => Str::random(32),
            'uid' => (int) auth()->id(),
            'plan_id' => (int) $plan->id,
            'payment_id' => $reference,
            'payment_info' => trim((string) $validated['paymentInfo']),
            'amount' => (float) $pricing['total'],
            'currency' => strtoupper((string) $plan->currency ?: 'USD'),
            'notes' => $pricing['discount'] > 0
                ? 'Coupon: '.($pricing['coupon_code'] ?? '').' | Discount: '.$pricing['discount']
                : null,
            'status' => 0,
            'created' => time(),
            'changed' => time(),
        ]);

        session()->forget('payment_manual_reference_'.$plan->id);
        $this->paymentInfo = '';
        $this->captchaToken = '';
        $this->dispatch('captcha-reset');
        $this->notifySuccess(__('Manual payment request submitted. We will review it shortly.'));
    }

    public function checkout(string $gateway)
    {
        $plan = $this->currentPlanOrFail();
        $definition = $this->payments->definition($gateway);
        $activeRecurringSubscription = PaymentSubscription::activeRecurringForUser((int) auth()->id());

        if ($activeRecurringSubscription instanceof PaymentSubscription) {
            if ($definition->type !== 'recurring') {
                $this->notifyError(__('You already have an active recurring subscription. To change plan, choose a recurring payment option for a different plan.'));

                return null;
            }

            if ((int) $activeRecurringSubscription->plan_id === (int) $plan->id) {
                $this->notifyError(__('You already have an active recurring subscription for this plan. Use billing to cancel it or choose a different plan.'));

                return null;
            }
        }

        abort_if($definition->type === 'recurring' && ! in_array((int) $plan->type, [1, 2], true), 422);

        [, $pricing] = $this->resolvePricing($plan);
        $transition = $this->planTransitions->preview(auth()->user()?->loadMissing('plan'), $plan);

        $checkout = new PaymentCheckout(
            gateway: $definition->key,
            gatewayType: $definition->type,
            userId: (int) auth()->id(),
            planId: (int) $plan->id,
            amount: (float) $pricing['total'],
            currency: strtoupper((string) $plan->currency ?: 'USD'),
            returnUrl: route('payment.success', ['gateway' => $definition->key]),
            cancelUrl: route('payment.cancel', ['gateway' => $definition->key]),
            meta: [
                'plan_name' => $plan->name,
                'plan_slug' => $plan->slug,
                'pricing' => $pricing,
                'transition' => $transition,
            ],
        );

        $this->checkoutStore->put($checkout);

        try {
            $result = $this->payments->gateway($definition->key)->start($checkout);
        } catch (\Throwable $e) {
            $this->checkoutStore->forget();
            $this->notifyError($e->getMessage());

            return null;
        }

        return redirect()->away($result->redirectUrl);
    }

    public function render(): View
    {
        $plan = $this->currentPlanOrFail();
        [$activeCoupon, $pricing] = $this->resolvePricing($plan);
        $manualReference = $this->manualReference($plan);
        $activeRecurringSubscription = PaymentSubscription::activeRecurringForUser((int) auth()->id());
        $isRecurringChangeFlow = $activeRecurringSubscription instanceof PaymentSubscription;
        $isSameRecurringPlan = $activeRecurringSubscription
            && (int) $activeRecurringSubscription->plan_id === (int) $plan->id;

        $gateways = collect($this->payments->available())
            ->filter(function ($definition) use ($plan, $isRecurringChangeFlow, $isSameRecurringPlan) {
                if ($definition->type !== 'recurring') {
                    return ! $isRecurringChangeFlow;
                }

                if (! in_array((int) $plan->type, [1, 2], true)) {
                    return false;
                }

                if ($isSameRecurringPlan) {
                    return false;
                }

                return true;
            })
            ->values()
            ->all();

        return view('apppayments::index', [
            'plan' => $plan,
            'gateways' => $gateways,
            'coupon' => $activeCoupon,
            'pricing' => $pricing,
            'manualReference' => $manualReference,
            'manualInfo' => (string) $this->options->get('payment_manual_info', 'Bank Info'),
            'manualEnabled' => ! $isRecurringChangeFlow && (string) $this->options->get('payment_manual_status', '0') === '1',
            'activeRecurringSubscription' => $activeRecurringSubscription,
            'isRecurringChangeFlow' => $isRecurringChangeFlow,
            'isSameRecurringPlan' => $isSameRecurringPlan,
            'transitionPreview' => $this->planTransitions->preview(auth()->user()?->loadMissing('plan'), $plan),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Payment'),
            'shellArea' => 'user',
        ]);
    }

    protected function currentPlanOrFail(): AdminPlan
    {
        $plan = $this->plans->resolve($this->planSource);

        abort_if(! $plan instanceof AdminPlan || ! $plan->status || $plan->free_plan, 404);

        return $plan;
    }

    protected function resolvePricing(AdminPlan $plan): array
    {
        $couponCode = strtoupper(trim((string) session()->get('payment_coupon_code', '')));
        $coupon = $couponCode !== '' ? $this->findApplicableCoupon($couponCode, $plan) : null;

        if (! $coupon && $couponCode !== '') {
            session()->forget('payment_coupon_code');
            $this->couponCode = '';
        }

        $subtotal = (float) $plan->price;
        $discount = 0.0;

        if ($coupon) {
            $discount = (int) $coupon->type === 2
                ? min((float) $coupon->discount, $subtotal)
                : round($subtotal * ((float) $coupon->discount / 100), 2);
        }

        $total = max(0, round($subtotal - $discount, 2));

        return [
            $coupon,
            [
                'subtotal' => round($subtotal, 2),
                'discount' => round($discount, 2),
                'total' => $total,
                'coupon_code' => $coupon?->code,
            ],
        ];
    }

    protected function findApplicableCoupon(string $code, AdminPlan $plan): ?Coupon
    {
        $coupon = Coupon::query()
            ->where('status', true)
            ->whereRaw('UPPER(code) = ?', [$code])
            ->first();

        if (! $coupon) {
            return null;
        }

        $now = time();
        $plans = is_array($coupon->plans) ? $coupon->plans : [];

        if ((int) $coupon->start_date > 0 && (int) $coupon->start_date > $now) {
            return null;
        }

        if ((int) $coupon->end_date > 0 && (int) $coupon->end_date !== -1 && (int) $coupon->end_date < $now) {
            return null;
        }

        if ((int) $coupon->usage_limit !== -1 && (int) $coupon->usage_count >= (int) $coupon->usage_limit) {
            return null;
        }

        if ($plans !== [] && ! in_array((string) $plan->id, array_map('strval', $plans), true)) {
            return null;
        }

        return $coupon;
    }

    protected function manualReference(AdminPlan $plan): string
    {
        $key = 'payment_manual_reference_'.$plan->id;
        $existing = trim((string) session()->get($key, ''));

        if ($existing !== '') {
            return $existing;
        }

        $prefix = (string) $this->options->get('payment_manual_prefix', 'PAY-');
        $reference = $prefix.Str::upper(Str::random(8));
        session()->put($key, $reference);

        return $reference;
    }

    protected function notifySuccess(string $message): void
    {
        $this->dispatch('app-toast', type: 'success', message: $message);
    }

    protected function notifyError(string $message): void
    {
        $this->dispatch('app-toast', type: 'error', message: $message);
    }
}
