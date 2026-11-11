<?php

use Illuminate\Support\Facades\Route;
use Modules\AppAISemanticSearch\Livewire\SemanticSearchIndex;

Route::middleware(['web', 'auth', 'verified'])
    ->prefix(config('modules.appaisemanticsearch.route_prefix', 'portal/ai-studio/search'))
    ->group(function (): void {
        Route::livewire('/', SemanticSearchIndex::class)->name('portal.ai-semantic-search');
    });
