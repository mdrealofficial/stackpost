<?php

namespace Modules\AdminMarketplace\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\AdminMarketplace\Models\MarketplacePackage;
use RuntimeException;
use Throwable;

class ShopProductCatalogService
{
    protected const STACKPOSTS_BASE_URL = '';

    protected ?array $shopConfig = null;

    protected string $cacheVersion = 'v9';

    protected array $resolvedLicensePrices = [];

    public function configured(): bool
    {
        return filled($this->marketplaceApiBase());
    }

    public function storefrontApiBase(): ?string
    {
        $base = self::STACKPOSTS_BASE_URL !== '' ? self::STACKPOSTS_BASE_URL : env('MARKETPLACE_BASE_URL', '');
        return $base ? rtrim($base, '/').'/api/storefront' : null;
    }

    public function marketplaceApiBase(): ?string
    {
        $base = self::STACKPOSTS_BASE_URL !== '' ? self::STACKPOSTS_BASE_URL : env('MARKETPLACE_BASE_URL', '');
        return $base ? rtrim($base, '/').'/api/marketplace' : null;
    }

    public function appUrl(): ?string
    {
        $base = self::STACKPOSTS_BASE_URL !== '' ? self::STACKPOSTS_BASE_URL : env('MARKETPLACE_BASE_URL', '');
        return $base ?: null;
    }

    public function categories(): Collection
    {
        if (! $this->configured()) {
            return collect();
        }

        $cached = Cache::remember($this->cacheKey('categories'), now()->addMinutes(5), function (): array {
            try {
                return $this->catalog()
                    ->pluck('category_name')
                    ->filter(fn ($name) => is_string($name) && trim($name) !== '')
                    ->unique()
                    ->sort()
                    ->values()
                    ->map(fn (string $name, int $index): array => [
                        'id' => $index + 1,
                        'name' => $name,
                    ])
                    ->all();
            } catch (Throwable) {
                return [];
            }
        });

        return collect(is_array($cached) ? $cached : []);
    }

    public function catalog(array $filters = []): Collection
    {
        if (! $this->configured()) {
            return collect();
        }

        $cacheKey = $this->cacheKey('catalog:'.md5(json_encode($filters)));

        $cached = Cache::remember($cacheKey, now()->addMinutes(3), function () use ($filters): array {
            try {
                $products = $this->fetchMarketplaceProducts((string) ($filters['q'] ?? ''));

                if (($filters['featured'] ?? 'all') === 'featured') {
                    $products = $products->where('is_featured', true);
                }

                if (($filters['type'] ?? 'all') === 'main') {
                    $products = $products->where('is_main', true);
                } elseif (($filters['type'] ?? 'all') === 'addon') {
                    $products = $products->where('is_main', false)->where('is_combo', false);
                } elseif (($filters['type'] ?? 'all') === 'combo') {
                    $products = $products->where('is_combo', true);
                }

                if (filled($filters['category'] ?? null) && $filters['category'] !== 'all') {
                    $categories = $this->categories()->values();
                    $selectedCategory = data_get($categories->get(((int) $filters['category']) - 1), 'name');
                    if (is_string($selectedCategory) && $selectedCategory !== '') {
                        $products = $products->where('category_name', $selectedCategory);
                    }
                }

                return $products
                    ->map(fn (array $product) => $this->normalizeProduct($product))
                    ->values()
                    ->all();
            } catch (Throwable) {
                return [];
            }
        });

        return collect(is_array($cached) ? $cached : []);
    }

    public function findBySlug(string $slug): ?array
    {
        if (! $this->configured()) {
            return null;
        }

        try {
            $detail = $this->fetchMarketplaceDetail($slug);

            if (is_array($detail) && ! empty($detail['product']) && is_array($detail['product'])) {
                $product = $this->normalizeProduct($detail['product'], true);
                $product['faqs'] = is_array($detail['faqs'] ?? null) ? $detail['faqs'] : [];
                $product['support'] = is_array($detail['support'] ?? null) ? [
                    'info' => $this->normalizeHtmlContent((string) data_get($detail, 'support.info', '')),
                    'link' => $this->externalUrl(data_get($detail, 'support.link')),
                ] : [];
                $product['changelog'] = is_array($detail['changelog'] ?? null) ? $detail['changelog'] : [];

                return $product;
            }

            $product = $this->fetchMarketplaceProducts()
                ->first(fn (array $product): bool => (string) ($product['slug'] ?? '') === $slug);
        } catch (Throwable) {
            return null;
        }

        if (! $product) {
            return null;
        }

        $normalizedProduct = $this->normalizeProduct((array) $product, true);
        $normalizedProduct['faqs'] = [];
        $normalizedProduct['support'] = [];
        $normalizedProduct['changelog'] = [];

        return $normalizedProduct;
    }

