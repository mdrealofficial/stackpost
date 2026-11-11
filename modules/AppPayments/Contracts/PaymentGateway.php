<?php

namespace Modules\AppPayments\Contracts;

use Illuminate\Http\Request;
use Modules\AppPayments\Support\PaymentCallbackResult;
use Modules\AppPayments\Support\PaymentCheckout;
use Modules\AppPayments\Support\PaymentStartResult;

interface PaymentGateway
{
    public function start(PaymentCheckout $checkout): PaymentStartResult;

    public function complete(Request $request, PaymentCheckout $checkout): PaymentCallbackResult;

    public function webhook(Request $request): mixed;
}
