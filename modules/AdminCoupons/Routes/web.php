<?php

use Illuminate\Support\Facades\Route;
use Modules\AdminCoupons\Livewire\CouponIndex;

Route::middleware(['web', 'auth', 'verified'])
    ->prefix('admin/coupons')
    ->name('admin-coupons.')
    ->group(function (): void {
        Route::get('/', CouponIndex::class)->name('index');
    });
