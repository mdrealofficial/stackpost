<?php

use Illuminate\Support\Facades\Route;
use Modules\AppChannelInstagramProfiles\Http\Controllers\InstagramProfileConnectController;
use Modules\AppChannelInstagramProfiles\Livewire\InstagramProfilePicker;

Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
    Route::get('portal/channels/instagram/profile/connect', [InstagramProfileConnectController::class, 'redirect'])
        ->name('portal.channels.instagram.profiles.connect');

    Route::get('integrations/instagram/profile', [InstagramProfileConnectController::class, 'callback'])
        ->name('portal.channels.instagram.profiles.callback');

    Route::livewire('portal/channels/instagram/profile/select', InstagramProfilePicker::class)
        ->name('portal.channels.instagram.profiles.select');
});
