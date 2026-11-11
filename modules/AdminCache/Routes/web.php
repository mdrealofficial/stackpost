<?php

use Illuminate\Support\Facades\Route;
use Modules\AdminCache\Livewire\CacheIndex;

Route::middleware(['web', 'auth'])->group(function () {
    Route::livewire('settings/cache', CacheIndex::class);
});

Route::prefix('admin/settings')->middleware(['web', 'auth'])->group(function () {
    Route::livewire('cache', CacheIndex::class)->name('admin-cache.index');
});
