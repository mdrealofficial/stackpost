<?php

use Illuminate\Support\Facades\Route;
use Modules\AdminPaymentSubscriptions\Livewire\PaymentSubscriptionIndex;

Route::middleware(['web', 'auth', 'verified'])
    ->prefix('admin/payment-subscriptions')
    ->name('admin-payment-subscriptions.')
    ->group(function (): void {
        Route::get('/', PaymentSubscriptionIndex::class)->name('index');
    });
