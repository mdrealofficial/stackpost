<?php

use Illuminate\Support\Facades\Route;
use Modules\AdminAffiliate\Livewire\AdminAffiliateIndex;
use Modules\AdminAffiliate\Livewire\AdminAffiliateCommissions;
use Modules\AdminAffiliate\Livewire\AdminAffiliateWithdrawals;

Route::middleware(['web', 'auth', 'verified'])
    ->prefix('admin/affiliate')
    ->group(function (): void {
        Route::get('/', AdminAffiliateIndex::class)->name('admin-affiliate.index');
        Route::get('/commissions', AdminAffiliateCommissions::class)->name('admin-affiliate-commissions.index');
        Route::get('/withdrawals', AdminAffiliateWithdrawals::class)->name('admin-affiliate-withdrawals.index');
    });
