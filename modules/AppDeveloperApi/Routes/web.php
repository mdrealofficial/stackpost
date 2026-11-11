<?php

use Illuminate\Support\Facades\Route;
use Modules\AppDeveloperApi\Livewire\DeveloperApiIndex;

Route::middleware(['web', 'auth', 'verified'])
    ->prefix(config('modules.appdeveloperapi.route_prefix', 'portal/developer'))
    ->name('portal.developer-api.')
    ->group(function (): void {
        Route::livewire('/', DeveloperApiIndex::class)->name('index');
    });
