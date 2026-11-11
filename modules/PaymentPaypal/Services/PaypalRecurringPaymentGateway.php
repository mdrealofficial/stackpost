<?php

namespace Modules\PaymentPaypal\Services;

use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Modules\AdminPlans\Models\AdminPlan;
use Modules\AdminSettings\Support\OptionStore;
use Modules\AppPayments\Contracts\PaymentGateway;
use Modules\AppPayments\Support\PaymentCallbackResult;
use Modules\AppPayments\Support\PaymentCheckout;
use Modules\AppPayments\Support\PaymentStartResult;

class PaypalRecurringPaymentGateway extends AbstractPaypalGateway implements PaymentGateway
{
    public function start(PaymentCheckout $checkout): PaymentStartResult
    {
        $plan = AdminPlan::query()->find($checkout->planId);

        if (! $plan instanceof AdminPlan) {
            throw new Exception(__('The selected plan could not be found.'));
        }

        if (! in_array((int) $plan->type, [1, 2], true)) {
            throw new Exception(__('PayPal recurring payment is only available for monthly or yearly plans.'));
        }

        $paypalPlanId = $this->getOrCreateBillingPlan($plan, $checkout);

        $payload = [
            'plan_id' => $paypalPlanId,
            'application_context' => [
                'return_url' => $checkout->returnUrl,
                'cancel_url' => $checkout->cancelUrl,
            ],
        ];

        $transitionMode = (string) data_get($checkout->meta, 'transition.mode', '');
        $effectiveAt = (string) data_get($checkout->meta, 'transition.effective_at', '');

        if ($transitionMode === 'downgrade_scheduled' && $effectiveAt !== '') {
            $startTime = Carbon::parse($effectiveAt)->utc()->addMinutes(5);
            $minimumStart = now()->utc()->addMinutes(5);

            if ($startTime->lt($minimumStart)) {
                $startTime = $minimumStart;
            }

            $payload['start_time'] = $startTime->toIso8601String();
        }

        $response = Http::asJson()
            ->withToken($this->accessToken())
            ->post($this->apiBase.'/v1/billing/subscriptions', $payload);

        if (! $response->successful()) {
            $this->logPaypalFailure('subscriptions.create', $payload, $response->json(), $response->status());
            throw new Exception($this->paypalError($response->json(), __('Unable to create PayPal subscription.')));
        }

        $payload = $response->json();
        $approvalLink = collect($payload['links'] ?? [])
            ->firstWhere('rel', 'approve')['href'] ?? null;

        if (! $approvalLink) {
            throw new Exception(__('PayPal subscription approval link was not returned.'));
        }

        return PaymentStartResult::redirect($approvalLink, [
            'paypal_subscription_id' => (string) ($payload['id'] ?? ''),
            'paypal_plan_id' => $paypalPlanId,
        ]);
    }

    public function complete(Request $request, PaymentCheckout $checkout): PaymentCallbackResult
    {
        $subscriptionId = (string) (
            $request->query('subscription_id')
            ?: $request->query('token')
            ?: $request->query('ba_token')
            ?: data_get($checkout->meta, 'paypal_subscription_id', '')
        );

        if ($subscriptionId === '') {
            throw new Exception(__('Missing PayPal subscription callback parameters.'));
        }

        $response = Http::withToken($this->accessToken())
            ->get($this->apiBase."/v1/billing/subscriptions/{$subscriptionId}");

        if (! $response->successful()) {
            $this->logPaypalFailure('subscriptions.show', [], $response->json(), $response->status());
            throw new Exception($this->paypalError($response->json(), __('Unable to verify PayPal subscription.')));
        }

        $payload = $response->json();
        $status = strtoupper((string) ($payload['status'] ?? ''));
        $successStatuses = ['ACTIVE', 'APPROVED', 'APPROVAL_PENDING'];

        if (! in_array($status, $successStatuses, true)) {
            return PaymentCallbackResult::failed(
                __('PayPal returned :status for this subscription.', ['status' => $status ?: 'UNKNOWN']),
                ['paypal_subscription_id' => $subscriptionId, 'paypal_payload' => $payload]
            );
        }

        $transactionId = (string) (
            data_get($payload, 'billing_info.last_payment.transaction_id')
            ?: data_get($payload, 'id')
            ?: $subscriptionId
        );

        $amount = (float) (
            data_get($payload, 'billing_info.last_payment.amount.value')
            ?: $checkout->amount
        );

        $currency = (string) (
            data_get($payload, 'billing_info.last_payment.amount.currency_code')
            ?: $this->paypalCurrencyCode($checkout->currency)
        );

        return PaymentCallbackResult::success(
            transactionId: $transactionId,
            amount: $amount,
            currency: strtoupper($currency),
            meta: [
                'paypal_subscription_id' => $subscriptionId,
                'paypal_payload' => $payload,
            ],
            subscriptionId: $subscriptionId,
            customerId: (string) data_get($payload, 'subscriber.payer_id', 'paypal-subscriber'),
            message: __('PayPal recurring subscription approved successfully.')
        );
    }

