<?php

namespace App\Installer\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\AdminPlans\Models\AdminPlan;
use Modules\AdminMarketplace\Models\MarketplacePackage;
use Modules\AdminSettings\Support\OptionStore;
use Modules\AdminUser\Models\User;
use Modules\AdminUser\Support\PersonalTeamProvisioner;
use Modules\AppAffiliate\Support\AffiliateService;
use Modules\AppPayments\Support\UserPlanTransitionService;
use Throwable;

class InstallerService
{
    public function __construct(
        protected InstallerState $state,
        protected EnvFileManager $envFileManager,
        protected OptionStore $optionStore,
        protected PersonalTeamProvisioner $teamProvisioner,
        protected AffiliateService $affiliateService,
        protected UserPlanTransitionService $userPlanTransitions,
    ) {}

    public function install(array $data, Request $request): User
    {
        if (! $this->state->allRequirementsPass()) {
            throw ValidationException::withMessages([
                'installer' => __('The server does not meet the installer requirements. Resolve the failed checks and try again.'),
            ]);
        }

        $verifiedPurchase = $this->verifyPurchaseCode($data, $request);
        $appKey = $this->generateAppKey();
        $originalEnv = File::exists(base_path('.env')) ? File::get(base_path('.env')) : null;

        try {
            $this->writeEnvironment($data, $request, $appKey, false);
            $this->configureRuntimeDatabase($data);
            $this->ensureFreshDatabase();

            Artisan::call('migrate', [
                '--force' => true,
                '--no-interaction' => true,
            ]);
            $this->seedDefaults();

            $user = $this->createAdministrator($data);
            $this->assignAdministratorPlan($user);
            $this->storeOptions($data, $verifiedPurchase);
            $this->syncMarketplaceLicensePackage($data, $request, $verifiedPurchase);
            $this->writeEnvironment($data, $request, $appKey, true);
            return $user;
        } catch (Throwable $exception) {
            $this->restoreEnvironment($originalEnv);

            throw $exception;
        }
    }

