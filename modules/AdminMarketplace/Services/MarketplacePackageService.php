<?php

namespace Modules\AdminMarketplace\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\AdminMarketplace\Models\MarketplacePackage;
use RuntimeException;
use ZipArchive;

class MarketplacePackageService
{
    public function providersPath(): string
    {
        return base_path('bootstrap/providers.marketplace.php');
    }

    public function discover(): Collection
    {
        MarketplacePackage::query()
            ->where('source_type', 'local')
            ->delete();

        $trackedPackages = MarketplacePackage::query()
            ->whereIn('source_type', ['purchase', 'zip'])
            ->get()
            ->keyBy('package_key');
        $registeredProviders = $this->registeredMarketplaceProviders();
        $staticProviders = $this->registeredStaticProviders($registeredProviders);
        $moduleDirectories = collect(File::directories(base_path('modules')))
            ->map(function (string $directory) use ($trackedPackages, $registeredProviders, $staticProviders): ?array {
                $moduleName = basename($directory);
                $moduleJsonPath = $directory.'/module.json';
                if (! File::exists($moduleJsonPath)) {
                    return null;
                }

                $moduleJson = json_decode((string) File::get($moduleJsonPath), true);

                if (! is_array($moduleJson)) {
                    return null;
                }

                $packageKey = Str::slug((string) ($moduleJson['alias'] ?? $moduleName));
                $existing = $trackedPackages->get($packageKey);
                $providers = collect((array) data_get($moduleJson, 'providers', []))
                    ->filter(fn ($provider) => is_string($provider) && trim($provider) !== '')
                    ->values()
                    ->all();
                $belongsToStaticBootstrap = $providers !== []
                    && collect($providers)->contains(fn ($provider) => in_array($provider, $staticProviders, true));

                if (! $existing || $belongsToStaticBootstrap) {
                    return null;
                }

                $providerEnabled = $providers === []
                    ? false
                    : collect($providers)->every(fn ($provider) => in_array($provider, $registeredProviders, true));
                $resolvedVersion = $this->resolveVersion($directory);
                $marketplaceVersion = trim((string) data_get($existing?->meta, 'marketplace_version', ''));
                $resolvedPackageVersion = ($existing?->source_type === 'purchase' && $marketplaceVersion !== '')
                    ? $marketplaceVersion
                    : ($resolvedVersion ?: $existing?->version ?: ($marketplaceVersion !== '' ? $marketplaceVersion : null));
                $resolvedTitle = $existing?->source_type === 'purchase'
                    ? $this->resolveMarketplaceProductTitle(
                        [
                            'name' => $existing?->title,
                            'product_id' => $existing?->product_id ?: data_get($existing?->meta, 'product_id'),
                            'slug' => $existing?->product_slug ?: data_get($existing?->meta, 'slug'),
                        ],
                        $moduleJson,
                        $moduleName,
                        (array) ($existing?->meta ?? [])
                    )
                    : (string) ($moduleJson['name'] ?? $moduleName);
                $resolvedTitle = $this->preferExistingPackageTitle($resolvedTitle, (string) ($existing?->title ?? ''), $moduleName);
                $resolvedDescription = $existing?->source_type === 'purchase'
                    ? (string) ($existing?->description ?: ($moduleJson['description'] ?? ''))
                    : (string) ($moduleJson['description'] ?? '');

                return [
                    'package_key' => $packageKey,
                    'module_name' => $moduleName,
                    'title' => $resolvedTitle,
                    'description' => $resolvedDescription,
                    'version' => $resolvedPackageVersion,
                    'source_type' => $existing->source_type,
                    'product_id' => $existing?->product_id ?: data_get($existing?->meta, 'product_id'),
                    'purchase_code' => $existing?->purchase_code ?: data_get($existing?->meta, 'purchase_code'),
                    'product_slug' => $existing?->product_slug ?: data_get($existing?->meta, 'slug'),
                    'license_type' => $existing?->license_type ?: data_get($existing?->meta, 'license'),
                    'licensed_domain' => $existing?->licensed_domain ?: data_get($existing?->meta, 'domain'),
                    'install_path' => $directory,
                    'providers' => $providers,
                    'is_active' => $providerEnabled,
                    'meta' => array_merge((array) ($existing?->meta ?? []), [
                        'alias' => $moduleJson['alias'] ?? null,
                        'keywords' => $moduleJson['keywords'] ?? [],
                    ]),
                    'id_secure' => $existing?->id_secure ?: Str::random(32),
                    'installed_at' => $existing?->installed_at ?: now(),
                ];
            })
            ->filter();

        foreach ($moduleDirectories as $package) {
            MarketplacePackage::query()->updateOrCreate(
                ['package_key' => $package['package_key']],
                [
                    'id_secure' => $package['id_secure'],
                    'module_name' => $package['module_name'],
                    'title' => $package['title'],
                    'description' => $package['description'],
                    'version' => $package['version'],
                    'source_type' => $package['source_type'],
                    'product_id' => $package['product_id'],
                    'purchase_code' => $package['purchase_code'],
                    'product_slug' => $package['product_slug'],
                    'license_type' => $package['license_type'],
                    'licensed_domain' => $package['licensed_domain'],
                    'install_path' => $package['install_path'],
                    'providers' => $package['providers'],
                    'meta' => $package['meta'],
                    'is_active' => $package['is_active'],
                    'installed_at' => $package['installed_at'],
                    'last_synced_at' => now(),
                ]
            );
        }

        $catalogProducts = null;

        try {
            $catalog = app(ShopProductCatalogService::class);
            $catalogProducts = $catalog->configured() ? $catalog->catalog() : collect();
        } catch (\Throwable) {
            $catalogProducts = collect();
        }

        return MarketplacePackage::query()
            ->orderByDesc('is_active')
            ->orderBy('title')
            ->get()
            ->map(function (MarketplacePackage $package) use ($catalogProducts): MarketplacePackage {
                if ($package->source_type !== 'purchase' || ! $this->isPlaceholderPackageTitle((string) $package->title)) {
                    return $package;
                }

                $resolvedTitle = $this->resolveMarketplaceProductTitle(
                    [
                        'name' => '',
                        'product_id' => $package->product_id ?: data_get($package->meta, 'product_id'),
                        'slug' => $package->product_slug ?: data_get($package->meta, 'slug'),
                    ],
                    [],
                    (string) ($package->module_name ?? ''),
                    (array) ($package->meta ?? [])
                );

                if ($this->isPlaceholderPackageTitle($resolvedTitle) && $catalogProducts instanceof Collection) {
                    $productId = (int) ($package->product_id ?: data_get($package->meta, 'product_id', 0));
                    $slug = trim((string) ($package->product_slug ?: data_get($package->meta, 'slug', '')));

                    $matchedProduct = $catalogProducts->first(function (array $product) use ($productId, $slug): bool {
                        if ($productId > 0 && (int) ($product['product_id'] ?? $product['id'] ?? 0) === $productId) {
                            return true;
                        }

                        return $slug !== '' && strcasecmp((string) ($product['slug'] ?? ''), $slug) === 0;
                    });

                    if (is_array($matchedProduct) && trim((string) ($matchedProduct['name'] ?? '')) !== '') {
                        $resolvedTitle = trim((string) $matchedProduct['name']);
                    }
                }

                if (! $this->isPlaceholderPackageTitle($resolvedTitle)) {
                    $package->forceFill([
                        'title' => $resolvedTitle,
                        'meta' => array_merge((array) ($package->meta ?? []), ['product_name' => $resolvedTitle]),
                    ])->save();
                }

                return $package;
            });
    }

