<?php

use Illuminate\Support\Facades\Route;
use Modules\AppAIContent\Livewire\AIContentIndex;

Route::middleware(['web', 'auth', 'verified'])
    ->prefix(config('modules.appaicontent.route_prefix', 'portal/ai-studio/ai-content'))
    ->group(function (): void {
        Route::livewire('/', AIContentIndex::class)->name('portal.ai-content');
    });
