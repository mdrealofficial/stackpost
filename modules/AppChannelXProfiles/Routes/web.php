<?php

use Illuminate\Support\Facades\Route;
use Modules\AppChannelXProfiles\Http\Controllers\XProfileConnectController;

Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('portal/channels/x/profile/connect', [XProfileConnectController::class, 'redirect'])
        ->name('portal.channels.x.profiles.connect');

    Route::get('integrations/x/profile', [XProfileConnectController::class, 'callback'])
        ->name('portal.channels.x.profiles.callback');
});
