<?php

use Illuminate\Support\Facades\Route;
use Modules\AppPayments\Support\PaymentCheckoutStore;

Route::middleware(['web', 'auth', 'verified'])->prefix('payment/payu')->group(function (): void {
    Route::get('checkout', function (PaymentCheckoutStore $store) {
        $checkout = $store->current('payu');

        if (! $checkout) {
            return redirect()->route('guest.pricing')->with('error', __('Payment session not found or already expired.'));
        }

        return view('paymentpayu::checkout', [
            'checkout' => $checkout,
            'params' => session('payu_checkout', []),
            'url' => (string) session('payu_checkout_url', 'https://secure.payu.in/_payment'),
        ]);
    })->name('payment.payu.checkout');
});
