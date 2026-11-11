<?php

use Illuminate\Support\Facades\Route;
use Modules\AdminPlans\Livewire\PlanForm;
use Modules\AdminPlans\Livewire\PlanIndex;

Route::middleware(['web', 'auth', 'verified'])
    ->prefix('admin/plans')
    ->name('admin-plans.')
    ->group(function (): void {
        Route::get('/', PlanIndex::class)->name('index');
        Route::get('/create', PlanForm::class)->name('create');
        Route::get('/{plan}/edit', PlanForm::class)->name('edit');
    });
