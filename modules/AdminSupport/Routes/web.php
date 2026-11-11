<?php

use Illuminate\Support\Facades\Route;
use Modules\AdminSupport\Livewire\SupportCreate;
use Modules\AdminSupport\Livewire\SupportIndex;
use Modules\AdminSupport\Livewire\SupportShow;
use Modules\AdminSupport\Livewire\SupportTaxonomy;

Route::middleware(['web', 'auth', 'verified'])
    ->prefix('admin/support')
    ->name('admin-support.')
    ->group(function (): void {
        Route::get('/', SupportIndex::class)->name('index');
        Route::get('/create', SupportCreate::class)->name('create');

        Route::get('/categories', SupportTaxonomy::class)
            ->defaults('resource', 'categories')
            ->name('categories.index');

        Route::get('/labels', SupportTaxonomy::class)
            ->defaults('resource', 'labels')
            ->name('labels.index');

        Route::get('/types', SupportTaxonomy::class)
            ->defaults('resource', 'types')
            ->name('types.index');

        Route::get('/{ticket}', SupportShow::class)->name('show');
    });
