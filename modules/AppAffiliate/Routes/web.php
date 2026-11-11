<?php

use Illuminate\Support\Facades\Route;
use Modules\AppAffiliate\Livewire\AffiliateSettings;
use Modules\AppAffiliate\Http\Controllers\PortalAffiliateController;

Route::middleware(['web', 'auth', 'verified'])
    ->prefix('portal/affiliate')
    ->name('portal.affiliate.')
    ->group(function (): void {
        Route::get('/', [PortalAffiliateController::class, 'index'])->name('index');
        Route::post('/withdraw', [PortalAffiliateController::class, 'withdraw'])->name('withdraw');
    });

Route::middleware(['web', 'auth'])
    ->prefix('admin/settings')
    ->group(function (): void {
        Route::livewire('affiliate', AffiliateSettings::class)->name('settings.affiliate');
    });