    public function summary(Collection $products): array
    {
        return [
            'total' => $products->count(),
            'featured' => $products->where('is_featured', true)->count(),
            'main' => $products->where('is_main', true)->count(),
            'addons' => $products->where('is_main', false)->where('is_combo', false)->count(),
            'categories' => $products->pluck('category_name')->filter()->unique()->count(),
        ];
    }

    public function marketplaceUpdateNotice(MarketplacePackageService $packages): array
    {
        return Cache::remember(
            'admin-dashboard:marketplace-update-notice',
            now()->addMinutes(30),
            function () use ($packages): array {
                if (! $this->configured()) {
                    return [
                        'configured' => false,
                        'count' => 0,
                        'items' => [],
                    ];
                }

                $installedPackages = $packages->discover()
                    ->where('source_type', 'purchase')
                    ->values();

                if ($installedPackages->isEmpty()) {
                    return [
                        'configured' => true,
                        'count' => 0,
                        'items' => [],
                    ];
                }

                $catalogProducts = $this->catalog();
                $updates = [];

                foreach ($installedPackages as $package) {
                    $product = $this->matchPackageToProduct($catalogProducts, $package);

                    if (! $product) {
                        continue;
                    }

                    $latestVersion = trim((string) ($product['version'] ?? ''));
                    $installedVersion = trim((string) ($package->version ?? ''));

                    if ($latestVersion === '' || $installedVersion === '') {
                        continue;
                    }

                    if (! version_compare($latestVersion, $installedVersion, '>')) {
                        continue;
                    }

                    $updates[] = [
                        'package_id_secure' => $package->id_secure,
                        'title' => trim((string) ($product['name'] ?? '')) !== ''
                            ? (string) $product['name']
                            : $package->title,
                        'installed_version' => $installedVersion,
                        'latest_version' => $latestVersion,
                    ];
                }

                return [
                    'configured' => true,
                    'count' => count($updates),
                    'items' => array_slice($updates, 0, 3),
                ];
            }
        );
    }

