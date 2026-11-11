<?php

use Illuminate\Support\Facades\Route;
use Modules\AdminBlogCategories\Livewire\CategoryForm;
use Modules\AdminBlogCategories\Livewire\CategoryIndex;

Route::middleware(['web', 'auth', 'verified'])
    ->prefix('admin/blog-categories')
    ->name('admin-blog-categories.')
    ->group(function (): void {
        Route::get('/', CategoryIndex::class)->name('index');
        Route::get('/create', CategoryForm::class)->name('create');
        Route::get('/{blogCategory}/edit', CategoryForm::class)->name('edit');
    });
