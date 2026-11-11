<?php

use Illuminate\Support\Facades\Route;
use Modules\AdminPaymentHistory\Livewire\PaymentHistoryIndex;

Route::middleware(['web', 'auth', 'verified'])
    ->prefix('admin/payment-history')
    ->name('admin-payment-history.')
    ->group(function (): void {
        Route::get('/', PaymentHistoryIndex::class)->name('index');
    });
