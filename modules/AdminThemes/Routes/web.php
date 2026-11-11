<?php

use Illuminate\Support\Facades\Route;
use Modules\AdminThemes\Http\Controllers\AdminThemeController;
use Modules\AdminThemes\Livewire\BackendThemePage;
use Modules\AdminThemes\Livewire\FrontendThemePage;

Route::middleware(['web', 'auth', 'verified'])->group(function () {
    Route::prefix('admin/themes')->group(function () {
        Route::redirect('/', '/admin/themes/backend')->name('admin-themes.index');
        Route::get('/frontend', FrontendThemePage::class)->name('admin-themes.frontend');
        Route::get('/frontend/export', [AdminThemeController::class, 'exportFrontend'])->name('admin-themes.frontend.export');
        Route::put('/frontend', [AdminThemeController::class, 'updateFrontend'])->name('admin-themes.frontend.update');
        Route::get('/backend', BackendThemePage::class)->name('admin-themes.backend');
        Route::get('/backend/export', [AdminThemeController::class, 'exportBackend'])->name('admin-themes.backend.export');
        Route::put('/backend', [AdminThemeController::class, 'updateBackend'])->name('admin-themes.backend.update');
    });
});