    protected function verifyPurchaseCode(array $data, Request $request): ?array
    {
        $required = (bool) config('installer.purchase_code_required', true);
        $purchaseCode = trim((string) ($data['purchase_code'] ?? ''));

        if (! $required && $purchaseCode === '') {
            return null;
        }

        if ($purchaseCode === '') {
            throw ValidationException::withMessages([
                'purchase_code' => __('Purchase code is required.'),
            ]);
        }

        $endpoint = $this->state->purchaseVerificationEndpoint();

        if (! $endpoint) {
            throw ValidationException::withMessages([
                'purchase_code' => __('Purchase verification service is not configured.'),
            ]);
        }

        try {
            $response = Http::withOptions(['verify' => false])
                ->timeout(30)
                ->post($endpoint, [
                    'purchase_code' => preg_replace('/\s+/', '', $purchaseCode),
                    'domain' => preg_replace('/^www\./', '', (string) $request->getHost()),
                    'website' => url('/'),
                    'is_main' => 1,
                ]);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'purchase_code' => __('Could not contact the purchase verification server.'),
            ]);
        }

        $payload = $response->json();

        if (! $response->ok() || (int) data_get($payload, 'status', 0) !== 1) {
            throw ValidationException::withMessages([
                'purchase_code' => (string) data_get($payload, 'message', __('Purchase code verification failed.')),
            ]);
        }

        return is_array($payload) ? $payload : [];
    }

    protected function writeEnvironment(array $data, Request $request, string $appKey, bool $installed): void
    {
        $title = trim((string) $data['website_title']);
        $basePath = '/'.trim((string) $request->getBaseUrl(), '/');
        $sessionPath = $basePath !== '/' ? $basePath : '/';
        $sessionCookie = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $title) ?: 'application').'_session';
        $appUrl = rtrim($request->getSchemeAndHttpHost().$request->getBaseUrl(), '/');
        $secureSessionCookie = $this->shouldUseSecureSessionCookie($appUrl);

        $env = [
            'APP_NAME' => $title,
            'APP_URL' => $appUrl !== '' ? $appUrl : 'http://127.0.0.1',
            'APP_KEY' => $appKey,
            'APP_TIMEZONE' => (string) $data['admin_timezone'],
            'APP_INSTALLED' => $installed ? 'true' : 'false',
            'SITE_TITLE' => $title,
            'SITE_DESCRIPTION' => (string) ($data['website_description'] ?? ''),
            'SITE_KEYWORDS' => (string) ($data['website_keywords'] ?? ''),
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => (string) ($data['db_host'] ?? ''),
            'DB_PORT' => (string) ($data['db_port'] ?? ''),
            'DB_DATABASE' => (string) ($data['db_database'] ?? ''),
            'DB_USERNAME' => (string) ($data['db_username'] ?? ''),
            'DB_PASSWORD' => (string) ($data['db_password'] ?? ''),
            'SESSION_DRIVER' => $installed ? (string) config('installer.final_session_driver', 'database') : 'file',
            'SESSION_PATH' => $sessionPath,
            'SESSION_COOKIE' => $sessionCookie,
            'SESSION_SECURE_COOKIE' => $secureSessionCookie ? 'true' : 'false',
            'CACHE_STORE' => $installed ? (string) config('installer.final_cache_store', 'database') : 'file',
            'QUEUE_CONNECTION' => $installed ? (string) config('installer.final_queue_connection', 'database') : 'sync',
        ];

        $this->envFileManager->write($env);
    }

    protected function shouldUseSecureSessionCookie(string $appUrl): bool
    {
        $scheme = strtolower((string) parse_url(trim($appUrl), PHP_URL_SCHEME));

        return $scheme === 'https';
    }

    protected function generateAppKey(): string
    {
        return 'base64:'.base64_encode(random_bytes(32));
    }

    protected function restoreEnvironment(?string $contents): void
    {
        $path = base_path('.env');

        if ($contents === null) {
            if (File::exists($path)) {
                File::delete($path);
            }

            return;
        }

        File::put($path, $contents);
    }

    protected function configureRuntimeDatabase(array $data): void
    {
        $connectionName = 'mysql';
        $config = [
            'driver' => 'mysql',
            'host' => (string) $data['db_host'],
            'port' => (string) $data['db_port'],
            'database' => (string) ($data['db_database'] ?? ''),
            'username' => (string) $data['db_username'],
            'password' => (string) ($data['db_password'] ?? ''),
            'unix_socket' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
        ];

        Config::set('database.default', $connectionName);
        Config::set('database.connections.'.$connectionName, $config);
        DB::purge($connectionName);
        DB::reconnect($connectionName);
    }

    protected function ensureFreshDatabase(): void
    {
        $tables = DB::select('SHOW TABLES');

        if ($tables !== []) {
            throw ValidationException::withMessages([
                'installer' => __('The selected database is not empty. Use a fresh database or remove all existing tables before running the installer again.'),
            ]);
        }
    }

    protected function createAdministrator(array $data): User
    {
        $user = User::query()->updateOrCreate(
            ['email' => strtolower((string) $data['admin_email'])],
            [
                'name' => (string) $data['admin_name'],
                'username' => strtolower((string) $data['admin_username']),
                'email' => strtolower((string) $data['admin_email']),
                'email_verified_at' => now(),
                'timezone' => (string) $data['admin_timezone'],
                'is_super_admin' => true,
                'password' => (string) $data['admin_password'],
            ]
        );

        $this->affiliateService->ensureReferralCode($user);
        $this->affiliateService->ensureProfile($user);
        $this->teamProvisioner->ensureForUser($user);

        return $user;
    }

    protected function assignAdministratorPlan(User $user): void
    {
        $planSlug = trim((string) config('installer.admin_plan_slug', ''));

        if ($planSlug === '') {
            return;
        }

        $plan = AdminPlan::query()
            ->where('slug', $planSlug)
            ->where('status', true)
            ->first();

        if (! $plan instanceof AdminPlan) {
            return;
        }

        $this->userPlanTransitions->applyPurchasedPlan($user, $plan);
    }

    protected function seedDefaults(): void
    {
        foreach ((array) config('installer.default_seeders', []) as $seederClass) {
            Artisan::call('db:seed', [
                '--class' => $seederClass,
                '--force' => true,
                '--no-interaction' => true,
            ]);
        }
    }

    protected function storeOptions(array $data, ?array $verifiedPurchase): void
    {
        $this->optionStore->set('website_title', (string) $data['website_title']);
        $this->optionStore->set('website_description', (string) ($data['website_description'] ?? ''));
        $this->optionStore->set('website_keyword', (string) ($data['website_keywords'] ?? ''));
        $this->optionStore->set('contact_company_name', (string) $data['website_title']);
        $this->optionStore->set('contact_email', (string) $data['admin_email']);
        $this->optionStore->set('app_timezone', (string) $data['admin_timezone']);
        $this->optionStore->set('installer_completed_at', Carbon::now()->toIso8601String());

        if ($verifiedPurchase) {
            $this->optionStore->set('license_purchase_code', (string) ($data['purchase_code'] ?? ''));
            $this->optionStore->set('license_status', (string) data_get($verifiedPurchase, 'license.status', 'verified'));
            $this->optionStore->set('license_product_id', (string) data_get($verifiedPurchase, 'product_id', ''));
            $this->optionStore->set('license_version', (string) data_get($verifiedPurchase, 'version', ''));
            $this->optionStore->set('license_install_path', (string) data_get($verifiedPurchase, 'install_path', ''));
            $this->optionStore->set('license_verified_at', Carbon::now()->toIso8601String());
            $this->optionStore->set('license_meta', $verifiedPurchase);
        }
    }

    protected function syncMarketplaceLicensePackage(array $data, Request $request, ?array $verifiedPurchase): void
    {
        if (! $verifiedPurchase) {
            return;
        }

        $productId = (int) data_get($verifiedPurchase, 'product_id', 0);
        $productSlug = trim((string) data_get($verifiedPurchase, 'slug', ''));
        $moduleName = trim((string) data_get($verifiedPurchase, 'module_name', ''));
        $productTitle = trim((string) data_get($verifiedPurchase, 'name', ''));
        $purchaseCode = trim((string) ($data['purchase_code'] ?? ''));
        $version = trim((string) data_get($verifiedPurchase, 'version', ''));
        $installPath = $this->resolveInstallerLicenseInstallPath(
            trim((string) data_get($verifiedPurchase, 'install_path', '')),
            $moduleName
        );

        $packageKeySource = $productSlug !== ''
            ? $productSlug
            : ($moduleName !== '' ? $moduleName : ('installer-main-product-'.($productId > 0 ? $productId : 'license')));
        $packageKey = Str::slug($packageKeySource);
        $existingPackage = MarketplacePackage::query()
            ->where('package_key', $packageKey)
            ->first();

        MarketplacePackage::query()->updateOrCreate(
            ['package_key' => $packageKey],
            [
                'id_secure' => $existingPackage?->id_secure ?: Str::random(32),
                'module_name' => $moduleName !== '' ? $moduleName : null,
                'title' => $productTitle !== '' ? $productTitle : (string) $data['website_title'],
                'description' => trim((string) data_get($verifiedPurchase, 'description', '')),
                'version' => $version !== '' ? $version : null,
                'source_type' => 'purchase',
                'product_id' => $productId > 0 ? $productId : null,
                'purchase_code' => $purchaseCode !== '' ? preg_replace('/\s+/', '', $purchaseCode) : null,
                'product_slug' => $productSlug !== '' ? $productSlug : null,
                'license_type' => trim((string) data_get($verifiedPurchase, 'license', data_get($verifiedPurchase, 'license.status', ''))) ?: null,
                'licensed_domain' => preg_replace('/^www\./', '', (string) $request->getHost()),
                'install_path' => $installPath,
                'providers' => [],
                'meta' => array_filter([
                    'installer_managed' => true,
                    'is_main' => 1,
                    'marketplace_version' => $version !== '' ? $version : null,
                    'payload' => $verifiedPurchase,
                ], fn ($value) => $value !== null && $value !== ''),
                'is_active' => true,
                'installed_at' => now(),
                'last_synced_at' => now(),
            ]
        );
    }

    protected function resolveInstallerLicenseInstallPath(string $relativeInstallPath, string $moduleName): ?string
    {
        if ($relativeInstallPath === '') {
            return base_path();
        }

        $normalized = str_replace('\\', '/', $relativeInstallPath);

        if ($moduleName !== '' && preg_match('#(?:^|/)\.\./modules/?$#', $normalized)) {
            return base_path('modules/'.$moduleName);
        }

        $trimmed = preg_replace('#^(?:\.\./)+#', '', ltrim($normalized, '/'));
        $resolved = $trimmed !== '' ? base_path($trimmed) : base_path();

        return str_replace('\\', DIRECTORY_SEPARATOR, $resolved);
    }
}
