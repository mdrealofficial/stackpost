<?php

use Illuminate\Support\Facades\Route;
use Modules\AppPayments\Support\PaymentCheckoutStore;

Route::middleware(['web', 'auth', 'verified'])->prefix('payment/2checkout')->group(function (): void {
    Route::get('checkout', function (PaymentCheckoutStore $store) {
        $checkout = $store->current('2checkout');

        if (! $checkout) {
            return redirect()->route('guest.pricing')->with('error', __('Payment session not found or already expired.'));
        }

        return view('payment2checkout::checkout', [
            'checkout' => $checkout,
            'payload' => session('twocheckout_checkout', []),
            'checkoutUrl' => (string) data_get(session('twocheckout_checkout', []), 'checkout_url', 'https://secure.2checkout.com/order/checkout.php'),
        ]);
    })->name('payment.2checkout.checkout');
});
