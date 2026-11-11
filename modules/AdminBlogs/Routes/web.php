<?php

use Illuminate\Support\Facades\Route;
use Modules\AdminBlogs\Http\Controllers\AdminBlogController;
use Modules\AdminBlogs\Livewire\BlogForm;
use Modules\AdminBlogs\Livewire\BlogIndex;
use Modules\AdminBlogs\Livewire\RssIndex;
use Modules\AdminBlogs\Livewire\RssLogsIndex;

Route::middleware(['web', 'auth', 'verified'])
    ->prefix('admin/blogs')
    ->name('admin-blogs.')
    ->group(function (): void {
        Route::get('/', BlogIndex::class)->name('index');
        Route::get('/create', BlogForm::class)->name('create');
        Route::get('/rss', RssIndex::class)->name('rss.index');
        Route::get('/rss/logs', RssLogsIndex::class)->name('rss.logs');
        Route::post('/ai/content', [AdminBlogController::class, 'generateAiContent'])->name('ai.content');
        Route::post('/ai/image', [AdminBlogController::class, 'generateAiImage'])->name('ai.image');
        Route::post('/categories', [AdminBlogController::class, 'createCategory'])->name('categories.store');
        Route::get('/tags/search', [AdminBlogController::class, 'searchTags'])->name('tags.search');
        Route::post('/tags', [AdminBlogController::class, 'createTag'])->name('tags.store');
        Route::post('/', [AdminBlogController::class, 'store'])->name('store');
        Route::get('/{blog}/preview', [AdminBlogController::class, 'preview'])->name('preview');
        Route::post('/{blog}/duplicate', [AdminBlogController::class, 'duplicate'])->name('duplicate');
        Route::get('/{blog}/edit', BlogForm::class)->name('edit');
        Route::put('/{blog}', [AdminBlogController::class, 'update'])->name('update');
        Route::delete('/{blog}', [AdminBlogController::class, 'destroy'])->name('destroy');
    });
