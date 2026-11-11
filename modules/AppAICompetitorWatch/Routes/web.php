<?php

use Illuminate\Support\Facades\Route;
use Modules\AppAICompetitorWatch\Livewire\CompetitorWatchIndex;

Route::middleware(['web', 'auth', 'verified'])
    ->prefix(config('modules.appaicompetitorwatch.route_prefix', 'portal/competitor-watch'))
    ->group(function (): void {
        Route::livewire('/', CompetitorWatchIndex::class)->name('portal.ai-competitor-watch');
    });
