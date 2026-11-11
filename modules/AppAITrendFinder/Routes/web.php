<?php

use Illuminate\Support\Facades\Route;
use Modules\AppAITrendFinder\Livewire\TrendFinderIndex;

Route::middleware(['web', 'auth', 'verified'])
    ->prefix(config('modules.appaitrendfinder.route_prefix', 'portal/ai-studio/trend-finder'))
    ->group(function (): void {
        Route::livewire('/', TrendFinderIndex::class)->name('portal.ai-trend-finder');
    });
