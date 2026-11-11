<?php

use Illuminate\Support\Facades\Route;
use Modules\AdminAITemplateCategories\Livewire\CategoryIndex;

Route::prefix('admin/ai-template-categories')
    ->middleware(['web', 'auth'])
    ->name('admin-ai-template-categories.')
    ->group(function (): void {
        Route::get('/', CategoryIndex::class)->name('index');
    });
