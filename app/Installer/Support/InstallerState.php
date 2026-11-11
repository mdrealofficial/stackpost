<?php

namespace App\Installer\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Modules\AdminMarketplace\Services\ShopProductCatalogService;
use Throwable;

class InstallerState
{
    public function __construct(
        protected ShopProductCatalogService $shopCatalog,
    ) {}

    public function isInstalled(): bool
    {
        if (filter_var(env('APP_INSTALLED', false), FILTER_VALIDATE_BOOL)) {
            return true;
        }

        try {
            if (! Schema::hasTable('options')) {
                return false;
            }

            return DB::table('options')
                ->where('name', 'installer_completed_at')
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }

    public function requirements(): array
    {
        $requiredPhpVersion = (string) config('installer.required_php_version', '8.3.0');
        $requiredExtensions = (array) config('installer.required_extensions', []);
        $writablePaths = (array) config('installer.writable_paths', []);
        $purchaseRequired = (bool) config('installer.purchase_code_required', true);

        $checks = [
            [
                'label' => 'PHP version',
                'ok' => version_compare(PHP_VERSION, $requiredPhpVersion, '>='),
                'current' => PHP_VERSION,
                'expected' => '>= '.$requiredPhpVersion,
            ],
            [
                'label' => 'Server software',
                'ok' => filled((string) request()->server('SERVER_SOFTWARE', '')),
                'current' => (string) request()->server('SERVER_SOFTWARE', 'Unknown'),
                'expected' => 'Apache, Nginx, or another supported web server',
            ],
        ];

        foreach ($requiredExtensions as $extension) {
            $checks[] = [
                'label' => 'PHP extension: '.$extension,
                'ok' => extension_loaded($extension),
                'current' => extension_loaded($extension) ? 'Loaded' : 'Missing',
                'expected' => 'Loaded',
            ];
        }

        foreach ($writablePaths as $path) {
            $absolutePath = base_path($path);
            $exists = File::exists($absolutePath);
            $writable = $exists ? is_writable($absolutePath) : is_writable(dirname($absolutePath));

            $checks[] = [
                'label' => 'Writable path: '.$path,
                'ok' => $writable,
                'current' => $exists ? 'Writable' : 'Will be created',
                'expected' => 'Writable',
            ];
        }

        if ($purchaseRequired) {
            $checks[] = [
                'label' => 'Purchase verification service',
                'ok' => filled($this->purchaseVerificationEndpoint()),
                'current' => $this->purchaseVerificationEndpoint() ?: 'Not configured',
                'expected' => 'Configured endpoint',
            ];
        }

        return $checks;
    }

    public function allRequirementsPass(): bool
    {
        return collect($this->requirements())->every(fn (array $check) => (bool) ($check['ok'] ?? false));
    }

    public function purchaseVerificationEndpoint(): ?string
    {
        $configured = trim((string) config('installer.purchase_verify_url', ''));

        if ($configured !== '') {
            return $configured;
        }

        $base = $this->shopCatalog->marketplaceApiBase();

        return $base ? rtrim($base, '/').'/install' : null;
    }

    public function isInstallerRequest(Request $request): bool
    {
        $routeName = (string) ($request->route()?->getName() ?? '');

        return str_starts_with($routeName, 'installer.')
            || $request->is('installer')
            || $request->is('installer/*');
    }
}