    protected function normalizeProduct(array $product, bool $withContent = false): array
    {
        $thumbnail = trim((string) Arr::get($product, 'thumbnail', ''));
        $previewImages = Arr::get($product, 'preview_images', Arr::get($product, 'preview', []));

        if (is_string($previewImages) && $previewImages !== '') {
            $decoded = json_decode($previewImages, true);
            $previewImages = is_array($decoded) ? $decoded : [$previewImages];
        }

        $productId = (int) (Arr::get($product, 'product_id') ?: Arr::get($product, 'id'));
        $name = (string) Arr::get($product, 'name');
        $categoryName = $this->inferCategoryName($name, $product);
        $isCombo = $this->inferIsCombo($name, $product);
        $isMain = $this->inferIsMain($name, $product, $isCombo);
        $isEnvato = $this->inferIsEnvato($product);
        $licensePrices = $productId > 0 ? $this->resolveLicensePrices($productId, $product) : [
            'regular' => (float) Arr::get($product, 'price', 0),
            'extended' => (float) Arr::get($product, 'price_extended_license', 0),
        ];

        return [
            'id' => $productId,
            'db_id' => (int) Arr::get($product, 'id'),
            'product_id' => $productId,
            'slug' => (string) Arr::get($product, 'slug'),
            'name' => $name,
            'description' => trim((string) Arr::get($product, 'description', '')),
            'content' => $withContent
                ? $this->normalizeHtmlContent((string) Arr::get($product, 'content', Arr::get($product, 'description', '')))
                : null,
            'thumbnail' => $thumbnail,
            'thumbnail_url' => $this->assetUrl($thumbnail),
            'preview_images' => collect(is_array($previewImages) ? $previewImages : [])
                ->filter(fn ($path) => is_string($path) && trim($path) !== '')
                ->map(fn ($path) => $this->assetUrl((string) $path))
                ->values()
                ->all(),
            'price_regular_license' => (float) ($licensePrices['regular'] ?? Arr::get($product, 'price_regular_license', Arr::get($product, 'price', 0))),
            'price_extended_license' => (float) ($licensePrices['extended'] ?? Arr::get($product, 'price_extended_license', 0)),
            'price_renew_support' => (float) Arr::get($product, 'price_renew_support', 0),
            'version' => Arr::get($product, 'version') ? (string) Arr::get($product, 'version') : null,
            'module_name' => Arr::get($product, 'module_name') ? (string) Arr::get($product, 'module_name') : null,
            'folder_install' => Arr::get($product, 'folder_install') ? (string) Arr::get($product, 'folder_install') : null,
            'category_name' => $categoryName,
            'demo_url' => $this->externalUrl(Arr::get($product, 'demo_url')),
            'buy_url' => $this->externalUrl(Arr::get($product, 'source_url'))
                ?? $this->externalUrl(Arr::get($product, 'envato_url'))
                ?? $this->externalUrl(Arr::get($product, 'product_url')),
            'product_url' => $this->externalUrl(Arr::get($product, 'product_url')),
            'author' => Arr::get($product, 'author') ? (string) Arr::get($product, 'author') : null,
            'sales' => (int) Arr::get($product, 'sales', 0),
            'is_envato' => $isEnvato,
            'status' => (int) Arr::get($product, 'status', 1),
            'is_featured' => (bool) Arr::get($product, 'is_featured', false),
            'is_main' => $isMain,
            'is_combo' => $isCombo,
            'changed_at' => $this->timestampToDateTime(Arr::get($product, 'changed')),
            'created_at' => $this->timestampToDateTime(Arr::get($product, 'created')),
        ];
    }

    protected function fetchMarketplaceProducts(string $search = ''): Collection
    {
        $cacheKey = $this->cacheKey('remote-products:'.md5($search));

        $cached = Cache::remember($cacheKey, now()->addMinutes(3), function () use ($search): array {
            $apiBase = $this->marketplaceApiBase();

            if (! $apiBase) {
                return [];
            }

            $response = Http::withOptions(['verify' => false])
                ->acceptJson()
                ->timeout(30)
                ->get(rtrim($apiBase, '/').'/products', array_filter([
                    'page' => 1,
                    'per_page' => 100,
                    'search' => trim($search) !== '' ? trim($search) : null,
                ]));

            $data = $response->json('data');
            $products = is_array($data) ? $data : [];
            $versionMap = $this->fetchMarketplaceVersionMap();

            return collect($products)->map(function (array $item) use ($versionMap): array {
                $productId = (int) ($item['product_id'] ?? $item['id'] ?? 0);

                if ($productId > 0 && isset($versionMap[$productId])) {
                    $item['version'] = $versionMap[$productId];
                }

                return $item;
            })->all();
        });

        return collect(is_array($cached) ? $cached : []);
    }

    protected function fetchMarketplaceVersionMap(): array
    {
        $cacheKey = $this->cacheKey('remote-all-products:versions');

        $cached = Cache::remember($cacheKey, now()->addMinutes(3), function (): array {
            $apiBase = $this->marketplaceApiBase();

            if (! $apiBase) {
                return [];
            }

            $response = Http::withOptions(['verify' => false])
                ->acceptJson()
                ->timeout(30)
                ->get(rtrim($apiBase, '/').'/all-products');

            $data = $response->json('data');

            if (! is_array($data)) {
                return [];
            }

            return collect($data)
                ->filter(fn ($item) => is_array($item))
                ->mapWithKeys(function (array $item): array {
                    $productId = (int) ($item['product_id'] ?? $item['id'] ?? 0);
                    $version = trim((string) ($item['version'] ?? ''));

                    return $productId > 0 && $version !== '' ? [$productId => $version] : [];
                })
                ->all();
        });

        return is_array($cached) ? $cached : [];
    }

