<?php

use Illuminate\Support\Facades\Route;
use Modules\AppGroups\Livewire\GroupWorkspace;

Route::middleware(['web', 'auth', 'verified'])
    ->prefix(config('modules.appgroups.route_prefix', 'portal/groups'))
    ->group(function (): void {
        Route::get('/', GroupWorkspace::class)->name('portal.groups');
    });
