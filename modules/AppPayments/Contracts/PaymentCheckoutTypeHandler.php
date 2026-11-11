<?php

namespace Modules\AppPayments\Contracts;

use Modules\AdminPaymentHistory\Models\PaymentHistory;
use Modules\AppPayments\Support\PaymentCallbackResult;
use Modules\AppPayments\Support\PaymentCheckout;

interface PaymentCheckoutTypeHandler
{
    public function finalize(PaymentCheckout $checkout, PaymentCallbackResult $result): PaymentHistory;

    public function retryUrl(PaymentCheckout $checkout): string;
}
