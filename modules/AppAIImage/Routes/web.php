<?php

use Illuminate\Support\Facades\Route;
use Modules\AppAIImage\Livewire\AIImageIndex;

Route::middleware(['web', 'auth', 'verified'])
    ->prefix(config('modules.appaiimage.route_prefix', 'portal/ai-studio/image'))
    ->group(function (): void {
        Route::livewire('/', AIImageIndex::class)->name('portal.ai-image');
    });
