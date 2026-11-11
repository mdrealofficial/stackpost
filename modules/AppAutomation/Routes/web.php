<?php

use Illuminate\Support\Facades\Route;
use Modules\AppAutomation\Http\Controllers\GoogleDriveConnectionController;

Route::middleware(['web', 'auth', 'verified'])
    ->prefix(config('modules.appautomation.route_prefix', 'portal/automation'))
    ->name('portal.automation.')
    ->group(function (): void {
        Route::get('/connections/google-drive/connect', [GoogleDriveConnectionController::class, 'connect'])->name('connections.google-drive.connect');
        Route::get('/connections/google-drive/callback', [GoogleDriveConnectionController::class, 'callback'])->name('connections.google-drive.callback');
        Route::post('/connections/google-drive/{connection}/verify', [GoogleDriveConnectionController::class, 'verify'])->whereNumber('connection')->name('connections.google-drive.verify');
        Route::delete('/connections/google-drive/{connection}', [GoogleDriveConnectionController::class, 'disconnect'])->whereNumber('connection')->name('connections.google-drive.disconnect');
        Route::get('/developer', fn () => redirect()->to(url('portal/developer')))->name('developer');
    });
