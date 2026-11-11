<?php

use Illuminate\Support\Facades\Route;
use Modules\AdminAITemplate\Livewire\TemplateIndex;
use Modules\AdminAITemplate\Models\AiTemplate;

Route::prefix('admin/ai-templates')
    ->middleware(['web', 'auth'])
    ->name('admin-ai-templates.')
    ->group(function (): void {
        Route::get('/', TemplateIndex::class)->name('index');
        Route::redirect('/create', '/admin/ai-templates?create=1')->name('create');
        Route::get('/{template}/edit', function (AiTemplate $template) {
            return redirect()->route('admin-ai-templates.index', ['edit' => $template->id]);
        })->name('edit');
    });
