<?php

use Illuminate\Support\Facades\Route;
use Modules\AppChannelInstagramUnofficial\Http\Controllers\InstagramUnofficialConnectController;

Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('portal/channels/instagram-unofficial/connect', [InstagramUnofficialConnectController::class, 'show'])
        ->name('portal.channels.instagram-unofficial.connect');
    Route::post('portal/channels/instagram-unofficial/process', [InstagramUnofficialConnectController::class, 'process'])
        ->name('portal.channels.instagram-unofficial.process');
});
