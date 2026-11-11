<?php

use Illuminate\Support\Facades\Route;
use Modules\AdminUser\Http\Controllers\AdminUserLogController;
use Modules\AdminUser\Http\Controllers\AdminUserReportController;
use Modules\AdminUser\Http\Controllers\PortalActivityController;
use Modules\AdminUser\Http\Controllers\UserImpersonationController;
use Modules\AdminUser\Livewire\AccessIdentity;
use Modules\AdminUser\Livewire\RoleForm;
use Modules\AdminUser\Livewire\RoleIndex;
use Modules\AdminUser\Livewire\TeamIndex;
use Modules\AdminUser\Livewire\UserForm;
use Modules\AdminUser\Livewire\UserIndex;

Route::middleware(['web', 'auth', 'verified'])
    ->prefix(config('modules.adminuser.route_prefix', 'admin/users'))
    ->name('admin-users.')
    ->group(function (): void {
        Route::livewire('/', UserIndex::class)->name('index');
        Route::livewire('/create', UserForm::class)->name('create');
        Route::post('/{user}/impersonate', [UserImpersonationController::class, 'store'])->name('impersonate');
        Route::livewire('/{user}/edit', UserForm::class)->name('edit');
    });

Route::middleware(['web', 'auth', 'verified'])
    ->prefix('admin/user-report')
    ->name('admin-user-report.')
    ->group(function (): void {
        Route::get('/', [AdminUserReportController::class, 'index'])->name('index');
    });

Route::middleware(['web', 'auth', 'verified'])
    ->prefix('admin/user-logs')
    ->name('admin-user-logs.')
    ->group(function (): void {
        Route::get('/', [AdminUserLogController::class, 'index'])->name('index');
    });

Route::middleware(['web', 'auth', 'verified'])
    ->prefix('admin/user-roles')
    ->name('admin-user-roles.')
    ->group(function (): void {
        Route::livewire('/', RoleIndex::class)->name('index');
        Route::livewire('/create', RoleForm::class)->name('create');
        Route::livewire('/{role}/edit', RoleForm::class)->name('edit');
    });

Route::middleware(['web', 'auth', 'verified'])
    ->prefix('admin/user-teams')
    ->name('admin-user-teams.')
    ->group(function (): void {
        Route::livewire('/', TeamIndex::class)->name('index');
        Route::livewire('/{team}/edit', \Modules\AdminUser\Livewire\TeamForm::class)->name('edit');
    });

Route::middleware(['web', 'auth'])->group(function (): void {
    Route::post('impersonation/leave', [UserImpersonationController::class, 'destroy'])->name('impersonation.leave');
    Route::livewire('settings/auth', AccessIdentity::class);
    Route::get('portal/activity', [PortalActivityController::class, 'index'])
        ->middleware('verified')
        ->name('portal.activity');
});

Route::prefix('admin/settings')
    ->middleware(['web', 'auth'])
    ->group(function (): void {
        Route::livewire('auth', AccessIdentity::class)->name('settings.auth');
    });
