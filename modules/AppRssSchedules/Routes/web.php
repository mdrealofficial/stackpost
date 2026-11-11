<?php

use Illuminate\Support\Facades\Route;
use Modules\AppRssSchedules\Livewire\RssScheduleForm;
use Modules\AppRssSchedules\Livewire\RssScheduleIndex;

Route::middleware(['web', 'auth', 'verified'])
    ->prefix('portal/rss-schedules')
    ->name('portal.rss-schedules.')
    ->group(function (): void {
        Route::livewire('/', RssScheduleIndex::class)->name('index');
        Route::livewire('/create', RssScheduleForm::class)->name('create');
        Route::livewire('/{rssSchedule}/edit', RssScheduleForm::class)->name('edit');
    });
