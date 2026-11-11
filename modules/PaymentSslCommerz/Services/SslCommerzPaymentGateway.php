<?php

namespace Modules\PaymentSslCommerz\Services;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\AppPayments\Contracts\PaymentGateway;
use Modules\AppPayments\Support\PaymentCallbackResult;
use Modules\AppPayments\Support\PaymentCheckout;
use Modules\AppPayments\Support\PaymentStartResult;

class SslCommerzPaymentGateway extends AbstractSslCommerzGateway implements PaymentGateway
{
    public function start(PaymentCheckout $checkout): PaymentStartResult
    {
        if ($this->storeId === '' || $this->storePassword === '') {
            throw new Exception(__('Missing SSLCOMMERZ configuration.'));
        }

        $tranId = (string) data_get($checkout->meta, 'reference', 'ssl_'.Str::upper(Str::random(12)));
        $address = $this->customerAddress();

        $payload = [
            'store_id' => $this->storeId,
            'store_passwd' => $this->storePassword,
            'total_amount' => number_format((float) $checkout->amount, 2, '.', ''),
            'currency' => strtoupper($checkout->currency ?: 'BDT'),
            'tran_id' => $tranId,
            'success_url' => $checkout->returnUrl,
            'fail_url' => $checkout->cancelUrl,
            'cancel_url' => $checkout->cancelUrl,
            'shipping_method' => 'NO',
            'product_name' => $this->shortDescription($checkout),
            'product_category' => 'General',
            'product_profile' => 'general',
            'cus_name' => $this->customerName(),
            'cus_email' => $this->customerEmail(),
            'cus_add1' => $address['address'],
            'cus_city' => $address['city'],
            'cus_state' => $address['state'],
            'cus_postcode' => $address['postcode'],
            'cus_country' => $address['country'],
            'cus_phone' => $this->customerPhone(),
        ];

        $result = $this->api('POST', '/gwprocess/v4/api.php', $payload);
        $redirectUrl = (string) data_get($result, 'GatewayPageURL', '');

        if (($result['status'] ?? '') !== 'SUCCESS' || $redirectUrl === '') {
            throw new Exception(__('SSLCOMMERZ init failed: :message', ['message' => json_encode($result)]));
        }

        session(['sslcommerz_tran_id' => $tranId]);

        return PaymentStartResult::redirect($redirectUrl, ['sslcommerz_tran_id' => $tranId]);
    }

    public function complete(Request $request, PaymentCheckout $checkout): PaymentCallbackResult
    {
        $valId = (string) $request->query('val_id', $request->input('val_id', ''));

        if ($valId === '') {
            throw new Exception(__('Missing val_id from SSLCOMMERZ callback.'));
        }

        $query = [
            'val_id' => $valId,
            'store_id' => $this->storeId,
            'store_passwd' => $this->storePassword,
            'format' => 'json',
        ];

        $result = $this->api('GET', '/validator/api/validationserverAPI.php', [], $query);

        if (! in_array((string) ($result['status'] ?? ''), ['VALID', 'VALIDATED'], true)) {
            return PaymentCallbackResult::failed(__('SSLCOMMERZ payment was not validated.'));
        }

        return PaymentCallbackResult::success(
            transactionId: (string) ($result['tran_id'] ?? $valId),
            amount: isset($result['amount']) ? (float) $result['amount'] : $checkout->amount,
            currency: strtoupper((string) ($result['currency'] ?? $checkout->currency ?: 'BDT')),
            meta: ['sslcommerz_payload' => $result],
            message: __('SSLCOMMERZ payment captured successfully.')
        );
    }

    public function webhook(Request $request): mixed
    {
        $valId = (string) $request->input('val_id', '');

        if ($valId === '') {
            return ['status' => 'ignored', 'reason' => 'missing val_id'];
        }

        return ['status' => 'ok', 'val_id' => $valId];
    }
}
