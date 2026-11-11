<?php

use Illuminate\Support\Facades\Route;
use Modules\AppPayments\Support\PaymentCheckoutStore;

Route::middleware(['web', 'auth', 'verified'])->prefix('payment/iyzico')->group(function (): void {
    Route::get('checkout', function (PaymentCheckoutStore $store) {
        $checkout = $store->current('iyzico');

        if (! $checkout) {
            return redirect()->route('guest.pricing')->with('error', __('Payment session not found or already expired.'));
        }

        $session = session('iyzico_checkout', []);

        return view('paymentiyzico::checkout', [
            'checkout' => $checkout,
            'checkoutFormContent' => (string) data_get($session, 'checkout_form_content', ''),
            'paymentPageUrl' => (string) data_get($session, 'payment_page_url', ''),
            'token' => (string) data_get($session, 'token', ''),
            'planName' => (string) data_get($session, 'plan_name', $checkout->meta['plan_name'] ?? __('Subscription')),
        ]);
    })->name('payment.iyzico.checkout');
});
