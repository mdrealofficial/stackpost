<?php

use Illuminate\Support\Facades\Route;
use Modules\AppChannelLinkedinPages\Http\Controllers\LinkedinPageConnectController;
use Modules\AppChannelLinkedinPages\Livewire\LinkedinPagePicker;

Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
    Route::get('portal/channels/linkedin/page/connect', [LinkedinPageConnectController::class, 'redirect'])
        ->name('portal.channels.linkedin.pages.connect');

    Route::get('integrations/linkedin/page', [LinkedinPageConnectController::class, 'callback'])
        ->name('portal.channels.linkedin.pages.callback');

    Route::livewire('portal/channels/linkedin/page/select', LinkedinPagePicker::class)
        ->name('portal.channels.linkedin.pages.select');
});
