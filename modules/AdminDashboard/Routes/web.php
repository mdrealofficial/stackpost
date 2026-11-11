<?php

use Illuminate\Support\Facades\Route;
use Modules\AdminDashboard\Http\Controllers\AdminDashboardController;
use Modules\AdminDashboard\Livewire\DashboardIndex;

Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
    Route::get(config('modules.admindashboard.route_prefix', 'dashboard'), DashboardIndex::class)
        ->name('dashboard');

    Route::post(config('modules.admindashboard.route_prefix', 'dashboard').'/layout', [AdminDashboardController::class, 'updateLayout'])
        ->name('dashboard.layout.update');
});
