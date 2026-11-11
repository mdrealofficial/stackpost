<?php

use Illuminate\Support\Facades\Route;
use Modules\AdminFaqs\Livewire\FaqIndex;

Route::middleware(['web', 'auth', 'verified'])
    ->prefix('admin/faqs')
    ->name('admin-faqs.')
    ->group(function (): void {
        Route::get('/', FaqIndex::class)->name('index');
    });
