<?php

use Illuminate\Support\Facades\Route;
use Modules\AdminFaker\Livewire\FakerIndex;

Route::middleware(['web', 'auth'])
    ->prefix('admin/faker')
    ->name('admin-faker.')
    ->group(function (): void {
        Route::get('/', FakerIndex::class)->name('index');
    });