    public function webhook(Request $request): mixed
    {
        $payload = $request->json()->all();

        return [
            'event_type' => (string) ($payload['event_type'] ?? ''),
            'resource_id' => (string) data_get($payload, 'resource.id', ''),
            'status' => (string) data_get($payload, 'resource.status', ''),
        ];
    }

    public function cancelSubscription(string $subscriptionId, string $reason = ''): bool
    {
        $subscriptionId = trim($subscriptionId);

        if ($subscriptionId === '') {
            return false;
        }

        $payload = [
            'reason' => $this->shortText($reason !== '' ? $reason : 'Subscription replaced by a new billing agreement.', 127),
        ];

        $response = Http::asJson()
            ->withToken($this->accessToken())
            ->post($this->apiBase."/v1/billing/subscriptions/{$subscriptionId}/cancel", $payload);

        if (in_array($response->status(), [200, 202, 204], true)) {
            return true;
        }

        $body = $response->json();
        $issue = strtoupper((string) data_get($body, 'details.0.issue', ''));
        $name = strtoupper((string) data_get($body, 'name', ''));

        if (in_array($issue, ['RESOURCE_NOT_FOUND', 'SUBSCRIPTION_STATUS_INVALID'], true)
            || in_array($name, ['RESOURCE_NOT_FOUND', 'INVALID_REQUEST'], true)) {
            return true;
        }

        $this->logPaypalFailure('subscriptions.cancel', $payload, $body, $response->status());

        return false;
    }

    protected function getOrCreateBillingPlan(AdminPlan $plan, PaymentCheckout $checkout): string
    {
        $options = app(OptionStore::class);
        $cacheKey = $this->billingPlanCacheKey($plan, $checkout);
        $planMap = json_decode((string) $options->get('paypal_recurring_plan_map', '{}'), true);
        $planMap = is_array($planMap) ? $planMap : [];

        $existingPlanId = (string) ($planMap[$cacheKey] ?? '');

        if ($existingPlanId !== '') {
            return $existingPlanId;
        }

        $productId = $this->getOrCreateProductId();

        $payload = [
            'product_id' => $productId,
            'name' => $this->shortText($plan->name.' '.($plan->type == 2 ? 'Yearly' : 'Monthly'), 127),
            'description' => $this->shortText((string) ($plan->desc ?: $plan->name), 127),
            'billing_cycles' => [[
                'frequency' => [
                    'interval_unit' => (int) $plan->type === 2 ? 'YEAR' : 'MONTH',
                    'interval_count' => 1,
                ],
                'tenure_type' => 'REGULAR',
                'sequence' => 1,
                'total_cycles' => 0,
                'pricing_scheme' => [
                    'fixed_price' => [
                        'value' => number_format((float) $checkout->amount, 2, '.', ''),
                        'currency_code' => $this->paypalCurrencyCode($checkout->currency),
                    ],
                ],
            ]],
            'payment_preferences' => [
                'auto_bill_outstanding' => true,
                'setup_fee' => [
                    'value' => '0',
                    'currency_code' => $this->paypalCurrencyCode($checkout->currency),
                ],
                'setup_fee_failure_action' => 'CONTINUE',
                'payment_failure_threshold' => 3,
            ],
        ];

        $response = Http::asJson()
            ->withToken($this->accessToken())
            ->post($this->apiBase.'/v1/billing/plans', $payload);

        if (! $response->successful()) {
            $this->logPaypalFailure('plans.create', $payload, $response->json(), $response->status());
            throw new Exception($this->paypalError($response->json(), __('Unable to create PayPal billing plan.')));
        }

        $paypalPlanId = (string) $response->json('id', '');

        if ($paypalPlanId === '') {
            throw new Exception(__('PayPal billing plan ID was not returned.'));
        }

        $planMap[$cacheKey] = $paypalPlanId;
        $options->set('paypal_recurring_plan_map', json_encode($planMap));

        return $paypalPlanId;
    }

    protected function getOrCreateProductId(): string
    {
        $options = app(OptionStore::class);
        $existingProductId = trim((string) $options->get('paypal_recurring_product_id', ''));

        if ($existingProductId !== '') {
            return $existingProductId;
        }

        $payload = [
            'name' => $this->shortText(config('app.name', 'Application').' Subscriptions', 127),
            'description' => $this->shortText(__('Recurring billing product for application plans.'), 127),
            'type' => 'SERVICE',
            'category' => 'SOFTWARE',
        ];

        $response = Http::asJson()
            ->withToken($this->accessToken())
            ->post($this->apiBase.'/v1/catalogs/products', $payload);

        if (! $response->successful()) {
            $this->logPaypalFailure('products.create', $payload, $response->json(), $response->status());
            throw new Exception($this->paypalError($response->json(), __('Unable to create PayPal product.')));
        }

        $productId = (string) $response->json('id', '');

        if ($productId === '') {
            throw new Exception(__('PayPal product ID was not returned.'));
        }

        $options->set('paypal_recurring_product_id', $productId);

        return $productId;
    }

    protected function billingPlanCacheKey(AdminPlan $plan, PaymentCheckout $checkout): string
    {
        return implode(':', [
            (int) $plan->id,
            (int) $plan->type,
            strtoupper($checkout->currency),
            number_format((float) $checkout->amount, 2, '.', ''),
        ]);
    }
}
