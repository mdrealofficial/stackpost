<?php

use Illuminate\Support\Facades\Route;
use Modules\AppUrlShorteners\Livewire\UrlShorteners;

Route::middleware(['web', 'auth'])->group(function (): void {
    Route::prefix('admin/settings')->group(function (): void {
        Route::livewire('url-shorteners', UrlShorteners::class)->name('settings.url-shorteners');
    });
});
