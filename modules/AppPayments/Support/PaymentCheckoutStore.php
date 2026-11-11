<?php

namespace Modules\AppPayments\Support;

use Illuminate\Contracts\Session\Session;

class PaymentCheckoutStore
{
    protected string $sessionKey = 'app_payments.current_checkout';

    public function __construct(
        protected Session $session,
    ) {}

    public function put(PaymentCheckout $checkout): void
    {
        $this->session->put($this->sessionKey, $checkout->toArray());
    }

    public function current(?string $gateway = null): ?PaymentCheckout
    {
        $payload = $this->session->get($this->sessionKey);

        if (! is_array($payload)) {
            return null;
        }

        $checkout = PaymentCheckout::fromArray($payload);

        if ($gateway !== null && $checkout->gateway !== strtolower($gateway)) {
            return null;
        }

        return $checkout;
    }

    public function forget(): void
    {
        $this->session->forget($this->sessionKey);
    }
}
