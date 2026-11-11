<?php

use Illuminate\Support\Facades\Route;
use Modules\AppAIContentPlanner\Livewire\ContentPlannerIndex;

Route::middleware(['web', 'auth', 'verified'])
    ->prefix(config('modules.appaicontentplanner.route_prefix', 'portal/ai-studio/planner'))
    ->group(function (): void {
        Route::livewire('/', ContentPlannerIndex::class)->name('portal.ai-content-planner');
    });