    protected function resolveLicensePrices(int $productId, array $product = []): array
    {
        if (isset($this->resolvedLicensePrices[$productId])) {
            return $this->resolvedLicensePrices[$productId];
        }

        $cacheKey = $this->cacheKey('storefront-license-prices:'.$productId);
        $cached = Cache::remember($cacheKey, now()->addHours(12), function () use ($productId, $product): array {
            $regular = (float) Arr::get($product, 'price_regular_license', Arr::get($product, 'price', 0));
            $extended = (float) Arr::get($product, 'price_extended_license', 0);

            $storefrontExtended = $this->probeStorefrontLicensePrice($productId, 'extended');

            if ($storefrontExtended !== null && $storefrontExtended > 0) {
                $extended = $storefrontExtended;
            }

            return [
                'regular' => $regular,
                'extended' => $extended,
            ];
        });

        return $this->resolvedLicensePrices[$productId] = is_array($cached) ? $cached : [
            'regular' => (float) Arr::get($product, 'price_regular_license', Arr::get($product, 'price', 0)),
            'extended' => (float) Arr::get($product, 'price_extended_license', 0),
        ];
    }

    protected function probeStorefrontLicensePrice(int $productId, string $licenseType): ?float
    {
        $apiBase = $this->storefrontApiBase();

        if (! $apiBase || $productId < 1 || ! in_array($licenseType, ['regular', 'extended'], true)) {
            return null;
        }

        $cartToken = null;

        try {
            $response = Http::withOptions(['verify' => false])
                ->acceptJson()
                ->timeout(20)
                ->post(rtrim($apiBase, '/').'/cart/items', [
                    'product_id' => $productId,
                    'license_type' => $licenseType,
                    'quantity' => 1,
                ]);

            if (! $response->ok()) {
                return null;
            }

            $cartToken = trim((string) ($response->header('X-Cart-Token') ?: data_get($response->json(), 'cart_token', '')));
            $price = data_get($response->json(), 'data.items.0.price');

            return is_numeric($price) ? (float) $price : null;
        } catch (Throwable) {
            return null;
        } finally {
            if ($cartToken) {
                try {
                    Http::withOptions(['verify' => false])
                        ->acceptJson()
                        ->timeout(15)
                        ->withHeaders(['X-Cart-Token' => $cartToken])
                        ->delete(rtrim($apiBase, '/').'/cart');
                } catch (Throwable) {
                }
            }
        }
    }

    protected function fetchMarketplaceDetail(string $slug): ?array
    {
        $cacheKey = $this->cacheKey('remote-product-detail:'.md5($slug));

        $cached = Cache::remember($cacheKey, now()->addMinutes(3), function () use ($slug): ?array {
            $apiBase = $this->marketplaceApiBase();

            if (! $apiBase) {
                return null;
            }

            $response = Http::withOptions(['verify' => false])
                ->acceptJson()
                ->timeout(30)
                ->get(rtrim($apiBase, '/').'/product-detail/'.rawurlencode($slug));

            if (! $response->ok()) {
                return null;
            }

            $payload = $response->json();

            return is_array($payload) ? $payload : null;
        });

        return is_array($cached) ? $cached : null;
    }

    protected function inferCategoryName(string $name, array $product): string
    {
        if (Arr::get($product, 'category_name')) {
            return (string) Arr::get($product, 'category_name');
        }

        $normalizedName = Str::lower($name);

        if (str_contains($normalizedName, 'service')) {
            return 'Services';
        }

        if ($this->inferIsCombo($name, $product)) {
            return 'Combo';
        }

        if ($this->inferIsMain($name, $product)) {
            return 'Main scripts';
        }

        return 'Modules';
    }

    protected function inferIsMain(string $name, array $product, ?bool $isCombo = null): bool
    {
        if (Arr::has($product, 'is_main')) {
            return (bool) Arr::get($product, 'is_main');
        }

        if ($isCombo ?? $this->inferIsCombo($name, $product)) {
            return false;
        }

        $normalizedName = Str::lower($name);

        return str_contains($normalizedName, 'main script')
            || str_contains($normalizedName, 'saas platform');
    }

