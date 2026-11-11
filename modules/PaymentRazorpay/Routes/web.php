<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\AdminSettings\Support\OptionStore;

Route::middleware(['web', 'auth', 'verified'])
    ->prefix('payment/razorpay')
    ->group(function (): void {
        Route::get('checkout/{gateway}', function (Request $request, string $gateway) {
            abort_unless(in_array($gateway, ['razorpay', 'razorpay_recurring'], true), 404);

            $options = app(OptionStore::class);
            $keyId = trim((string) $options->get('razorpay_key_id', ''));
            abort_if($keyId === '', 404);

            $checkoutOptions = array_filter([
                'key' => $keyId,
                'name' => (string) $request->query('name', config('app.name', 'Stackposts')),
                'description' => (string) $request->query('description', __('Secure checkout')),
                'callback_url' => (string) $request->query('callback_url', ''),
                'redirect' => true,
                'order_id' => $request->query('order_id'),
                'subscription_id' => $request->query('subscription_id'),
                'amount' => $request->query('amount'),
                'currency' => $request->query('currency'),
                'prefill' => array_filter([
                    'name' => (string) $request->query('prefill_name', ''),
                    'email' => (string) $request->query('prefill_email', ''),
                ]),
                'theme' => [
                    'color' => '#0765ff',
                ],
            ], fn ($value) => $value !== null && $value !== '' && $value !== []);

            return view('paymentrazorpay::checkout', [
                'checkoutOptions' => $checkoutOptions,
                'cancelUrl' => (string) $request->query('cancel_url', route('payment.cancel', ['gateway' => $gateway])),
            ]);
        })->name('payment.razorpay.checkout');
    });
