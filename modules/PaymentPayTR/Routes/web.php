<?php

use Illuminate\Support\Facades\Route;
use Modules\AppPayments\Support\PaymentCheckoutStore;

Route::middleware(['web', 'auth', 'verified'])->prefix('payment/paytr')->group(function (): void {
    Route::get('checkout', function (PaymentCheckoutStore $store) {
        $checkout = $store->current('paytr');

        if (! $checkout) {
            return redirect()->route('guest.pricing')->with('error', __('Payment session not found or already expired.'));
        }

        $session = session('paytr_checkout', []);

        return view('paymentpaytr::checkout', [
            'checkout' => $checkout,
            'checkoutUrl' => (string) data_get($session, 'payment_url', ''),
            'token' => (string) data_get($session, 'token', ''),
            'planName' => (string) data_get($session, 'plan_name', $checkout->meta['plan_name'] ?? __('Subscription')),
        ]);
    })->name('payment.paytr.checkout');
});
