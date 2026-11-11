<?php

use Illuminate\Support\Facades\Route;
use Modules\AdminBlogTags\Livewire\TagForm;
use Modules\AdminBlogTags\Livewire\TagIndex;

Route::middleware(['web', 'auth', 'verified'])
    ->prefix('admin/blog-tags')
    ->name('admin-blog-tags.')
    ->group(function (): void {
        Route::get('/', TagIndex::class)->name('index');
        Route::get('/create', TagForm::class)->name('create');
        Route::get('/{blogTag}/edit', TagForm::class)->name('edit');
    });
