<?php

use Illuminate\Support\Facades\Route;
use Modules\AppAiPublishing\Livewire\AiPublishingForm;
use Modules\AppAiPublishing\Livewire\AiPublishingIndex;

Route::middleware(['web', 'auth', 'verified'])
    ->prefix(config('modules.appaipublishing.route_prefix', 'portal/ai-publishing'))
    ->group(function (): void {
        Route::livewire('/', AiPublishingIndex::class)->name('portal.ai-publishing');
        Route::livewire('/create', AiPublishingForm::class)->name('portal.ai-publishing.create');
        Route::livewire('/{run}/edit', AiPublishingForm::class)->name('portal.ai-publishing.edit');
    });
