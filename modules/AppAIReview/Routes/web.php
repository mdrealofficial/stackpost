<?php

use Illuminate\Support\Facades\Route;
use Modules\AppAIReview\Livewire\AIReviewIndex;

Route::middleware(['web', 'auth', 'verified'])
    ->prefix(config('modules.appaireview.route_prefix', 'portal/ai-studio/review'))
    ->group(function (): void {
        Route::livewire('/', AIReviewIndex::class)->name('portal.ai-review');
    });
