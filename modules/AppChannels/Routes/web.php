<?php

use Illuminate\Support\Facades\Route;
use Modules\AppChannels\Livewire\PortalDashboard;

Route::middleware(['web', 'auth', 'verified'])
    ->group(function (): void {
        Route::livewire('portal/channels', PortalDashboard::class)->name('portal.channels');
    });
