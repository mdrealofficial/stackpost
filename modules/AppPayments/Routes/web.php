<?php

use Illuminate\Support\Facades\Route;
use Modules\AppPayments\Http\Controllers\PortalPackagesController;
use Modules\AppPayments\Http\Controllers\PaymentController;
use Modules\AppPayments\Livewire\Admin\PaymentGateways;
use Modules\AppPayments\Livewire\CheckoutPage;

Route::middleware(['web', 'auth'])->group(function (): void {
    Route::prefix('admin/settings')->group(function (): void {
        Route::livewire('payment-gateways', PaymentGateways::class)->name('settings.payment-gateways');
    });
});

Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
    Route::prefix('portal')->group(function (): void {
        Route::get('packages', PortalPackagesController::class)->name('portal.packages');
    });

    Route::prefix('payment')->group(function (): void {
        Route::livewire('/{plan}', CheckoutPage::class)->name('payment.index');
        Route::match(['get', 'post'], '/success/{gateway}', [PaymentController::class, 'success'])->name('payment.success');
        Route::get('/cancel/{gateway}', [PaymentController::class, 'cancel'])->name('payment.cancel');
    });
});

Route::middleware(['web'])->post('/payment/webhook/{gateway}', [PaymentController::class, 'webhook'])->name('payment.webhook');
