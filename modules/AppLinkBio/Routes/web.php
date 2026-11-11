<?php

use Illuminate\Support\Facades\Route;
use Modules\AppLinkBio\Http\Controllers\PublicLinkBioController;
use Modules\AppLinkBio\Livewire\LinkBioForm;
use Modules\AppLinkBio\Livewire\LinkBioIndex;
use Modules\AppLinkBio\Livewire\LinkBioSettings;
use Modules\AppLinkBio\Livewire\LinkBioTemplates;

Route::middleware(['web', 'auth', 'verified'])
    ->prefix(config('modules.applinkbio.portal_route_prefix', 'portal/link-bio'))
    ->name('portal.link-bio.')
    ->group(function (): void {
        Route::get('/', LinkBioIndex::class)->name('index');
        Route::get('/create', LinkBioForm::class)->name('create');
        Route::get('/templates', LinkBioTemplates::class)->name('templates');
        Route::get('/{page}/edit', LinkBioForm::class)->name('edit');
    });

Route::middleware(['web', 'auth'])
    ->prefix('admin/settings')
    ->group(function (): void {
        Route::get('/link-bio', LinkBioSettings::class)->name('settings.link-bio');
    });

Route::middleware(['web'])
    ->prefix(config('modules.applinkbio.public_route_prefix', 'b'))
    ->name('link-bio.public.')
    ->group(function (): void {
        Route::get('/{slug}/click/{block}/{item}', [PublicLinkBioController::class, 'click'])
            ->whereNumber(['block', 'item'])
            ->name('click');
        Route::get('/{slug}', PublicLinkBioController::class)->name('show');
    });
