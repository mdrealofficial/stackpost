<?php

use Illuminate\Support\Facades\Route;
use Modules\AdminAI\Livewire\AiConfig;
use Modules\AdminAI\Livewire\AiReportIndex;
use Modules\AdminAI\Livewire\AiUsageLogIndex;

Route::middleware(['web', 'auth'])->group(function () {
    Route::livewire('settings/ai-config', AiConfig::class);
});

Route::prefix('admin/settings')->middleware(['web', 'auth'])->group(function () {
    Route::livewire('ai-config', AiConfig::class)->name('settings.ai');
});

Route::prefix('admin/ai-usage-logs')
    ->middleware(['web', 'auth'])
    ->name('admin-ai-usage-logs.')
    ->group(function (): void {
        Route::livewire('/', AiUsageLogIndex::class)->name('index');
    });

Route::prefix('admin/ai-report')
    ->middleware(['web', 'auth'])
    ->name('admin-ai-report.')
    ->group(function (): void {
        Route::livewire('/', AiReportIndex::class)->name('index');
    });
