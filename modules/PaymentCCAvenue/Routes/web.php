<?php

use Illuminate\Support\Facades\Route;
use Modules\AppPayments\Support\PaymentCheckoutStore;

Route::middleware(['web', 'auth', 'verified'])->prefix('payment/ccavenue')->group(function (): void {
    Route::get('checkout', function (PaymentCheckoutStore $store) {
        $checkout = $store->current('ccavenue');

        if (! $checkout) {
            return redirect()->route('guest.pricing')->with('error', __('Payment session not found or already expired.'));
        }

        return view('paymentccavenue::checkout', [
            'checkout' => $checkout,
            'payload' => session('ccavenue_checkout', []),
            'checkoutUrl' => (string) data_get(session('ccavenue_checkout', []), 'checkout_url', ''),
        ]);
    })->name('payment.ccavenue.checkout');
});
