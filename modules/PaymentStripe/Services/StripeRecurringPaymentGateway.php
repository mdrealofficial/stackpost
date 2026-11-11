<?php

namespace Modules\PaymentStripe\Services;

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

class StripeRecurringPaymentGateway extends AbstractStripeGateway implements PaymentGateway
{
    public function start(PaymentCheckout $checkout): PaymentStartResult
    {
        $plan = AdminPlan::query()->find($checkout->planId);
        $user = User::query()->find($checkout->userId);

        if (! $plan instanceof AdminPlan) {
            throw new Exception(__('The selected plan could not be found.'));
        }

        if (! in_array((int) $plan->type, [1, 2], true)) {
            throw new Exception(__('Stripe recurring payment is only available for monthly or yearly plans.'));
        }

        $priceId = $this->getOrCreatePriceId($plan, $checkout);
        $transitionMode = (string) data_get($checkout->meta, 'transition.mode', '');
        $effectiveAt = (string) data_get($checkout->meta, 'transition.effective_at', '');

        $payload = [
            'mode' => 'subscription',
            'client_reference_id' => (string) $checkout->userId,
            'success_url' => $checkout->returnUrl.'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $checkout->cancelUrl,
            'line_items' => [[
                'price' => $priceId,
                'quantity' => 1,
            ]],
            'metadata' => [
                'gateway' => 'stripe_recurring',
                'user_id' => (string) $checkout->userId,
                'plan_id' => (string) $checkout->planId,
                'transition_mode' => $transitionMode,
            ],
            'subscription_data' => [
                'metadata' => [
                    'gateway' => 'stripe_recurring',
                    'user_id' => (string) $checkout->userId,
                    'plan_id' => (string) $checkout->planId,
                    'plan_type' => (string) $plan->type,
                    'transition_mode' => $transitionMode,
                ],
            ],
        ];

        if (filled($user?->email)) {
            $payload['customer_email'] = $user->email;
        }

        if ($transitionMode === 'downgrade_scheduled' && $effectiveAt !== '') {
            $trialEnd = Carbon::parse($effectiveAt)->utc()->timestamp;
            $minimumTrialEnd = now()->utc()->addMinutes(5)->timestamp;

            if ($trialEnd < $minimumTrialEnd) {
                $trialEnd = $minimumTrialEnd;
            }

            $payload['subscription_data']['trial_end'] = $trialEnd;
        }

        try {
            $session = $this->stripe->checkout->sessions->create($payload);
        } catch (\Throwable $e) {
            throw new Exception($this->stripeError($e, __('Unable to create Stripe recurring checkout session.')));
        }

        $redirectUrl = trim((string) ($session->url ?? ''));

        if ($redirectUrl === '') {
            throw new Exception(__('Stripe recurring checkout URL was not returned.'));
        }

        return PaymentStartResult::redirect($redirectUrl, [
            'stripe_session_id' => (string) $session->id,
            'stripe_price_id' => $priceId,
        ]);
    }

    public function complete(Request $request, PaymentCheckout $checkout): PaymentCallbackResult
    {
        $sessionId = trim((string) ($request->query('session_id') ?: data_get($checkout->meta, 'stripe_session_id', '')));

        if ($sessionId === '') {
            throw new Exception(__('Missing Stripe session ID.'));
        }

        try {
            $session = $this->stripe->checkout->sessions->retrieve($sessionId, [
                'expand' => ['subscription.latest_invoice.payment_intent'],
            ]);
        } catch (\Throwable $e) {
            throw new Exception($this->stripeError($e, __('Unable to verify Stripe recurring checkout session.')));
        }

        $subscription = $session->subscription;

        if (is_string($subscription) && $subscription !== '') {
            try {
                $subscription = $this->stripe->subscriptions->retrieve($subscription, [
                    'expand' => ['latest_invoice.payment_intent'],
                ]);
            } catch (\Throwable $e) {
                throw new Exception($this->stripeError($e, __('Unable to retrieve Stripe subscription.')));
            }
        }

        if (! is_object($subscription)) {
            throw new Exception(__('Stripe subscription was not returned.'));
        }

        $status = strtolower((string) ($subscription->status ?? ''));

        if (! in_array($status, ['active', 'trialing'], true)) {
            return PaymentCallbackResult::failed(
                __('Stripe returned :status for this subscription.', ['status' => strtoupper($status ?: 'UNKNOWN')]),
                ['stripe_session_id' => $sessionId]
            );
        }

        $item = $subscription->items->data[0] ?? null;
        $price = $item?->price;
        $currency = strtoupper((string) ($price?->currency ?: $checkout->currency));
        $amount = $price?->unit_amount !== null
            ? $this->majorAmount($price->unit_amount, $currency)
            : (float) $checkout->amount;

        $invoice = $subscription->latest_invoice ?? null;
        $paymentIntent = is_object($invoice) ? ($invoice->payment_intent ?? null) : null;
        $transactionId = is_object($paymentIntent)
            ? (string) ($paymentIntent->id ?? $subscription->id)
            : (string) $subscription->id;

        return PaymentCallbackResult::success(
            transactionId: $transactionId,
            amount: $amount,
            currency: $currency,
            meta: [
                'stripe_session_id' => (string) $session->id,
                'stripe_subscription_id' => (string) $subscription->id,
                'stripe_payload' => $subscription->toArray(),
            ],
            subscriptionId: (string) $subscription->id,
            customerId: (string) ($subscription->customer ?? ''),
            message: __('Stripe recurring subscription approved successfully.')
        );
    }

