<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function (): void {
    Route::redirect('settings/payment-manual-config', 'admin/settings/payment-gateways');
});

Route::prefix('admin/settings')->middleware(['web', 'auth'])->group(function (): void {
    Route::redirect('payment-manual-config', 'payment-gateways')->name('admin-payment-manual-config.index');
});
