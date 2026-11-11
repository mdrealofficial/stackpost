<?php

namespace Modules\AdminMarketplace\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\AdminMarketplace\Models\MarketplacePackage;
use Modules\AdminMarketplace\Services\MarketplacePackageService;
use Modules\AdminMarketplace\Services\ShopProductCatalogService;

#[Title('Installed packages')]
class MarketplacePackages extends Component
{
    protected MarketplacePackageService $packages;

    protected ShopProductCatalogService $catalog;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'status', except: 'all')]
    public string $statusFilter = 'all';

    public function boot(
        MarketplacePackageService $packages,
        ShopProductCatalogService $catalog,
    ): void
    {
        $this->packages = $packages;
        $this->catalog = $catalog;
    }

    public function resetPackageFilters(): void
    {
        $this->search = '';
        $this->statusFilter = 'all';
    }

    public function render(): View
    {
        $filters = [
            'q' => $this->search,
            'status' => $this->statusFilter,
        ];

        $packages = $this->filterPackages(
            $this->packages->discover()
                ->whereIn('source_type', ['purchase', 'zip']),
            $filters
        )->values();

        $catalogProducts = $this->catalog->configured() ? $this->catalog->catalog() : collect();

        $packages = $packages->map(function (MarketplacePackage $package) use ($catalogProducts): MarketplacePackage {
            $package->has_update = false;
            $package->latest_version = null;

            if ($package->source_type !== 'purchase') {
                return $package;
            }

            $productId = (int) ($package->product_id ?: data_get($package->meta, 'product_id', 0));

            if ($productId <= 0) {
                return $package;
            }

            $product = $catalogProducts->first(
                fn (array $product): bool => (int) ($product['product_id'] ?? $product['id'] ?? 0) === $productId
            );

            if (! is_array($product)) {
                return $package;
            }

            $latestVersion = trim((string) ($product['version'] ?? ''));
            $installedVersion = trim((string) ($package->version ?? ''));

            if ($latestVersion === '' || $installedVersion === '') {
                return $package;
            }

            if (version_compare($latestVersion, $installedVersion, '>')) {
                $package->has_update = true;
                $package->latest_version = $latestVersion;
            }

            return $package;
        })->values();

        return view('adminmarketplace::packages', [
            'packages' => $packages,
            'filters' => $filters,
            'summary' => [
                'total' => $packages->count(),
                'active' => $packages->where('is_active', true)->count(),
                'inactive' => $packages->where('is_active', false)->count(),
                'zip' => $packages->where('source_type', 'zip')->count(),
            ],
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Installed packages'),
        ]);
    }

    protected function filterPackages(Collection $packages, array $filters): Collection
    {
        return $packages
            ->when(filled($filters['q'] ?? null), function (Collection $collection) use ($filters) {
                $search = mb_strtolower(trim((string) ($filters['q'] ?? '')));

                return $collection->filter(function (MarketplacePackage $package) use ($search): bool {
                    $haystack = mb_strtolower(implode(' ', array_filter([
                        $package->title,
                        $package->module_name,
                        $package->description,
                        $package->version,
                    ])));

                    return str_contains($haystack, $search);
                });
            })
            ->when(($filters['status'] ?? 'all') !== 'all', function (Collection $collection) use ($filters) {
                return $collection->where('is_active', (bool) ((int) $filters['status']));
            });
    }
}
