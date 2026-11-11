<?php

use Illuminate\Support\Facades\Route;
use Modules\AppProfile\Http\Controllers\AppProfileController;

Route::middleware(['web', 'auth', 'verified'])
    ->prefix(config('modules.appprofile.route_prefix', 'portal/profile'))
    ->group(function (): void {
        Route::get('/', [AppProfileController::class, 'index'])->name('portal.profile');
        Route::put('/', [AppProfileController::class, 'update'])->name('portal.profile.update');
        Route::put('/password', [AppProfileController::class, 'updatePassword'])->name('portal.profile.password.update');
    });