    public function installFromZip(UploadedFile $zipFile): MarketplacePackage
    {
        $tempZipPath = storage_path('app/marketplace/'.Str::random(16).'.zip');
        File::ensureDirectoryExists(dirname($tempZipPath));
        $zipFile->move(dirname($tempZipPath), basename($tempZipPath));

        $zip = new ZipArchive();

        if ($zip->open($tempZipPath) !== true) {
            File::delete($tempZipPath);
            throw new RuntimeException(__('Unable to open the ZIP file.'));
        }

        $moduleNames = $this->resolveZipModuleDirectories($zip);

        if ($moduleNames === []) {
            $zip->close();
            File::delete($tempZipPath);
            throw new RuntimeException(__('The uploaded ZIP does not contain a valid module.json file.'));
        }

        $extractRoot = base_path('modules');

        foreach ($moduleNames as $moduleName) {
            $moduleJson = $this->readZipModuleJson($zip, $moduleName);
            $packageKey = Str::slug((string) ($moduleJson['alias'] ?? $moduleName));
            $existingPackage = MarketplacePackage::query()
                ->where('package_key', $packageKey)
                ->first();

            if ($existingPackage?->is_active) {
                $this->deactivate($existingPackage);
            }

            $moduleDirectory = $extractRoot.'/'.$moduleName;

            if (File::exists($moduleDirectory)) {
                File::deleteDirectory($moduleDirectory);
            }
        }

        $zip->extractTo($extractRoot);
        $zip->close();
        File::delete($tempZipPath);

        $installedPackages = collect($moduleNames)->map(function (string $moduleName) use ($moduleNames): MarketplacePackage {
            $moduleDirectory = base_path('modules/'.$moduleName);
            $moduleJsonPath = $moduleDirectory.'/module.json';

            if (! File::exists($moduleJsonPath)) {
                throw new RuntimeException(__('The installed package is missing module.json.'));
            }

            $moduleJson = json_decode((string) File::get($moduleJsonPath), true);

            if (! is_array($moduleJson) || blank($moduleJson['name'] ?? null)) {
                throw new RuntimeException(__('The installed module.json is invalid.'));
            }

            $packageKey = Str::slug((string) ($moduleJson['alias'] ?? $moduleName));
            $existingPackage = MarketplacePackage::query()
                ->where('package_key', $packageKey)
                ->first();

            $package = MarketplacePackage::query()->updateOrCreate(
                ['package_key' => $packageKey],
                [
                    'id_secure' => $existingPackage?->id_secure ?: Str::random(32),
                    'module_name' => $moduleName,
                    'title' => (string) ($moduleJson['name'] ?? $moduleName),
                    'description' => (string) ($moduleJson['description'] ?? ''),
                    'version' => $this->resolveVersion($moduleDirectory),
                    'source_type' => 'zip',
                    'product_id' => null,
                    'purchase_code' => null,
                    'product_slug' => null,
                    'license_type' => null,
                    'licensed_domain' => null,
                    'install_path' => $moduleDirectory,
                    'providers' => array_values((array) ($moduleJson['providers'] ?? [])),
                    'meta' => [
                        'alias' => $moduleJson['alias'] ?? null,
                        'keywords' => $moduleJson['keywords'] ?? [],
                        'bundle_modules' => $moduleNames,
                    ],
                    'is_active' => false,
                    'installed_at' => $existingPackage?->installed_at ?: now(),
                    'last_synced_at' => now(),
                ]
            );

            $this->runModuleMigrations($package);

            return $package;
        })->values();

        return $installedPackages->last();
    }

