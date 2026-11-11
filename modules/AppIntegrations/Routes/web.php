<?php

use Illuminate\Support\Facades\Route;
use Modules\AppIntegrations\Livewire\IntegrationHub;

Route::prefix('admin')
    ->middleware(['web', 'auth'])
    ->group(function (): void {
        Route::livewire('integrations', IntegrationHub::class)->name('admin.integrations');
    });
