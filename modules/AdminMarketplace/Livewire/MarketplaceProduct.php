<?php

namespace Modules\AdminMarketplace\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Modules\AdminMarketplace\Models\MarketplacePackage;
use Modules\AdminMarketplace\Services\MarketplacePackageService;
use Modules\AdminMarketplace\Services\ShopProductCatalogService;

class MarketplaceProduct extends Component
{
    protected MarketplacePackageService $packages;

    protected ShopProductCatalogService $catalog;

    public string $slug = '';

    public function boot(
        MarketplacePackageService $packages,
        ShopProductCatalogService $catalog,
    ): void {
        $this->packages = $packages;
        $this->catalog = $catalog;
    }

    public function mount(string $slug): void
    {
        $this->slug = $slug;
    }

    public function render(): View
    {
        $product = $this->catalog->findBySlug($this->slug);

        abort_if($product === null, 404);

        $matchedPackage = $this->matchPackageToProduct($this->packages->discover(), $product);
        $product['matched_package'] = $matchedPackage;
        $product['is_installed'] = $matchedPackage !== null;
        $product['has_update'] = $matchedPackage !== null
            && $matchedPackage->source_type === 'purchase'
            && filled($product['version'])
            && filled($matchedPackage->version)
            && version_compare((string) $product['version'], (string) $matchedPackage->version, '>');
        $product['latest_version'] = $product['has_update'] ? $product['version'] : null;

        return view('adminmarketplace::product', [
            'product' => $product,
            'storefrontApiBase' => route('admin-marketplace.storefront.cart'),
            'shopAppUrl' => $this->catalog->appUrl(),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => $product['name'],
        ]);
    }

    protected function matchPackageToProduct(\Illuminate\Support\Collection $packages, array $product): ?MarketplacePackage
    {
        $productId = (int) ($product['product_id'] ?? $product['id'] ?? 0);

        if ($productId > 0) {
            $package = $packages->first(function (MarketplacePackage $package) use ($productId): bool {
                return (int) ($package->product_id ?: data_get($package->meta, 'product_id', 0)) === $productId;
            });

            if ($package instanceof MarketplacePackage) {
                return $package;
            }
        }

        $moduleName = trim((string) ($product['module_name'] ?? ''));

        if ($moduleName !== '') {
            $package = $packages->first(fn (MarketplacePackage $package) => strcasecmp((string) $package->module_name, $moduleName) === 0);

            if ($package instanceof MarketplacePackage) {
                return $package;
            }
        }

        $folderInstall = trim((string) ($product['folder_install'] ?? ''));
        if ($folderInstall !== '' && $folderInstall !== './') {
            $normalizedFolder = trim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $folderInstall), DIRECTORY_SEPARATOR);

            $package = $packages->first(function (MarketplacePackage $package) use ($normalizedFolder): bool {
                $installPath = trim((string) $package->install_path);
                $relativeInstallPath = trim(str_replace(base_path(), '', $installPath), DIRECTORY_SEPARATOR.' ');

                return $relativeInstallPath !== ''
                    && strcasecmp($relativeInstallPath, $normalizedFolder) === 0;
            });

            if ($package instanceof MarketplacePackage) {
                return $package;
            }
        }

        return null;
    }
}
