<?php

use Illuminate\Support\Facades\Route;
use Modules\AppWatermark\Livewire\WatermarkWorkspace;

Route::middleware(['web', 'auth', 'verified'])
    ->prefix(config('modules.appwatermark.route_prefix', 'portal/watermark'))
    ->group(function (): void {
        Route::get('/', WatermarkWorkspace::class)->name('portal.watermarks');
    });
