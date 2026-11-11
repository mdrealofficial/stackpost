<?php

namespace Modules\PaymentPaypal\Services;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Modules\AppPayments\Contracts\PaymentGateway;
use Modules\AppPayments\Support\PaymentCallbackResult;
use Modules\AppPayments\Support\PaymentCheckout;
use Modules\AppPayments\Support\PaymentStartResult;

class PaypalPaymentGateway extends AbstractPaypalGateway implements PaymentGateway
{
    public function start(PaymentCheckout $checkout): PaymentStartResult
    {
        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'amount' => [
                    'currency_code' => $this->paypalCurrencyCode($checkout->currency),
                    'value' => number_format((float) $checkout->amount, 2, '.', ''),
                ],
            ]],
            'application_context' => [
                'return_url' => $checkout->returnUrl,
                'cancel_url' => $checkout->cancelUrl,
            ],
        ];

        $response = Http::asJson()
            ->withToken($this->accessToken())
            ->post($this->apiBase.'/v2/checkout/orders', $payload);

        if (! $response->successful()) {
            $this->logPaypalFailure('orders.create', $payload, $response->json(), $response->status());
            throw new Exception($this->paypalError($response->json(), __('Unable to create PayPal order.')));
        }

        $approvalLink = collect($response->json('links', []))
            ->firstWhere('rel', 'approve')['href'] ?? null;

        if (! $approvalLink) {
            throw new Exception(__('PayPal approval link was not returned.'));
        }

        return PaymentStartResult::redirect($approvalLink, [
            'paypal_order_id' => $response->json('id'),
        ]);
    }

    public function complete(Request $request, PaymentCheckout $checkout): PaymentCallbackResult
    {
        $orderId = (string) $request->query('token', '');
        $payerId = (string) $request->query('PayerID', '');

        if ($orderId === '' || $payerId === '') {
            throw new Exception(__('Missing PayPal callback parameters.'));
        }

        $response = Http::asJson()
            ->withToken($this->accessToken())
            ->post($this->apiBase."/v2/checkout/orders/{$orderId}/capture", (object) []);

        if (! $response->successful()) {
            $this->logPaypalFailure('orders.capture', [], $response->json(), $response->status());
            throw new Exception($this->paypalError($response->json(), __('Unable to capture PayPal order.')));
        }

        $payload = $response->json();
        $status = strtoupper((string) ($payload['status'] ?? ''));

        if ($status !== 'COMPLETED') {
            return PaymentCallbackResult::pending(
                __('PayPal returned :status for this payment.', ['status' => $status ?: 'UNKNOWN']),
                ['paypal_order_id' => $orderId, 'paypal_payload' => $payload]
            );
        }

        $capture = data_get($payload, 'purchase_units.0.payments.captures.0', []);
        $captureId = (string) ($capture['id'] ?? $orderId);
        $amount = (float) ($capture['amount']['value'] ?? $checkout->amount);
        $currency = (string) ($capture['amount']['currency_code'] ?? $this->paypalCurrencyCode($checkout->currency));

        return PaymentCallbackResult::success(
            transactionId: $captureId,
            amount: $amount,
            currency: strtoupper($currency),
            meta: [
                'paypal_order_id' => $orderId,
                'paypal_payer_id' => $payerId,
                'paypal_payload' => $payload,
            ],
            subscriptionId: $orderId,
            customerId: (string) data_get($payload, 'payer.payer_id', 'paypal-customer'),
            message: __('PayPal payment captured successfully.')
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
}
