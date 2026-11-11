<?php

use Illuminate\Support\Facades\Route;
use Modules\AppAIRepurpose\Livewire\RepurposeIndex;

Route::middleware(['web', 'auth', 'verified'])
    ->prefix(config('modules.appairepurpose.route_prefix', 'portal/ai-studio/repurpose'))
    ->group(function (): void {
        Route::livewire('/', RepurposeIndex::class)->name('portal.ai-repurpose');
    });
