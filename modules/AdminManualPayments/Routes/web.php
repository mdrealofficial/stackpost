<?php

use Illuminate\Support\Facades\Route;
use Modules\AdminManualPayments\Livewire\ManualPaymentForm;
use Modules\AdminManualPayments\Livewire\ManualPaymentIndex;

Route::middleware(['web', 'auth', 'verified'])
    ->prefix('admin/manual-payments')
    ->name('admin-manual-payments.')
    ->group(function (): void {
        Route::get('/', ManualPaymentIndex::class)->name('index');
        Route::get('/create', ManualPaymentForm::class)->name('create');
        Route::get('/{manualPayment}/edit', ManualPaymentForm::class)->name('edit');
    });