    public function webhook(Request $request): mixed
    {
        $event = $this->stripeEvent($request);

        if (is_array($event)) {
            return [
                'event_type' => (string) ($event['type'] ?? ''),
                'resource_id' => (string) data_get($event, 'data.object.id', ''),
                'status' => (string) data_get($event, 'data.object.status', ''),
            ];
        }

        return [
            'event_type' => (string) ($event->type ?? ''),
            'resource_id' => (string) data_get($event->data->object, 'id', ''),
            'status' => (string) data_get($event->data->object, 'status', ''),
        ];
    }

    public function cancelSubscription(string $subscriptionId, bool $atPeriodEnd = false): bool
    {
        $subscriptionId = trim($subscriptionId);

        if ($subscriptionId === '') {
            return false;
        }

        try {
            if ($atPeriodEnd) {
                $this->stripe->subscriptions->update($subscriptionId, [
                    'cancel_at_period_end' => true,
                ]);

                return true;
            }

            $this->stripe->subscriptions->cancel($subscriptionId, []);

            return true;
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            return str_contains(strtolower((string) $e->getMessage()), 'no such subscription');
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function getOrCreatePriceId(AdminPlan $plan, PaymentCheckout $checkout): string
    {
        $options = app(OptionStore::class);
        $cacheKey = $this->priceCacheKey($plan, $checkout);
        $priceMap = json_decode((string) $options->get('stripe_recurring_price_map', '{}'), true);
        $priceMap = is_array($priceMap) ? $priceMap : [];

        $existingPriceId = (string) ($priceMap[$cacheKey] ?? '');

        if ($existingPriceId !== '') {
            return $existingPriceId;
        }

        $productId = $this->getOrCreateProductId($plan);
        $currency = $this->normalizeCurrency($checkout->currency);

        try {
            $price = $this->stripe->prices->create([
                'currency' => strtolower($currency),
                'unit_amount' => $this->unitAmount($checkout->amount, $currency),
                'product' => $productId,
                'nickname' => $this->shortText($plan->name.' '.((int) $plan->type === 2 ? 'Yearly' : 'Monthly'), 40),
                'recurring' => [
                    'interval' => (int) $plan->type === 2 ? 'year' : 'month',
                    'interval_count' => 1,
                ],
                'metadata' => [
                    'plan_id' => (string) $plan->id,
                    'plan_type' => (string) $plan->type,
                ],
            ]);
        } catch (\Throwable $e) {
            throw new Exception($this->stripeError($e, __('Unable to create Stripe recurring price.')));
        }

        $priceId = trim((string) ($price->id ?? ''));

        if ($priceId === '') {
            throw new Exception(__('Stripe recurring price ID was not returned.'));
        }

        $priceMap[$cacheKey] = $priceId;
        $options->set('stripe_recurring_price_map', json_encode($priceMap));

        return $priceId;
    }

    protected function getOrCreateProductId(AdminPlan $plan): string
    {
        $options = app(OptionStore::class);
        $productMap = json_decode((string) $options->get('stripe_product_map', '{}'), true);
        $productMap = is_array($productMap) ? $productMap : [];
        $cacheKey = (string) $plan->id;
        $existingProductId = (string) ($productMap[$cacheKey] ?? '');

        if ($existingProductId !== '') {
            return $existingProductId;
        }

        try {
            $product = $this->stripe->products->create([
                'name' => $this->shortText($plan->name, 127),
                'description' => $this->shortText((string) ($plan->desc ?: $plan->name), 255),
                'metadata' => [
                    'plan_id' => (string) $plan->id,
                ],
            ]);
        } catch (\Throwable $e) {
            throw new Exception($this->stripeError($e, __('Unable to create Stripe product.')));
        }

        $productId = trim((string) ($product->id ?? ''));

        if ($productId === '') {
            throw new Exception(__('Stripe product ID was not returned.'));
        }

        $productMap[$cacheKey] = $productId;
        $options->set('stripe_product_map', json_encode($productMap));

        return $productId;
    }

    protected function priceCacheKey(AdminPlan $plan, PaymentCheckout $checkout): string
    {
        return implode(':', [
            (int) $plan->id,
            (int) $plan->type,
            $this->normalizeCurrency($checkout->currency),
            number_format((float) $checkout->amount, 2, '.', ''),
        ]);
    }
}