    public function installFromPurchaseCode(
        string $purchaseCode,
        string $marketplaceApiBase,
        string $domain,
        string $website
    ): MarketplacePackage {
        $purchaseCode = trim(preg_replace('/\s+/', '', $purchaseCode));

        if ($purchaseCode === '') {
            throw new RuntimeException(__('Purchase code is required.'));
        }

        $response = Http::withOptions(['verify' => false])
            ->acceptJson()
            ->asForm()
            ->timeout(60)
            ->post(rtrim($marketplaceApiBase, '/').'/install', [
                'purchase_code' => $purchaseCode,
                'domain' => $domain,
                'website' => $website,
            ]);

        $payload = $response->json();

        if (! $response->ok() || (int) data_get($payload, 'status', 0) !== 1) {
            throw new RuntimeException((string) data_get($payload, 'message', __('Purchase verification failed.')));
        }

        $downloadUrl = trim((string) data_get($payload, 'download_url', ''));
        $relativeInstallPath = trim((string) data_get($payload, 'install_path', ''));
        $moduleName = trim((string) data_get($payload, 'module_name', ''));
        $marketplaceVersion = trim((string) data_get($payload, 'version', ''));

        if ($downloadUrl === '' || $relativeInstallPath === '') {
            throw new RuntimeException(__('Missing install information from the marketplace response.'));
        }

        $installPath = $this->resolveMarketplaceInstallDirectory($relativeInstallPath, $moduleName);
        $isModuleInstall = $this->isInstallPathWithinModules($installPath);

        $tempZipPath = storage_path('app/marketplace/'.Str::random(16).'.zip');
        File::ensureDirectoryExists(dirname($tempZipPath));

        try {
            $downloadResponse = Http::withOptions(['verify' => false])
                ->timeout(120)
                ->get($downloadUrl);

            if (! $downloadResponse->ok()) {
                throw new RuntimeException(__('Download failed.'));
            }

            File::put($tempZipPath, $downloadResponse->body());
            
            $zip = new ZipArchive();
            if ($zip->open($tempZipPath) !== true) {
                throw new RuntimeException(__('Unable to open the ZIP file.'));
            }

            $moduleNames = $isModuleInstall ? $this->resolveZipModuleDirectories($zip) : [];
            $resolvedModuleName = $isModuleInstall ? $this->resolvePreferredZipModuleName($moduleNames, $moduleName) : null;

            if ($isModuleInstall) {
                if ($moduleNames === [] || $resolvedModuleName === null) {
                    throw new RuntimeException(__('The installed package is missing module.json.'));
                }

                $extractRoot = $this->resolveMarketplaceModuleExtractRoot($installPath);

                foreach ($moduleNames as $zipModuleName) {
                    $zipModuleDirectory = $extractRoot.'/'.$zipModuleName;

                    if (File::isDirectory($zipModuleDirectory)) {
                        File::deleteDirectory($zipModuleDirectory);
                    }
                }

                File::ensureDirectoryExists($extractRoot);
                $zip->extractTo($extractRoot);
                $zip->close();

                $installPath = $extractRoot.'/'.$resolvedModuleName;
            } else {
                $tempExtractRoot = storage_path('app/marketplace/extract-'.Str::random(16));
                File::ensureDirectoryExists($tempExtractRoot);

                try {
                    if (! $zip->extractTo($tempExtractRoot)) {
                        $zip->close();
                        throw new RuntimeException(__('Unable to extract the ZIP file.'));
                    }

                    $zip->close();

                    $sourceRoot = $this->resolveZipContentRoot($tempExtractRoot);
                    $this->copyDirectoryContents($sourceRoot, $installPath, ['.env', 'storage', 'bootstrap/cache']);
                } finally {
                    File::deleteDirectory($tempExtractRoot);
                }
            }
        } finally {
            File::delete($tempZipPath);
        }

        $moduleJson = [];
        $resolvedModuleName = $isModuleInstall ? ($resolvedModuleName ?? $moduleName) : $moduleName;
        $packageKey = Str::slug((string) (
            ($isModuleInstall ? null : data_get($payload, 'slug'))
            ?: ($isModuleInstall ? null : data_get($payload, 'name'))
            ?: ($resolvedModuleName !== '' ? $resolvedModuleName : ('purchase-package-'.(string) data_get($payload, 'product_id', Str::random(6))))
        ));

        if ($isModuleInstall) {
            $moduleJsonPath = rtrim($installPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'module.json';

            if (! File::exists($moduleJsonPath)) {
                throw new RuntimeException(__('The installed package is missing module.json.'));
            }

            $moduleJson = json_decode((string) File::get($moduleJsonPath), true);

            if (! is_array($moduleJson)) {
                throw new RuntimeException(__('The installed module.json is invalid.'));
            }

            $packageKey = Str::slug((string) ($moduleJson['alias'] ?? $resolvedModuleName));
        }

        $existingPackage = MarketplacePackage::query()
            ->where('package_key', $packageKey)
            ->first();

        if ($existingPackage?->is_active) {
            $this->deactivate($existingPackage);
        }

        $bundleProviders = $isModuleInstall
            ? collect($moduleNames)
                ->map(fn (string $bundleModuleName) => dirname($installPath).'/'.$bundleModuleName.'/module.json')
                ->filter(fn (string $bundleModuleJsonPath) => File::exists($bundleModuleJsonPath))
                ->flatMap(function (string $bundleModuleJsonPath): array {
                    $bundleModuleJson = json_decode((string) File::get($bundleModuleJsonPath), true);

                    return is_array($bundleModuleJson)
                        ? array_values((array) ($bundleModuleJson['providers'] ?? []))
                        : [];
                })
                ->filter(fn ($provider) => is_string($provider) && trim($provider) !== '')
                ->unique()
                ->values()
                ->all()
            : [];

        $resolvedProductTitle = $this->resolveMarketplaceProductTitle($payload, $moduleJson, $resolvedModuleName);
        $resolvedProductTitle = $this->preferExistingPackageTitle($resolvedProductTitle, (string) ($existingPackage?->title ?? ''), $resolvedModuleName);

        $package = MarketplacePackage::query()->updateOrCreate(
            ['package_key' => $packageKey],
            [
                'id_secure' => $existingPackage?->id_secure ?: Str::random(32),
                'module_name' => $resolvedModuleName !== '' ? $resolvedModuleName : null,
                'title' => $resolvedProductTitle,
                'description' => (string) ($moduleJson['description'] ?? data_get($payload, 'description', '')),
                'version' => $marketplaceVersion !== '' ? $marketplaceVersion : ($isModuleInstall ? $this->resolveVersion($installPath) : null),
                'source_type' => 'purchase',
                'product_id' => data_get($payload, 'product_id'),
                'purchase_code' => $purchaseCode,
                'product_slug' => data_get($payload, 'slug'),
                'license_type' => data_get($payload, 'license'),
                'licensed_domain' => $domain,
                'install_path' => $installPath,
                'providers' => $bundleProviders,
                'meta' => array_filter([
                    'alias' => $moduleJson['alias'] ?? null,
                    'keywords' => $moduleJson['keywords'] ?? [],
                    'marketplace_version' => $marketplaceVersion !== '' ? $marketplaceVersion : ($isModuleInstall ? $this->resolveVersion($installPath) : null),
                    'purchase_code' => $purchaseCode,
                    'product_id' => data_get($payload, 'product_id'),
                    'slug' => data_get($payload, 'slug'),
                    'license' => data_get($payload, 'license'),
                    'domain' => $domain,
                    'folder_install' => $relativeInstallPath,
                    'requested_module_name' => $moduleName,
                    'bundle_modules' => $moduleNames !== [] ? $moduleNames : null,
                    'product_name' => $resolvedProductTitle,
                ], fn ($value) => $value !== null && $value !== ''),
                'is_active' => false,
                'installed_at' => $existingPackage?->installed_at ?: now(),
                'last_synced_at' => now(),
            ]
        );

        if ($isModuleInstall && $resolvedModuleName !== '') {
            $this->runModuleMigrationsForModule($resolvedModuleName);
        } else {
            Artisan::call('migrate', [
                '--force' => true,
                '--no-interaction' => true,
            ]);
        }

        return $package;
    }

    public function updateFromPurchase(
        MarketplacePackage $package,
        string $marketplaceApiBase,
        string $domain,
        string $website
    ): MarketplacePackage {
        if ($package->source_type !== 'purchase') {
            throw new RuntimeException(__('Only marketplace purchase packages can be updated automatically.'));
        }

        $purchaseCode = trim((string) ($package->purchase_code ?: data_get($package->meta, 'purchase_code', '')));
        $productId = $package->product_id ?: data_get($package->meta, 'product_id');

        if ($purchaseCode === '' || blank($productId)) {
            throw new RuntimeException(__('This package is missing marketplace license information.'));
        }

        $installPath = $this->resolveStoredInstallPath($package);
        $normalizedBaseRoot = rtrim(str_replace('\\', '/', base_path()), '/').'/';
        $normalizedModulesRoot = rtrim(str_replace('\\', '/', base_path('modules')), '/').'/';
        $normalizedInstallPath = rtrim(str_replace('\\', '/', $installPath), '/').'/';
        $isModuleInstall = $installPath !== '' && str_starts_with($normalizedInstallPath, $normalizedModulesRoot);

        if ($installPath === '' || ! str_starts_with($normalizedInstallPath, $normalizedBaseRoot)) {
            throw new RuntimeException(__('The installed package path is invalid.'));
        }

        $response = Http::withOptions(['verify' => false])
            ->acceptJson()
            ->asForm()
            ->timeout(60)
            ->post(rtrim($marketplaceApiBase, '/').'/update', [
                'purchase_code' => $purchaseCode,
                'product_id' => $productId,
                'current_version' => (string) ($package->version ?? ''),
                'domain' => trim($domain),
                'website' => $website,
            ]);

        $payload = $response->json();

        if (! $response->ok() || (int) data_get($payload, 'status', 0) !== 1) {
            throw new RuntimeException((string) data_get($payload, 'message', __('No update available.')));
        }

        $downloadUrl = trim((string) data_get($payload, 'download_url', ''));
        $latestVersion = trim((string) data_get($payload, 'latest_version', data_get($payload, 'version', '')));
        $relativeInstallPath = trim((string) data_get($payload, 'install_path', ''));

        if ($downloadUrl === '' || $latestVersion === '') {
            throw new RuntimeException(__('Missing update information from the marketplace response.'));
        }

        if ($relativeInstallPath !== '') {
            $expectedInstallPath = $this->resolveMarketplaceInstallDirectory($relativeInstallPath, (string) $package->module_name);
            $normalizedExpectedPath = rtrim(str_replace('\\', '/', $expectedInstallPath), '/').'/';

            if ($normalizedExpectedPath !== $normalizedInstallPath) {
                $normalizedRelativeInstallPath = rtrim(str_replace('\\', '/', trim($relativeInstallPath)), '/');
                $isRootInstallHint = in_array($normalizedRelativeInstallPath, ['', '.', './'], true);
                $installerManaged = (bool) data_get($package->meta, 'installer_managed', false)
                    || (bool) data_get($package->meta, 'is_main', false);

                if ($isRootInstallHint && $installerManaged) {
                    $installPath = $expectedInstallPath;
                    $normalizedInstallPath = $normalizedExpectedPath;
                    $isModuleInstall = $this->isInstallPathWithinModules($installPath);
                } else {
                    throw new RuntimeException(__('The marketplace returned an unexpected install path.'));
                }
            }
        }

        $tempZipPath = storage_path('app/marketplace/'.Str::random(16).'.zip');
        File::ensureDirectoryExists(dirname($tempZipPath));

        try {
            $downloadResponse = Http::withOptions(['verify' => false])
                ->timeout(120)
                ->get($downloadUrl);

            if (! $downloadResponse->ok()) {
                throw new RuntimeException(__('Download failed.'));
            }

            File::put($tempZipPath, $downloadResponse->body());

            $zip = new ZipArchive();
            if ($zip->open($tempZipPath) !== true) {
                throw new RuntimeException(__('Unable to open the ZIP file.'));
            }

            if ($isModuleInstall) {
                $moduleNames = $this->resolveZipModuleDirectories($zip);
                $resolvedModuleName = $this->resolvePreferredZipModuleName($moduleNames, (string) $package->module_name);

                if ($moduleNames === [] || $resolvedModuleName === null) {
                    throw new RuntimeException(__('The updated package is missing module.json.'));
                }

                $extractRoot = dirname($installPath);

                foreach ($moduleNames as $zipModuleName) {
                    $zipModuleDirectory = $extractRoot.'/'.$zipModuleName;

                    if (File::isDirectory($zipModuleDirectory)) {
                        File::deleteDirectory($zipModuleDirectory);
                    }
                }

                File::ensureDirectoryExists($extractRoot);
                $zip->extractTo($extractRoot);
                $zip->close();

                $installPath = $extractRoot.'/'.$resolvedModuleName;
            } else {
                $tempExtractRoot = storage_path('app/marketplace/extract-'.Str::random(16));
                File::ensureDirectoryExists($tempExtractRoot);
                try {
                    if (! $zip->extractTo($tempExtractRoot)) {
                        $zip->close();
                        throw new RuntimeException(__('Unable to extract the ZIP file.'));
                    }

                    $zip->close();

                    $sourceRoot = $this->resolveZipContentRoot($tempExtractRoot);
                    $this->copyDirectoryContents($sourceRoot, $installPath, ['.env', 'storage', 'bootstrap/cache']);
                } finally {
                    File::deleteDirectory($tempExtractRoot);
                }
            }
        } finally {
            File::delete($tempZipPath);
        }

        $moduleJson = [];
        $bundleProviders = [];
        $moduleJsonPath = rtrim($installPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'module.json';

        if ($isModuleInstall) {
            if (! File::exists($moduleJsonPath)) {
                throw new RuntimeException(__('The updated package is missing module.json.'));
            }

            $moduleJson = json_decode((string) File::get($moduleJsonPath), true);
            if (! is_array($moduleJson)) {
                throw new RuntimeException(__('The updated module.json is invalid.'));
            }

            $bundleModules = collect((array) data_get($package->meta, 'bundle_modules', []))
                ->filter(fn ($module) => is_string($module) && trim($module) !== '')
                ->values()
                ->all();
            $bundleModules = $bundleModules !== [] ? $bundleModules : [(string) ($package->module_name ?? '')];
            $bundleProviders = collect($bundleModules)
                ->map(fn (string $bundleModuleName) => dirname($installPath).'/'.$bundleModuleName.'/module.json')
                ->filter(fn (string $bundleModuleJsonPath) => File::exists($bundleModuleJsonPath))
                ->flatMap(function (string $bundleModuleJsonPath): array {
                    $bundleModuleJson = json_decode((string) File::get($bundleModuleJsonPath), true);

                    return is_array($bundleModuleJson)
                        ? array_values((array) ($bundleModuleJson['providers'] ?? []))
                        : [];
                })
                ->filter(fn ($provider) => is_string($provider) && trim($provider) !== '')
                ->unique()
                ->values()
                ->all();
        }
        $oldProviders = array_values((array) $package->providers);
        $newProviders = $bundleProviders;

        $meta = array_merge((array) $package->meta, array_filter([
            'alias' => $moduleJson['alias'] ?? null,
            'keywords' => $moduleJson['keywords'] ?? [],
            'marketplace_version' => $latestVersion ?: $this->resolveVersion($installPath),
            'product_id' => data_get($payload, 'product_id', $productId),
            'slug' => data_get($payload, 'slug', $package->product_slug ?: data_get($package->meta, 'slug')),
            'license' => data_get($payload, 'license', $package->license_type ?: data_get($package->meta, 'license')),
            'domain' => $domain,
            'product_name' => trim((string) data_get($payload, 'name', data_get($package->meta, 'product_name', ''))) ?: null,
            'folder_install' => $relativeInstallPath !== '' ? $relativeInstallPath : data_get($package->meta, 'folder_install'),
        ], fn ($value) => $value !== null && $value !== ''));

        $resolvedProductTitle = $this->resolveMarketplaceProductTitle($payload, $moduleJson, (string) $package->module_name, (array) ($package->meta ?? []));
        $resolvedProductTitle = $this->preferExistingPackageTitle($resolvedProductTitle, (string) ($package->title ?? ''), (string) ($package->module_name ?? ''));

        $package->forceFill([
            'module_name' => (string) ($moduleJson['module_name'] ?? data_get($payload, 'module_name', $package->module_name)),
            'title' => $resolvedProductTitle,
            'description' => (string) ($moduleJson['description'] ?? $package->description ?? ''),
            'version' => $latestVersion ?: $this->resolveVersion($installPath),
            'product_id' => data_get($payload, 'product_id', $productId),
            'purchase_code' => $purchaseCode,
            'product_slug' => data_get($payload, 'slug', $package->product_slug),
            'license_type' => data_get($payload, 'license', $package->license_type),
            'licensed_domain' => $domain,
            'providers' => $newProviders,
            'meta' => array_merge($meta, ['product_name' => $resolvedProductTitle]),
            'last_synced_at' => now(),
        ])->save();

        if ($package->is_active) {
            $this->replaceActiveProviders($oldProviders, $newProviders);
        }

        if ($isModuleInstall) {
            $this->runModuleMigrationsForModule((string) $package->module_name);
        } else {
            Artisan::call('migrate', [
                '--force' => true,
                '--no-interaction' => true,
            ]);
        }
        Artisan::call('optimize:clear');

        return $package->fresh() ?? $package;
    }

    public function activate(MarketplacePackage $package): void
    {
        $providers = collect((array) $package->providers)
            ->filter(fn ($provider) => is_string($provider) && trim($provider) !== '')
            ->values()
            ->all();

        if ($providers === []) {
            throw new RuntimeException(__('No valid service providers were found for this package.'));
        }

        $registered = $this->registeredMarketplaceProviders();
        $registered = array_values(array_unique(array_merge($registered, $providers)));
        $this->writeMarketplaceProviders($registered);

        $package->forceFill([
            'is_active' => true,
            'last_synced_at' => now(),
        ])->save();

        $this->runModuleMigrations($package);
        Artisan::call('optimize:clear');
    }

    public function deactivate(MarketplacePackage $package): void
    {
        $providers = collect((array) $package->providers)
            ->filter(fn ($provider) => is_string($provider) && trim($provider) !== '')
            ->values()
            ->all();

        $registered = collect($this->registeredMarketplaceProviders())
            ->reject(fn ($provider) => in_array($provider, $providers, true))
            ->values()
            ->all();

        $this->writeMarketplaceProviders($registered);

        $package->forceFill([
            'is_active' => false,
            'last_synced_at' => now(),
        ])->save();

        Artisan::call('optimize:clear');
    }

    public function uninstall(MarketplacePackage $package): void
    {
        if ($package->is_active) {
            $this->deactivate($package);
        }

        $installPath = trim((string) $package->install_path);
        $normalizedModulesRoot = str_replace('\\', '/', base_path('modules'));
        $normalizedInstallPath = str_replace('\\', '/', $installPath);

        if ($installPath !== '' && File::isDirectory($installPath) && str_starts_with($normalizedInstallPath, $normalizedModulesRoot)) {
            File::deleteDirectory($installPath);
        }

        $package->delete();
    }

    protected function registeredMarketplaceProviders(): array
    {
        $path = $this->providersPath();

        if (! File::exists($path)) {
            return [];
        }

        $providers = require $path;

        return is_array($providers)
            ? collect($providers)
                ->filter(fn ($provider) => is_string($provider) && trim($provider) !== '')
                ->values()
                ->all()
            : [];
    }

    protected function registeredStaticProviders(array $marketplaceProviders = []): array
    {
        $providers = require base_path('bootstrap/providers.php');

        return is_array($providers)
            ? collect($providers)
                ->filter(fn ($provider) => is_string($provider) && trim($provider) !== '')
                ->reject(fn ($provider) => in_array($provider, $marketplaceProviders, true))
                ->values()
                ->all()
            : [];
    }

    protected function writeMarketplaceProviders(array $providers): void
    {
        $content = "<?php\n\nreturn [\n";

        foreach ($providers as $provider) {
            $content .= "    ".str_replace("'", "\\'", $provider)."::class,\n";
        }

        $content .= "];\n";

        File::put($this->providersPath(), $content);
    }

    protected function replaceActiveProviders(array $oldProviders, array $newProviders): void
    {
        $registered = collect($this->registeredMarketplaceProviders())
            ->reject(fn ($provider) => in_array($provider, $oldProviders, true))
            ->merge($newProviders)
            ->filter(fn ($provider) => is_string($provider) && trim($provider) !== '')
            ->unique()
            ->values()
            ->all();

        $this->writeMarketplaceProviders($registered);
    }

    protected function runModuleMigrations(MarketplacePackage $package): void
    {
        $moduleName = trim((string) $package->module_name);
        if ($moduleName === '') {
            return;
        }

        $this->runModuleMigrationsForModule($moduleName);
    }

    protected function runModuleMigrationsForModule(string $moduleName): void
    {
        $moduleName = trim($moduleName);

        if ($moduleName === '') {
            return;
        }

        $migrationPath = base_path('modules/'.$moduleName.'/database/migrations');

        if (! File::isDirectory($migrationPath)) {
            return;
        }

        foreach (collect(File::files($migrationPath))->sortBy->getFilename() as $file) {
            $relative = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
            $relative = str_replace('\\', '/', $relative);

            Artisan::call('migrate', [
                '--path' => $relative,
                '--force' => true,
            ]);
        }
    }

    protected function resolveVersion(string $moduleDirectory): ?string
    {
        $moduleJsonPath = $moduleDirectory.'/module.json';

        if (File::exists($moduleJsonPath)) {
            $json = json_decode((string) File::get($moduleJsonPath), true);

            $version = Arr::get($json, 'version')
                ?? Arr::get($json, 'meta.version')
                ?? null;

            if (is_string($version) && trim($version) !== '') {
                return trim($version);
            }
        }

        $configPath = $moduleDirectory.'/config/config.php';
        if (File::exists($configPath)) {
            $config = require $configPath;
            $version = Arr::get($config, 'version');

            if (is_string($version) && trim($version) !== '') {
                return trim($version);
            }
        }

        return null;
    }

    protected function resolveMarketplaceInstallDirectory(string $relativeInstallPath, string $moduleName): string
    {
        $normalizedRelativePath = trim(str_replace('\\', '/', $relativeInstallPath));
        $normalizedRelativePath = preg_replace('#/+#', '/', $normalizedRelativePath) ?: '';
        $normalizedRelativePath = rtrim($normalizedRelativePath, '/');
        $normalizedModuleName = trim(str_replace('\\', '/', $moduleName), '/');
        $isRootInstall = in_array($normalizedRelativePath, ['', '.', './'], true);

        if ($isRootInstall) {
            return base_path();
        }

        $lastSegment = basename($normalizedRelativePath);
        $looksLikeModulesRoot = $normalizedRelativePath === ''
            || str_ends_with($normalizedRelativePath, '/modules')
            || $lastSegment === 'modules'
            || $normalizedRelativePath === '..'
            || $normalizedRelativePath === '../modules';

        if ($looksLikeModulesRoot) {
            if ($normalizedModuleName === '') {
                return base_path('modules');
            }

            $installDirectory = base_path('modules/'.$normalizedModuleName);
        } else {
            $installDirectory = base_path(ltrim($normalizedRelativePath, '/'));
        }

        $normalizedBaseRoot = rtrim(str_replace('\\', '/', base_path()), '/').'/';
        $normalizedInstallDirectory = rtrim(str_replace('\\', '/', $installDirectory), '/').'/';

        if (! str_starts_with($normalizedInstallDirectory, $normalizedBaseRoot)) {
            throw new RuntimeException(__('The install path returned by the marketplace is invalid.'));
        }

        return $installDirectory;
    }

    protected function resolveMarketplaceModuleExtractRoot(string $installPath): string
    {
        $normalizedInstallPath = rtrim(str_replace('\\', '/', trim($installPath)), '/');
        $normalizedModulesRoot = rtrim(str_replace('\\', '/', base_path('modules')), '/');

        if ($normalizedInstallPath === $normalizedModulesRoot) {
            return base_path('modules');
        }

        return dirname($installPath);
    }

    protected function resolveMarketplaceExtractPath(
        ZipArchive $zip,
        string $moduleName,
        string $moduleDirectory,
        string $defaultExtractRoot
    ): string {
        $moduleName = trim(str_replace('\\', '/', $moduleName), '/');
        $firstEntry = trim((string) $zip->getNameIndex(0));
        $zipRoot = trim((string) explode('/', str_replace('\\', '/', $firstEntry))[0]);

        if ($zipRoot !== '' && strcasecmp($zipRoot, $moduleName) === 0) {
            return $defaultExtractRoot;
        }

        return $moduleDirectory;
    }

    protected function resolveZipModuleDirectories(ZipArchive $zip): array
    {
        $moduleNames = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entryName = str_replace('\\', '/', (string) $zip->getNameIndex($index));
            $entryName = ltrim($entryName, '/');

            if ($entryName === '' || ! str_ends_with($entryName, '/module.json')) {
                continue;
            }

            $moduleName = trim((string) Str::before($entryName, '/module.json'));

            if ($moduleName === '' || str_contains($moduleName, '/')) {
                continue;
            }

            $moduleJson = $this->readZipModuleJson($zip, $moduleName);

            if (! is_array($moduleJson) || blank($moduleJson['name'] ?? null)) {
                continue;
            }

            $moduleNames[] = $moduleName;
        }

        return array_values(array_unique($moduleNames));
    }

    protected function readZipModuleJson(ZipArchive $zip, string $moduleName): array
    {
        $moduleJsonIndex = $zip->locateName($moduleName.'/module.json');

        if ($moduleJsonIndex === false) {
            return [];
        }

        $moduleJson = json_decode((string) $zip->getFromIndex($moduleJsonIndex), true);

        return is_array($moduleJson) ? $moduleJson : [];
    }

    protected function resolvePreferredZipModuleName(array $moduleNames, string $preferredModuleName): ?string
    {
        $preferredModuleName = trim($preferredModuleName);

        if ($moduleNames === []) {
            return null;
        }

        if ($preferredModuleName === '') {
            return count($moduleNames) === 1 ? $moduleNames[0] : null;
        }

        foreach ($moduleNames as $moduleName) {
            if ($moduleName === $preferredModuleName) {
                return $moduleName;
            }
        }

        foreach ($moduleNames as $moduleName) {
            if (strcasecmp($moduleName, $preferredModuleName) === 0) {
                return $moduleName;
            }
        }

        $normalizedPreferred = $this->normalizeModuleNameForMatching($preferredModuleName);

        foreach ($moduleNames as $moduleName) {
            if ($this->normalizeModuleNameForMatching($moduleName) === $normalizedPreferred) {
                return $moduleName;
            }
        }

        return count($moduleNames) === 1 ? $moduleNames[0] : null;
    }

    protected function normalizeModuleNameForMatching(string $moduleName): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9]+/i', '', $moduleName));
    }

    protected function resolveMarketplaceProductTitle(
        array $payload,
        array $moduleJson,
        string $fallbackModuleName,
        array $existingMeta = []
    ): string {
        $payloadName = trim((string) data_get($payload, 'name', ''));

        if ($this->isPlaceholderPackageTitle($payloadName)) {
            $payloadName = '';
        }

        if ($payloadName !== '') {
            return $payloadName;
        }

        $metaProductName = trim((string) data_get($existingMeta, 'product_name', ''));

        if ($this->isPlaceholderPackageTitle($metaProductName)) {
            $metaProductName = '';
        }

        if ($metaProductName !== '') {
            return $metaProductName;
        }

        $slug = trim((string) data_get($payload, 'slug', data_get($existingMeta, 'slug', '')));
        $productId = (int) data_get($payload, 'product_id', data_get($existingMeta, 'product_id', 0));

        try {
            $catalog = app(ShopProductCatalogService::class);

            if ($slug !== '') {
                $product = $catalog->findBySlug($slug);

                if (is_array($product) && trim((string) ($product['name'] ?? '')) !== '') {
                    return trim((string) $product['name']);
                }
            }

            if ($productId > 0) {
                $product = $catalog->catalog()->first(function (array $product) use ($productId): bool {
                    return (int) ($product['product_id'] ?? $product['id'] ?? 0) === $productId;
                });

                if (is_array($product) && trim((string) ($product['name'] ?? '')) !== '') {
                    return trim((string) $product['name']);
                }
            }
        } catch (\Throwable) {
        }

        $moduleJsonName = trim((string) ($moduleJson['name'] ?? ''));

        if ($moduleJsonName !== '') {
            return $moduleJsonName;
        }

        return trim($fallbackModuleName) !== '' ? trim($fallbackModuleName) : __('Unknown package');
    }

    protected function isPlaceholderPackageTitle(string $title): bool
    {
        $normalized = strtolower(trim($title));

        return in_array($normalized, [
            '',
            'unknown package',
            strtolower((string) __('Unknown package')),
        ], true);
    }

    protected function preferExistingPackageTitle(string $resolvedTitle, string $existingTitle, string $fallbackModuleName = ''): string
    {
        $resolvedTitle = trim($resolvedTitle);
        $existingTitle = trim($existingTitle);
        $fallbackModuleName = trim($fallbackModuleName);

        if (! $this->isPlaceholderPackageTitle($resolvedTitle) && $resolvedTitle !== '' && $resolvedTitle !== $fallbackModuleName) {
            return $resolvedTitle;
        }

        if (! $this->isPlaceholderPackageTitle($existingTitle) && $existingTitle !== '') {
            return $existingTitle;
        }

        if (! $this->isPlaceholderPackageTitle($resolvedTitle) && $resolvedTitle !== '') {
            return $resolvedTitle;
        }

        return $fallbackModuleName !== '' ? $fallbackModuleName : __('Unknown package');
    }

    protected function extractZipDirectory(ZipArchive $zip, string $moduleName, string $extractRoot): void
    {
        $entries = [];
        $prefix = trim(str_replace('\\', '/', $moduleName), '/').'/';

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entryName = str_replace('\\', '/', (string) $zip->getNameIndex($index));

            if (str_starts_with($entryName, $prefix)) {
                $entries[] = $entryName;
            }
        }

        if ($entries === [] || ! $zip->extractTo($extractRoot, $entries)) {
            throw new RuntimeException(__('Unable to extract the selected module from the ZIP file.'));
        }
    }

    protected function resolveZipContentRoot(string $extractRoot): string
    {
        $entries = collect(File::directories($extractRoot))
            ->merge(collect(File::files($extractRoot))->map(fn ($file) => $file->getPathname()))
            ->values();

        if ($entries->count() !== 1) {
            return $extractRoot;
        }

        $firstEntry = (string) $entries->first();

        return File::isDirectory($firstEntry) ? $firstEntry : $extractRoot;
    }

    protected function isInstallPathWithinModules(string $installPath): bool
    {
        $normalizedModulesRoot = rtrim(str_replace('\\', '/', base_path('modules')), '/').'/';
        $normalizedInstallPath = rtrim(str_replace('\\', '/', trim($installPath)), '/').'/';

        return $installPath !== '' && str_starts_with($normalizedInstallPath, $normalizedModulesRoot);
    }

    protected function resolveStoredInstallPath(MarketplacePackage $package): string
    {
        $installPath = trim((string) $package->install_path);
        $metaFolderInstall = trim((string) data_get($package->meta, 'folder_install', ''));
        $payloadFolderInstall = trim((string) data_get($package->meta, 'payload.install_path', ''));
        $moduleName = trim((string) ($package->module_name ?? ''));
        $basePathNormalized = rtrim(str_replace('\\', '/', base_path()), '/');
        $modulesRootNormalized = $basePathNormalized.'/modules';

        if ($installPath !== '') {
            $normalizedInstallPath = rtrim(str_replace('\\', '/', $installPath), '/');

            if (str_starts_with($normalizedInstallPath, $basePathNormalized.'/')) {
                return str_replace('/', DIRECTORY_SEPARATOR, $normalizedInstallPath);
            }

            if ($moduleName !== '' && preg_match('#(?:^|/)modules/'.preg_quote($moduleName, '#').'$#i', $normalizedInstallPath)) {
                return base_path('modules/'.$moduleName);
            }
        }

        $relativeHint = $metaFolderInstall !== '' ? $metaFolderInstall : $payloadFolderInstall;
        $normalizedPath = trim(str_replace('\\', '/', $relativeHint !== '' ? $relativeHint : $installPath));

        if ($normalizedPath === '' || in_array($normalizedPath, ['.', './'], true)) {
            return base_path();
        }

        if ($moduleName !== '' && preg_match('#^(?:\.\./)?modules/?$#i', $normalizedPath)) {
            return base_path('modules/'.$moduleName);
        }

        if (preg_match('#^[a-zA-Z]:/#', $normalizedPath) || str_starts_with($normalizedPath, '/')) {
            if (str_starts_with(rtrim($normalizedPath, '/'), $modulesRootNormalized) || str_starts_with(rtrim($normalizedPath, '/'), $basePathNormalized)) {
                return str_replace('/', DIRECTORY_SEPARATOR, $normalizedPath);
            }

            if ($moduleName !== '' && preg_match('#(?:^|/)modules/'.preg_quote($moduleName, '#').'$#i', $normalizedPath)) {
                return base_path('modules/'.$moduleName);
            }

            return str_replace('/', DIRECTORY_SEPARATOR, $normalizedPath);
        }

        return base_path(ltrim($normalizedPath, '/'));
    }

    protected function copyDirectoryContents(string $sourceRoot, string $destinationRoot, array $excluded = []): void
    {
        File::ensureDirectoryExists($destinationRoot);

        foreach (File::allFiles($sourceRoot, true) as $file) {
            $sourcePath = $file->getPathname();
            $relativePath = ltrim(str_replace('\\', '/', Str::after($sourcePath, rtrim($sourceRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)), '/');

            if ($relativePath === '' || $this->pathMatchesExcluded($relativePath, $excluded)) {
                continue;
            }

            $destinationPath = $destinationRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            File::ensureDirectoryExists(dirname($destinationPath));
            File::copy($sourcePath, $destinationPath);
        }
    }

    protected function pathMatchesExcluded(string $relativePath, array $excluded): bool
    {
        $normalizedPath = trim(str_replace('\\', '/', $relativePath), '/');

        foreach ($excluded as $excludedPath) {
            $normalizedExcluded = trim(str_replace('\\', '/', (string) $excludedPath), '/');

            if ($normalizedExcluded === '') {
                continue;
            }

            if ($normalizedPath === $normalizedExcluded || str_starts_with($normalizedPath, $normalizedExcluded.'/')) {
                return true;
            }
        }

        return false;
    }
}
