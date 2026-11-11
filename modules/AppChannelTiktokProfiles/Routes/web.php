<?php

use Illuminate\Support\Facades\Route;
use Modules\AppChannelTiktokProfiles\Http\Controllers\TiktokProfileConnectController;

Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
    Route::get('portal/channels/tiktok/profile/connect', [TiktokProfileConnectController::class, 'redirect'])
        ->name('portal.channels.tiktok.profiles.connect');
    Route::post('portal/channels/tiktok/profile/creator-info', [TiktokProfileConnectController::class, 'creatorInfo'])
        ->name('portal.channels.tiktok.profiles.creator-info');

    Route::get('integrations/tiktok/profile', [TiktokProfileConnectController::class, 'callback'])
        ->name('portal.channels.tiktok.profiles.callback');
});
