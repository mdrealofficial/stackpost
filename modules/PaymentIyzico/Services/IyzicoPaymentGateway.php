<?php

namespace Modules\PaymentIyzico\Services;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\AdminPlans\Models\AdminPlan;
use Modules\AdminUser\Models\User;
use Modules\AppPayments\Contracts\PaymentGateway;
use Modules\AppPayments\Support\PaymentCallbackResult;
use Modules\AppPayments\Support\PaymentCheckout;
use Modules\AppPayments\Support\PaymentStartResult;

class IyzicoPaymentGateway implements PaymentGateway
{
    protected string $apiKey;

    protected string $secretKey;

    protected bool $live;

    protected string $baseUrl;

    protected string $locale;

    public function __construct()
    {
        $this->apiKey = (string) get_option('iyzico_api_key', '');
        $this->secretKey = (string) get_option('iyzico_secret_key', '');
        $this->live = (bool) get_option('iyzico_environment', 0);
        $this->locale = (string) get_option('iyzico_locale', 'en');
        $this->baseUrl = $this->live
            ? 'https://api.iyzipay.com'
            : 'https://sandbox-api.iyzipay.com';
    }

    public function start(PaymentCheckout $checkout): PaymentStartResult
    {
        $plan = AdminPlan::query()->find($checkout->planId);
        $user = User::query()->find($checkout->userId);

        if (! in_array(strtoupper($this->normalizeCurrency($checkout->currency)), ['TRY', 'USD', 'EUR', 'GBP', 'RUB'], true)) {
            throw new Exception(__('iyzico currently supports TRY, USD, EUR, GBP, and RUB in this module.'));
        }

        $conversationId = $this->conversationId($checkout);
        $body = [
            'locale' => $this->locale === 'tr' ? 'tr' : 'en',
            'conversationId' => $conversationId,
            'price' => $this->price($checkout->amount),
            'paidPrice' => $this->price($checkout->amount),
            'currency' => $this->normalizeCurrency($checkout->currency),
            'basketId' => (string) ($plan?->slug ?: $plan?->id ?: $checkout->planId),
            'paymentGroup' => 'PRODUCT',
            'callbackUrl' => route('payment.success', ['gateway' => 'iyzico']),
            'enabledInstallments' => ['2', '3', '6', '9', '12'],
            'buyer' => $this->buyer($user, $checkout),
            'shippingAddress' => $this->address($user, $checkout, 'shipping'),
            'billingAddress' => $this->address($user, $checkout, 'billing'),
            'basketItems' => [[
                'id' => 'plan-'.$checkout->planId,
                'name' => trim((string) ($plan?->name ?: data_get($checkout->meta, 'plan_name', __('Subscription')))),
                'category1' => 'Subscription',
                'itemType' => 'VIRTUAL',
                'price' => $this->price($checkout->amount),
            ]],
        ];

        $response = $this->post('/payment/iyzipos/checkoutform/initialize/auth/ecom', $body);

        $token = (string) data_get($response, 'token', '');
        $paymentPageUrl = (string) data_get($response, 'paymentPageUrl', '');
        $checkoutFormContent = (string) data_get($response, 'checkoutFormContent', '');

        if ($token === '' || ($paymentPageUrl === '' && $checkoutFormContent === '')) {
            throw new Exception((string) data_get($response, 'errorMessage', __('Failed to create iyzico checkout session.')));
        }

        session([
            'iyzico_checkout' => [
                'token' => $token,
                'conversation_id' => $conversationId,
                'payment_page_url' => $paymentPageUrl,
                'checkout_form_content' => $checkoutFormContent,
                'plan_name' => $plan?->name ?? data_get($checkout->meta, 'plan_name', __('Subscription')),
            ],
        ]);

        return PaymentStartResult::redirect(route('payment.iyzico.checkout'));
    }

