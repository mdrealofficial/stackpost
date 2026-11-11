<?php

namespace Modules\PaymentStripe\Services;

use Exception;
use Illuminate\Http\Request;
use Modules\AdminPlans\Models\AdminPlan;
use Modules\AdminUser\Models\User;
use Modules\AppPayments\Contracts\PaymentGateway;
use Modules\AppPayments\Support\PaymentCallbackResult;
use Modules\AppPayments\Support\PaymentCheckout;
use Modules\AppPayments\Support\PaymentStartResult;

class StripePaymentGateway extends AbstractStripeGateway implements PaymentGateway
{
    public function start(PaymentCheckout $checkout): PaymentStartResult
    {
        $plan = AdminPlan::query()->find($checkout->planId);
        $user = User::query()->find($checkout->userId);
        $currency = $this->normalizeCurrency($checkout->currency);

        $payload = [
                'mode' => 'payment',
                'client_reference_id' => (string) $checkout->userId,
                'success_url' => $checkout->returnUrl.'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $checkout->cancelUrl,
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => strtolower($currency),
                        'unit_amount' => $this->unitAmount($checkout->amount, $currency),
                        'product_data' => array_filter([
                            'name' => $this->shortText((string) ($plan?->name ?: data_get($checkout->meta, 'plan_name', 'Plan purchase')), 127),
                            'description' => $this->shortText((string) ($plan?->desc ?: data_get($checkout->meta, 'plan_name', 'Stripe payment')), 255),
                        ]),
                    ],
                ]],
                'metadata' => [
                    'gateway' => 'stripe',
                    'user_id' => (string) $checkout->userId,
                    'plan_id' => (string) $checkout->planId,
                ],
            ];

        if (filled($user?->email)) {
            $payload['customer_email'] = $user->email;
        }

        try {
            $session = $this->stripe->checkout->sessions->create($payload);
        } catch (\Throwable $e) {
            throw new Exception($this->stripeError($e, __('Unable to create Stripe Checkout session.')));
        }

        $redirectUrl = trim((string) ($session->url ?? ''));

        if ($redirectUrl === '') {
            throw new Exception(__('Stripe checkout URL was not returned.'));
        }

        return PaymentStartResult::redirect($redirectUrl, [
            'stripe_session_id' => (string) $session->id,
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
                'expand' => ['payment_intent.latest_charge'],
            ]);
        } catch (\Throwable $e) {
            throw new Exception($this->stripeError($e, __('Unable to verify Stripe checkout session.')));
        }

        $paymentStatus = strtolower((string) ($session->payment_status ?? ''));

        if ($paymentStatus !== 'paid') {
            return PaymentCallbackResult::pending(
                __('Stripe returned :status for this payment.', ['status' => strtoupper($paymentStatus ?: 'UNKNOWN')]),
                ['stripe_session_id' => $sessionId]
            );
        }

        $paymentIntent = $session->payment_intent;

        if (is_string($paymentIntent) && $paymentIntent !== '') {
            try {
                $paymentIntent = $this->stripe->paymentIntents->retrieve($paymentIntent, [
                    'expand' => ['latest_charge'],
                ]);
            } catch (\Throwable $e) {
                throw new Exception($this->stripeError($e, __('Unable to retrieve Stripe payment intent.')));
            }
        }

        $latestCharge = is_object($paymentIntent) ? ($paymentIntent->latest_charge ?? null) : null;
        $transactionId = is_object($latestCharge)
            ? (string) ($latestCharge->id ?? '')
            : (is_string($latestCharge) ? $latestCharge : '');

        if ($transactionId === '' && is_object($paymentIntent)) {
            $transactionId = (string) ($paymentIntent->id ?? $session->id);
        }

        $currency = strtoupper((string) ($session->currency ?: $checkout->currency));
        $amount = $this->majorAmount($session->amount_total ?? null, $currency);

        return PaymentCallbackResult::success(
            transactionId: $transactionId !== '' ? $transactionId : (string) $session->id,
            amount: $amount,
            currency: $currency,
            meta: [
                'stripe_session_id' => (string) $session->id,
                'stripe_payment_intent_id' => is_object($paymentIntent) ? (string) ($paymentIntent->id ?? '') : (string) $paymentIntent,
                'stripe_payload' => $session->toArray(),
            ],
            subscriptionId: (string) $session->id,
            customerId: (string) ($session->customer ?: (is_object($paymentIntent) ? ($paymentIntent->customer ?? '') : '')),
            message: __('Stripe payment captured successfully.')
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
}
