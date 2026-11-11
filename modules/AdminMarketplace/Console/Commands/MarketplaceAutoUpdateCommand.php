<?php

namespace Modules\AdminMarketplace\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Modules\AdminMarketplace\Models\MarketplacePackage;
use Modules\AdminMarketplace\Services\MarketplacePackageService;
use Modules\AdminMarketplace\Services\ShopProductCatalogService;
use Modules\AdminSettings\Support\OptionStore;
use Throwable;

class MarketplaceAutoUpdateCommand extends Command
{
    protected $signature = 'marketplace:auto-update';

    protected $description = 'Automatically update installed marketplace purchase packages when newer versions are available.';

    public function handle(
        OptionStore $options,
        ShopProductCatalogService $catalog,
        MarketplacePackageService $packages
    ): int {
        if ((string) $options->get('marketplace_auto_update_status', '0') !== '1') {
            $this->info('Marketplace auto-update is disabled.');

            return self::SUCCESS;
        }

        $marketplaceApiBase = trim((string) $catalog->marketplaceApiBase());

        if ($marketplaceApiBase === '') {
            $this->error('Marketplace API base is not configured.');

            return self::FAILURE;
        }

        $website = rtrim((string) config('app.url', ''), '/');
        $domain = trim((string) parse_url($website, PHP_URL_HOST));
        $domain = preg_replace('/^www\./', '', $domain ?? '') ?: '';

        if ($website === '' || $domain === '') {
            $this->error('APP_URL must be configured before marketplace auto-update can run.');

            return self::FAILURE;
        }

        $catalogProducts = collect($catalog->catalog());
        $latestVersionMap = $catalogProducts
            ->filter(fn (array $product) => filled($product['id'] ?? null) && filled($product['version'] ?? null))
            ->mapWithKeys(fn (array $product) => [(string) $product['id'] => trim((string) $product['version'])]);

        $purchasePackages = MarketplacePackage::query()
            ->where('source_type', 'purchase')
            ->whereNotNull('product_id')
            ->orderBy('title')
            ->get();

        $updated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($purchasePackages as $package) {
            $latestVersion = trim((string) ($latestVersionMap[(string) $package->product_id] ?? ''));
            $currentVersion = trim((string) ($package->version ?? ''));

            if ($latestVersion === '' || $currentVersion === '' || version_compare($latestVersion, $currentVersion, '<=')) {
                $skipped++;
                continue;
            }

            try {
                $packages->updateFromPurchase($package, $marketplaceApiBase, $domain, $website);
                $updated++;

                $this->line(sprintf(
                    'Updated %s from %s to %s.',
                    $package->title,
                    $currentVersion !== '' ? $currentVersion : 'unknown',
                    $latestVersion
                ));
            } catch (Throwable $exception) {
                $failed++;
                report($exception);

                $this->warn(sprintf(
                    'Failed updating %s: %s',
                    $package->title,
                    $exception->getMessage()
                ));
            }
        }

        Cache::forget('admin-dashboard:marketplace-update-notice');

        $this->info(sprintf(
            'Marketplace auto-update finished. Updated: %d, skipped: %d, failed: %d.',
            $updated,
            $skipped,
            $failed
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
