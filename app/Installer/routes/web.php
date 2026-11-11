<?php

use App\Installer\Http\Controllers\InstallerController;
use App\Installer\Http\Middleware\RedirectIfInstalled;
use Illuminate\Support\Facades\Route;

Route::middleware(RedirectIfInstalled::class)->group(function (): void {
    Route::get('/installer', [InstallerController::class, 'index'])->name('installer.index');
    Route::post('/installer', [InstallerController::class, 'store'])->name('installer.store');
});
