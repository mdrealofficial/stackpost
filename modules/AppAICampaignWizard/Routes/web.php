<?php

use Illuminate\Support\Facades\Route;
use Modules\AppAICampaignWizard\Livewire\CampaignWizardIndex;

Route::middleware(['web', 'auth', 'verified'])
    ->prefix(config('modules.appaicampaignwizard.route_prefix', 'portal/ai-studio/campaign-wizard'))
    ->group(function (): void {
        Route::livewire('/', CampaignWizardIndex::class)->name('portal.ai-campaign-wizard');
    });
