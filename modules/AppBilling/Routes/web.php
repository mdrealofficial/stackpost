<?php

use Illuminate\Support\Facades\Route;
use Modules\AppBilling\Http\Controllers\AppBillingController;

Route::middleware(['web', 'auth', 'verified'])
    ->prefix('portal')
    ->name('portal.')
    ->group(function (): void {
        Route::get('billing', [AppBillingController::class, 'billing'])->name('billing');
        Route::post('billing/subscriptions/{subscription}/cancel', [AppBillingController::class, 'cancelSubscription'])->name('billing.subscriptions.cancel');
        Route::get('invoices', [AppBillingController::class, 'invoices'])->name('invoices');
        Route::get('invoices/{invoice}/download', [AppBillingController::class, 'downloadInvoice'])->name('invoices.download');
    });
