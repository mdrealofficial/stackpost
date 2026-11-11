<?php

namespace Modules\AppPayments\Support;

use Modules\AdminPaymentHistory\Models\PaymentHistory;

class PaymentLifecycleService
{
    public function __construct(
        protected PaymentCheckoutTypeRegistry $types,
    ) {}

    public function finalize(PaymentCheckout $checkout, PaymentCallbackResult $result): PaymentHistory
    {
        return $this->types->resolve($checkout)->finalize($checkout, $result);
    }
}
