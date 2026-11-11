<?php

use Illuminate\Support\Facades\Route;
use Modules\AdminMenuBuilder\Livewire\MenuBuilder;

Route::middleware(['web', 'auth'])->group(function (): void {
    Route::livewire('admin/menu-builder', MenuBuilder::class)->name('admin.menu-builder');
});
