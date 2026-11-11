<?php

use Illuminate\Support\Facades\Route;
use Modules\AdminPaymentReport\Livewire\PaymentReportIndex;

Route::middleware(['web', 'auth', 'verified'])
    ->prefix('admin/payment-report')
    ->name('admin-payment-report.')
    ->group(function (): void {
        Route::get('/', PaymentReportIndex::class)->name('index');
    });
