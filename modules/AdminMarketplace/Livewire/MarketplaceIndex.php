<?php

namespace Modules\AdminMarketplace\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\AdminMarketplace\Models\MarketplacePackage;
use Modules\AdminMarketplace\Services\MarketplacePackageService;
use Modules\AdminMarketplace\Services\ShopProductCatalogService;

#[Title('Marketplace')]
class MarketplaceIndex extends Component
{
    use WithPagination;

    protected ShopProductCatalogService $catalog;

    protected string $paginationTheme = 'tailwind';

    protected int $perPage = 27;

    #[Url(as: 'catalog_q', except: '')]
    public string $catalogSearch = '';

    #[Url(as: 'catalog_category', except: 'all')]
    public string $catalogCategory = 'all';

    #[Url(as: 'catalog_featured', except: 'all')]
    public string $catalogFeatured = 'all';

    #[Url(as: 'catalog_type', except: 'all')]
    public string $catalogType = 'all';

    public function boot(
        MarketplacePackageService $packages,
        ShopProductCatalogService $catalog,
    ): void {
        $this->catalog = $catalog;
        $this->packageService = $packages;
    }

    protected MarketplacePackageService $packageService;

    public function resetCatalogFilters(): void
    {
        $this->catalogSearch = '';
        $this->catalogCategory = 'all';
        $this->catalogFeatured = 'all';
        $this->catalogType = 'all';
        $this->resetPage();
    }

    public function toggleCatalogFeatured(): void
    {
        $this->catalogFeatured = $this->catalogFeatured === 'featured' ? 'all' : 'featured';
        $this->resetPage();
    }

    public function toggleCatalogType(string $type): void
    {
        $this->catalogType = $this->catalogType === $type ? 'all' : $type;
        $this->resetPage();
    }

    public function updatedCatalogSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCatalogCategory(): void
    {
        $this->resetPage();
    }

    public function updatedCatalogFeatured(): void
    {
        $this->resetPage();
    }

    public function updatedCatalogType(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $catalogFilters = [
            'q' => $this->catalogSearch,
            'category' => $this->catalogCategory,
            'featured' => $this->catalogFeatured,
            'type' => $this->catalogType,
        ];

        $packages = $this->packageService->discover()->values();
        $catalogProducts = $this->catalog->catalog($catalogFilters)->map(function (array $product) use ($packages): array {
            $matchedPackage = $this->matchPackageToProduct($packages, $product);
            $product['matched_package'] = $matchedPackage;
            $product['is_installed'] = $matchedPackage !== null;
            $product['has_update'] = $matchedPackage !== null
                && $matchedPackage->source_type === 'purchase'
                && filled($product['version'])
                && filled($matchedPackage->version)
                && version_compare((string) $product['version'], (string) $matchedPackage->version, '>');
            $product['latest_version'] = $product['has_update'] ? $product['version'] : null;

            return $product;
        });

        $catalogProducts = $this->paginateCollection($catalogProducts, $this->perPage);

        return view('adminmarketplace::index', [
            'summary' => [
                'total' => $packages->count(),
                'active' => $packages->where('is_active', true)->count(),
                'inactive' => $packages->where('is_active', false)->count(),
                'zip' => $packages->where('source_type', 'zip')->count(),
            ],
            'catalogProducts' => $catalogProducts,
            'catalogCategories' => $this->catalog->categories(),
            'catalogFilters' => $catalogFilters,
            'catalogSummary' => $this->catalog->summary(collect($catalogProducts->items())),
            'catalogConfigured' => $this->catalog->configured(),
            'storefrontApiBase' => route('admin-marketplace.storefront.cart'),
            'shopAppUrl' => $this->catalog->appUrl(),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Marketplace'),
        ]);
    }

    protected function paginateCollection(Collection $items, int $perPage): LengthAwarePaginator
    {
        $page = max(1, (int) $this->getPage());
        $total = $items->count();
        $results = $items->forPage($page, $perPage)->values();

        return new LengthAwarePaginator(
            $results,
            $total,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'pageName' => 'page',
            ]
        );
    }

    protected function matchPackageToProduct(Collection $packages, array $product): ?MarketplacePackage
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
