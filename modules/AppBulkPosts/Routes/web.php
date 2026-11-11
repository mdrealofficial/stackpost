<?php

use Illuminate\Support\Facades\Route;
use Modules\AppBulkPosts\Http\Controllers\AppBulkPostController;
use Modules\AppBulkPosts\Livewire\BulkPostsIndex;

Route::middleware(['web', 'auth', 'verified'])
    ->prefix(config('modules.appbulkposts.route_prefix', 'portal/bulk-posts'))
    ->group(function (): void {
        Route::livewire('/', BulkPostsIndex::class)->name('portal.bulk-posts');
        Route::livewire('/history', BulkPostsIndex::class)->name('portal.bulk-posts.history');
        Route::livewire('/history/{batch}', BulkPostsIndex::class)->whereNumber('batch')->name('portal.bulk-posts.show');
        Route::get('/template.csv', [AppBulkPostController::class, 'template'])->name('portal.bulk-posts.template');
    });
