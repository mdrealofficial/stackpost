<?php

use Illuminate\Support\Facades\Route;
use Modules\AppChannelLinkedinProfiles\Http\Controllers\LinkedinProfileConnectController;

Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
    Route::get('portal/channels/linkedin/profile/connect', [LinkedinProfileConnectController::class, 'redirect'])
        ->name('portal.channels.linkedin.profiles.connect');

    Route::get('integrations/linkedin/profile', [LinkedinProfileConnectController::class, 'callback'])
        ->name('portal.channels.linkedin.profiles.callback');
});
