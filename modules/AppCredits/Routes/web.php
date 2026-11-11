<?php

use Illuminate\Support\Facades\Route;
use Modules\AppCredits\Http\Controllers\CreditUsageController;
use Modules\AppCredits\Livewire\CreditPackCheckoutPage;
use Modules\AppCredits\Livewire\CreditSettingsPage;

Route::middleware(['web', 'auth'])->group(function (): void {
    Route::prefix('admin/settings')->group(function (): void {
        Route::livewire('credits', CreditSettingsPage::class)->name('settings.credits');
    });
});

Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
    Route::prefix('portal')->group(function (): void {
        Route::get('credits', [CreditUsageController::class, 'index'])->name('portal.credits');
        Route::redirect('ai-studio/credits', '/portal/credits');
    });

    Route::prefix('payment')->group(function (): void {
        Route::livewire('/credits/{pack}', CreditPackCheckoutPage::class)->name('payment.credits');
    });
});