    protected function inferIsCombo(string $name, array $product): bool
    {
        if (Arr::has($product, 'is_combo')) {
            return (bool) Arr::get($product, 'is_combo');
        }

        $normalizedName = Str::lower($name);

        return str_contains($normalizedName, 'full addons')
            || str_contains($normalizedName, 'all addons')
            || str_contains($normalizedName, 'customized package');
    }

    protected function inferIsEnvato(array $product): bool
    {
        if (Arr::has($product, 'is_envato')) {
            return (bool) Arr::get($product, 'is_envato');
        }

        foreach (['envato_url', 'source_url', 'product_url'] as $key) {
            $url = trim((string) Arr::get($product, $key, ''));

            if ($url === '') {
                continue;
            }

            $host = Str::lower((string) parse_url($url, PHP_URL_HOST));

            if (
                str_contains($host, 'envato.com')
                || str_contains($host, 'codecanyon.net')
                || str_contains($host, 'themeforest.net')
            ) {
                return true;
            }
        }

        return false;
    }

    protected function timestampToDateTime(mixed $value): ?string
    {
        $timestamp = (int) $value;

        return $timestamp > 0 ? Carbon::createFromTimestamp($timestamp)->toDateTimeString() : null;
    }

    protected function assetUrl(?string $path): ?string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $this->normalizePublicUrl($path);
        }

        return (self::STACKPOSTS_BASE_URL !== '' ? self::STACKPOSTS_BASE_URL.'/' : '/').$this->normalizePublicAssetPath($path);
    }

    protected function externalUrl(mixed $value): ?string
    {
        $url = trim((string) $value);

        if ($url === '' || ! Str::startsWith($url, ['http://', 'https://'])) {
            return null;
        }

        return $this->normalizePublicUrl($url);
    }

    protected function normalizeHtmlContent(?string $html): ?string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return null;
        }

        return preg_replace_callback(
            '/\b(src|href)=([\'"])([^\'"]+)\2/i',
            function (array $matches): string {
                $attribute = $matches[1];
                $quote = $matches[2];
                $url = trim($matches[3]);

                if ($url === '' || Str::startsWith($url, ['#', 'mailto:', 'tel:', 'javascript:'])) {
                    return $matches[0];
                }

                if (Str::startsWith($url, ['http://', 'https://'])) {
                    $normalizedUrl = $this->normalizePublicUrl($url);
                } else {
                    $normalizedUrl = preg_replace('#^(\.\./)+#', '', ltrim($url, '/')) ?? ltrim($url, '/');

                    if (
                        Str::startsWith($normalizedUrl, 'storage/')
                        || Str::startsWith($normalizedUrl, 'files/')
                        || Str::startsWith($normalizedUrl, 'avatars/')
                    ) {
                        $normalizedUrl = (self::STACKPOSTS_BASE_URL !== '' ? self::STACKPOSTS_BASE_URL.'/' : '/').ltrim($this->normalizePublicAssetPath($normalizedUrl), '/');
                    } else {
                        $normalizedUrl = (self::STACKPOSTS_BASE_URL !== '' ? self::STACKPOSTS_BASE_URL.'/' : '/').ltrim($normalizedUrl, '/');
                    }
                }

                return $attribute.'='.$quote.$normalizedUrl.$quote;
            },
            $html
        );
    }

    protected function normalizePublicUrl(string $url): string
    {
        $url = trim($url);

        if (self::STACKPOSTS_BASE_URL !== '') {
            foreach ([
                'https://shop.com',
                'http://shop.com',
                'https://www.shop.com',
                'http://www.shop.com',
            ] as $legacyBase) {
                if (Str::startsWith($url, $legacyBase)) {
                    return self::STACKPOSTS_BASE_URL.substr($url, strlen($legacyBase));
                }
            }

            foreach ([
                self::STACKPOSTS_BASE_URL.'/files/',
                self::STACKPOSTS_BASE_URL.'/avatars/',
            ] as $legacyPublicPrefix) {
                if (Str::startsWith($url, $legacyPublicPrefix)) {
                    $relativePath = substr($url, strlen(self::STACKPOSTS_BASE_URL.'/'));

                    return self::STACKPOSTS_BASE_URL.'/'.$this->normalizePublicAssetPath($relativePath);
                }
            }
        }

        return $url;
    }

    protected function normalizePublicAssetPath(string $path): string
    {
        $normalizedPath = ltrim(str_replace('\\', '/', trim($path)), '/');

        if (
            Str::startsWith($normalizedPath, 'files/')
            || Str::startsWith($normalizedPath, 'avatars/')
        ) {
            return 'storage/app/public/'.$normalizedPath;
        }

        return $normalizedPath;
    }

    protected function matchPackageToProduct(Collection $catalogProducts, MarketplacePackage $package): ?array
    {
        $packageProductId = (int) ($package->product_id ?: data_get($package->meta, 'product_id', 0));

        if ($packageProductId > 0) {
            $product = $catalogProducts->first(
                fn (array $product): bool => (int) ($product['product_id'] ?? $product['id'] ?? 0) === $packageProductId
            );

            if (is_array($product)) {
                return $product;
            }
        }

        $moduleName = trim((string) $package->module_name);

        if ($moduleName !== '') {
            $product = $catalogProducts->first(
                fn (array $product): bool => strcasecmp((string) ($product['module_name'] ?? ''), $moduleName) === 0
            );

            if (is_array($product)) {
                return $product;
            }
        }

        $installPath = trim((string) $package->install_path);
        $relativeInstallPath = trim(str_replace(base_path(), '', $installPath), DIRECTORY_SEPARATOR.' ');

        if ($relativeInstallPath === '') {
            return null;
        }

        $normalizedInstallPath = trim(
            str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $relativeInstallPath),
            DIRECTORY_SEPARATOR
        );

        $product = $catalogProducts->first(function (array $product) use ($normalizedInstallPath): bool {
            $folderInstall = trim((string) ($product['folder_install'] ?? ''));

            if ($folderInstall === '' || $folderInstall === './') {
                return false;
            }

            $normalizedFolder = trim(
                str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $folderInstall),
                DIRECTORY_SEPARATOR
            );

            return strcasecmp($normalizedFolder, $normalizedInstallPath) === 0;
        });

        return is_array($product) ? $product : null;
    }

    protected function connection()
    {
        $config = $this->shopConfig();

        if ($config === null) {
            throw new RuntimeException('SHOP configuration not found.');
        }

        config([
            'database.connections.marketplace_shop' => [
                'driver' => $config['db_connection'],
                'host' => $config['db_host'],
                'port' => $config['db_port'],
                'database' => $config['db_database'],
                'username' => $config['db_username'],
                'password' => $config['db_password'],
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'prefix_indexes' => true,
                'strict' => true,
                'engine' => null,
            ],
        ]);

        return DB::connection('marketplace_shop');
    }

    protected function cacheKey(string $suffix): string
    {
        return 'admin-marketplace:shop:'.$this->cacheVersion.':'.$suffix;
    }

    protected function shopConfig(): ?array
    {
        if ($this->shopConfig !== null) {
            return $this->shopConfig;
        }

        $envPath = base_path('SHOP/shop/.env');

        if (! File::exists($envPath)) {
            return $this->shopConfig = null;
        }

        $values = [];

        foreach (preg_split("/(\r\n|\n|\r)/", (string) File::get($envPath)) as $line) {
            $line = trim($line);

            if ($line === '' || Str::startsWith($line, '#') || ! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $value = trim($value);

            if (Str::startsWith($value, ['"', "'"]) && Str::endsWith($value, ['"', "'"])) {
                $value = substr($value, 1, -1);
            }

            $values[trim($key)] = $value;
        }

        if (blank($values['DB_DATABASE'] ?? null)) {
            return $this->shopConfig = null;
        }

        return $this->shopConfig = [
            'app_url' => $values['APP_URL'] ?? '',
            'storefront_api_url' => $values['STOREFRONT_API_URL'] ?? '',
            'db_connection' => $values['DB_CONNECTION'] ?? 'mysql',
            'db_host' => $values['DB_HOST'] ?? '127.0.0.1',
            'db_port' => $values['DB_PORT'] ?? '3306',
            'db_database' => $values['DB_DATABASE'] ?? '',
            'db_username' => $values['DB_USERNAME'] ?? '',
            'db_password' => $values['DB_PASSWORD'] ?? '',
        ];
    }
}
