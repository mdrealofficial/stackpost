<?php

use Illuminate\Support\Facades\Route;
use Modules\AppAIVideo\Livewire\AIVideoIndex;

Route::middleware(['web', 'auth', 'verified'])
    ->prefix(config('modules.appaivideo.route_prefix', 'portal/ai-studio/video'))
    ->group(function (): void {
        Route::livewire('/', AIVideoIndex::class)->name('portal.ai-video');
    });
