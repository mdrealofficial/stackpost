<?php

use Illuminate\Support\Facades\Route;
use Modules\AppSupport\Livewire\PortalSupportIndex;
use Modules\AppSupport\Livewire\PortalSupportShow;

Route::middleware(['web', 'auth', 'verified'])
    ->prefix('portal/support')
    ->name('portal.support.')
    ->group(function (): void {
        Route::get('/', PortalSupportIndex::class)->name('index');
        Route::get('/{ticket}', PortalSupportShow::class)->name('show');
    });
