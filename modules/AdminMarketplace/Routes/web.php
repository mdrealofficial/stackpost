<?php

use Illuminate\Support\Facades\Route;
use Modules\AdminMarketplace\Http\Controllers\AdminMarketplaceController;
use Modules\AdminMarketplace\Livewire\MarketplaceIndex;
use Modules\AdminMarketplace\Livewire\MarketplacePackages;
use Modules\AdminMarketplace\Livewire\MarketplaceProduct;
use Modules\AdminMarketplace\Livewire\MarketplaceSettings;

Route::middleware(['web', 'auth'])->group(function (): void {
    Route::livewire('settings/marketplace', MarketplaceSettings::class);
});

Route::prefix('admin/settings')->middleware(['web', 'auth'])->group(function (): void {
    Route::livewire('marketplace', MarketplaceSettings::class)->name('settings.marketplace');
});

Route::middleware(['web', 'auth', 'verified'])
    ->prefix('admin/marketplace')
    ->name('admin-marketplace.')
    ->group(function (): void {
        Route::get('/', MarketplaceIndex::class)->name('index');
        Route::get('/packages', MarketplacePackages::class)->name('packages.index');
        Route::get('/catalog/{slug}', MarketplaceProduct::class)->name('products.show');
        Route::get('/dashboard/update-notice', [AdminMarketplaceController::class, 'dashboardUpdateNotice'])->name('dashboard.update-notice');
        Route::post('/rescan', [AdminMarketplaceController::class, 'rescan'])->name('rescan');
        Route::post('/install', [AdminMarketplaceController::class, 'install'])->name('install');
        Route::get('/storefront/cart', [AdminMarketplaceController::class, 'storefrontCart'])->name('storefront.cart');
        Route::post('/storefront/cart/items', [AdminMarketplaceController::class, 'storefrontCartAdd'])->name('storefront.cart.items.store');
        Route::delete('/storefront/cart/items/{itemKey}', [AdminMarketplaceController::class, 'storefrontCartRemove'])->name('storefront.cart.items.destroy');
        Route::delete('/storefront/cart', [AdminMarketplaceController::class, 'storefrontCartClear'])->name('storefront.cart.clear');
        Route::post('/{package:id_secure}/update', [AdminMarketplaceController::class, 'update'])->name('update');
        Route::delete('/{package:id_secure}', [AdminMarketplaceController::class, 'destroy'])->name('destroy');
    });
