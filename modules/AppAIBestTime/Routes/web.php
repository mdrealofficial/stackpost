<?php

use Illuminate\Support\Facades\Route;
use Modules\AppAIBestTime\Livewire\BestTimeIndex;

Route::middleware(['web', 'auth', 'verified'])
    ->prefix(config('modules.appaibesttime.route_prefix', 'portal/ai-studio/timing'))
    ->group(function (): void {
        Route::livewire('/', BestTimeIndex::class)->name('portal.ai-best-time');
    });