    public function complete(Request $request, PaymentCheckout $checkout): PaymentCallbackResult
    {
        $token = (string) ($request->input('token') ?: $request->query('token') ?: session('iyzico_checkout.token', ''));
        $conversationId = (string) session('iyzico_checkout.conversation_id', $this->conversationId($checkout));

        if ($token === '') {
            throw new Exception(__('Missing iyzico token.'));
        }

        $response = $this->post('/payment/iyzipos/checkoutform/auth/ecom/detail', [
            'locale' => $this->locale === 'tr' ? 'tr' : 'en',
            'conversationId' => $conversationId,
            'token' => $token,
        ]);

        $status = strtoupper((string) data_get($response, 'paymentStatus', data_get($response, 'status', 'FAILED')));

        if ($status !== 'SUCCESS') {
            return PaymentCallbackResult::failed(
                __('iyzico payment did not complete successfully.'),
                ['iyzico_payload' => $response]
            );
        }

        $transactionId = (string) (data_get($response, 'paymentId') ?: data_get($response, 'conversationId') ?: $token);
        $amount = (float) data_get($response, 'paidPrice', $checkout->amount);
        $currency = strtoupper((string) data_get($response, 'currency', $this->normalizeCurrency($checkout->currency)));
        $customerId = (string) (data_get($response, 'buyer.email') ?: data_get($response, 'paymentCard.cardHolderName') ?: '');

        session()->forget('iyzico_checkout');

        return PaymentCallbackResult::success(
            transactionId: $transactionId,
            amount: $amount,
            currency: $currency,
            meta: [
                'iyzico_payload' => $response,
                'iyzico_token' => $token,
            ],
            subscriptionId: $token,
            customerId: $customerId,
            message: __('iyzico payment captured successfully.')
        );
    }

    public function webhook(Request $request): mixed
    {
        return [
            'status' => 'received',
            'payload' => $request->all(),
        ];
    }

    protected function post(string $uriPath, array $body): array
    {
        $bodyJson = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
        $randomKey = (string) Str::uuid();
        $signature = hash_hmac('sha256', $randomKey.$uriPath.$bodyJson, $this->secretKey);
        $authorization = 'IYZWSv2 '.base64_encode("apiKey:{$this->apiKey}&randomKey:{$randomKey}&signature:{$signature}");

        $response = Http::timeout(60)
            ->acceptJson()
            ->withHeaders([
                'Authorization' => $authorization,
                'x-iyzi-rnd' => $randomKey,
                'Content-Type' => 'application/json',
            ])
            ->post($this->baseUrl.$uriPath, $body);

        if (! $response->successful()) {
            throw new Exception(sprintf('iyzico API error: %s', $response->body()));
        }

        return $response->json() ?: [];
    }

    protected function buyer(?User $user, PaymentCheckout $checkout): array
    {
        $name = trim((string) ($user?->name ?: $user?->username ?: __('Customer')));
        $parts = preg_split('/\s+/', $name, 2) ?: [$name];
        $firstName = trim((string) ($parts[0] ?? __('Customer')));
        $lastName = trim((string) ($parts[1] ?? $firstName));

        return [
            'id' => 'user-'.$checkout->userId,
            'name' => $firstName,
            'surname' => $lastName,
            'gsmNumber' => (string) ($user?->phone ?: '+905555555555'),
            'email' => (string) ($user?->email ?: 'customer@example.com'),
            'identityNumber' => str_pad((string) max(1, $checkout->userId), 11, '1', STR_PAD_LEFT),
            'registrationDate' => now()->toIso8601String(),
            'lastLoginDate' => now()->toIso8601String(),
            'registrationAddress' => 'Stackposts user account',
            'city' => 'Istanbul',
            'country' => 'Turkey',
            'zipCode' => '34000',
            'ip' => request()->ip() ?: '127.0.0.1',
        ];
    }

    protected function address(?User $user, PaymentCheckout $checkout, string $type): array
    {
        $label = $type === 'shipping' ? __('Shipping') : __('Billing');
        $address = trim((string) ($user?->address ?: $user?->location ?: __('Stackposts customer')));

        return [
            'contactName' => trim((string) ($user?->name ?: $user?->username ?: __('Customer'))),
            'city' => 'Istanbul',
            'country' => 'Turkey',
            'address' => $address ?: $label.' '.__('address'),
            'zipCode' => '34000',
        ];
    }

    protected function price(float $amount): string
    {
        return number_format(max(0, $amount), 2, '.', '');
    }

    protected function normalizeCurrency(string $currency): string
    {
        $currency = strtoupper(trim($currency));

        return $currency === 'TL' ? 'TRY' : $currency;
    }

    protected function conversationId(PaymentCheckout $checkout): string
    {
        return 'IYZ'.strtoupper(substr(hash('sha256', implode('|', [
            $checkout->userId,
            $checkout->planId,
            $checkout->amount,
            microtime(true),
            random_int(1000, 999999),
        ])), 0, 18));
    }
}
