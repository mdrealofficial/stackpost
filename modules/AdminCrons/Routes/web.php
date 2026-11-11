<?php

use Illuminate\Support\Facades\Route;
use Modules\AdminCrons\Http\Controllers\CronExecutionController;
use Modules\AdminCrons\Livewire\CronsIndex;

Route::middleware(['web', 'auth'])->group(function () {
    Route::livewire('settings/crons', CronsIndex::class);
});

Route::prefix('admin/settings')->middleware(['web', 'auth'])->group(function () {
    Route::livewire('crons', CronsIndex::class)->name('admin-crons.index');
});

Route::prefix('cron')->middleware('web')->group(function () {
    Route::get('{task}', CronExecutionController::class)->name('admin-crons.execute');
});
