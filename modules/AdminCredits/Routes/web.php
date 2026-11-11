<?php

use Illuminate\Support\Facades\Route;
use Modules\AdminCredits\Livewire\LedgerIndex;
use Modules\AdminCredits\Livewire\PackManager;
use Modules\AdminCredits\Livewire\UsageIndex;

Route::middleware(['web', 'auth', 'verified'])
    ->prefix('admin/credits')
    ->name('admin-credits.')
    ->group(function (): void {
        Route::livewire('/packs', PackManager::class)->name('packs');
        Route::livewire('/ledger', LedgerIndex::class)->name('ledger');
        Route::livewire('/usage', UsageIndex::class)->name('usage');
    });
