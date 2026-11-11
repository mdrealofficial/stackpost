<?php

namespace Modules\PaymentInstamojo\Services;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Modules\AppPayments\Contracts\PaymentGateway;
use Modules\AppPayments\Support\PaymentCallbackResult;
use Modules\AppPayments\Support\PaymentCheckout;
use Modules\AppPayments\Support\PaymentStartResult;

class InstamojoPaymentGateway extends AbstractInstamojoGateway implements PaymentGateway
{
    public function start(PaymentCheckout $checkout): PaymentStartResult
    {
        if ($this->clientId === '' || $this->clientSecret === '') {
            throw new Exception(__('Missing Instamojo credentials.'));
        }

        $payload = [
            'purpose' => (string) data_get($checkout->meta, 'plan_name', __('Payment')),
            'amount' => number_format((float) $checkout->amount, 2, '.', ''),
            'currency' => strtoupper($checkout->currency ?: 'INR'),
            'buyer_name' => auth()->user()?->name ?: '',
            'email' => auth()->user()?->email ?: '',
            'phone' => (string) data_get(auth()->user(), 'phone', ''),
            'redirect_url' => $checkout->returnUrl,
            'webhook' => route('payment.webhook', ['gateway' => 'instamojo']),
            'allow_repeated_payments' => false,
        ];

        $response = $this->request('POST', 'payment_requests/', $payload, [
            'Authorization' => 'Bearer '.$this->accessToken(),
        ]);

        $longUrl = (string) data_get($response, 'longurl', '');

        if ($longUrl === '') {
            throw new Exception(__('Instamojo approval link not found.'));
        }

        return PaymentStartResult::redirect($longUrl);
    }

    public function complete(Request $request, PaymentCheckout $checkout): PaymentCallbackResult
    {
        $paymentId = (string) $request->query('payment_id', '');
        $paymentRequestId = (string) $request->query('payment_request_id', '');

        if ($paymentId === '' || $paymentRequestId === '') {
            throw new Exception(__('Missing Instamojo callback parameters.'));
        }

        $response = Http::withoutVerifying()
            ->timeout(30)
            ->withHeaders(['Authorization' => 'Bearer '.$this->accessToken()])
            ->get(rtrim($this->apiBase, '/')."/payments/{$paymentId}/");

        if (! $response->successful()) {
            return PaymentCallbackResult::failed(__('Instamojo verification failed.'));
        }

        $details = (array) $response->json();

        if ((string) ($details['status'] ?? '') !== 'Credit') {
            return PaymentCallbackResult::failed(__('Instamojo payment was not completed.'));
        }

        return PaymentCallbackResult::success(
            transactionId: $paymentId,
            amount: isset($details['amount']) ? (float) $details['amount'] : $checkout->amount,
            currency: strtoupper((string) ($details['currency'] ?? $checkout->currency ?: 'INR')),
            meta: ['instamojo_payload' => $details],
            message: __('Instamojo payment captured successfully.')
        );
    }

    public function webhook(Request $request): mixed
    {
        return [
            'status' => 'ok',
            'payload' => $request->all(),
        ];
    }
}
