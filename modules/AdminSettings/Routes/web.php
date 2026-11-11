<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Modules\AdminSettings\Livewire\Analytics;
use Modules\AdminSettings\Livewire\EmbedCode;
use Modules\AdminSettings\Livewire\General;
use Modules\AdminSettings\Livewire\Security;
use Modules\AdminSettings\Livewire\StaticPages;

Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('settings', fn () => redirect()->to(settings_default_url()));
    Route::livewire('settings/general', General::class);
    Route::livewire('settings/analytics', Analytics::class);
    Route::livewire('settings/embed-code', EmbedCode::class);
    Route::livewire('settings/static-pages', StaticPages::class);
});

Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
    Route::livewire('settings/security', Security::class)
        ->middleware(
            when(
                Features::canManageTwoFactorAuthentication()
                && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                ['password.confirm'],
                [],
            ),
        );
});

Route::prefix('admin/settings')->middleware(['web', 'auth'])->group(function (): void {
    Route::redirect('/', settings_default_url());

    Route::livewire('general', General::class)->name('settings.general');
    Route::livewire('analytics', Analytics::class)->name('settings.analytics');
    Route::livewire('embed-code', EmbedCode::class)->name('settings.embed-code');
    Route::livewire('static-pages', StaticPages::class)->name('settings.static-pages');
});

Route::prefix('admin/settings')->middleware(['web', 'auth', 'verified'])->group(function (): void {
        Route::livewire('security', Security::class)
            ->middleware(
                when(
                    Features::canManageTwoFactorAuthentication()
                    && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                    ['password.confirm'],
                    [],
                ),
            )
            ->name('security.edit');
});
