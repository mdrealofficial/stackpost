<?php

use Illuminate\Support\Facades\Route;
use Modules\AdminSystemInformation\Livewire\SystemInformationIndex;

Route::middleware(['web', 'auth'])->group(function () {
    Route::livewire('settings/system-information', SystemInformationIndex::class);
});

Route::prefix('admin/settings')->middleware(['web', 'auth'])->group(function () {
    Route::livewire('system-information', SystemInformationIndex::class)->name('settings.system-information');
});
