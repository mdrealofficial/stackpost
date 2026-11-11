<?php

use Illuminate\Support\Facades\Route;
use Modules\AppPayments\Support\PaymentCheckoutStore;

Route::middleware(['web', 'auth', 'verified'])->prefix('payment/paytm')->group(function (): void {
    Route::get('checkout', function (PaymentCheckoutStore $store) {
        $checkout = $store->current('paytm');

        if (! $checkout) {
            return redirect()->route('guest.pricing')->with('error', __('Payment session not found or already expired.'));
        }

        return view('paymentpaytm::checkout', [
            'checkout' => $checkout,
            'payload' => session('paytm_checkout', []),
            'checkoutUrl' => (string) data_get(session('paytm_checkout', []), 'checkout_url', ''),
        ]);
    })->name('payment.paytm.checkout');
});
