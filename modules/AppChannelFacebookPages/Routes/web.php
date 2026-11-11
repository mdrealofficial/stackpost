<?php

use Illuminate\Support\Facades\Route;
use Modules\AppChannelFacebookPages\Http\Controllers\FacebookPageConnectController;
use Modules\AppChannelFacebookPages\Livewire\FacebookPagePicker;

Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
    Route::get('portal/channels/facebook/page/connect', [FacebookPageConnectController::class, 'redirect'])
        ->name('portal.channels.facebook.pages.connect');

    Route::get('integrations/facebook/page', [FacebookPageConnectController::class, 'callback'])
        ->name('portal.channels.facebook.pages.callback');

    Route::livewire('portal/channels/facebook/page/select', FacebookPagePicker::class)
        ->name('portal.channels.facebook.pages.select');
});
