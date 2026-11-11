<?php

use Illuminate\Support\Facades\Route;
use Modules\AppCaptions\Livewire\CaptionWorkspace;

Route::middleware(['web', 'auth', 'verified'])
    ->prefix(config('modules.appcaptions.route_prefix', 'portal/captions'))
    ->group(function (): void {
        Route::get('/', CaptionWorkspace::class)->name('portal.captions');
    });
