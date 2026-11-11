<?php

namespace Modules\AdminMarketplace\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Routing\Controller;
use RuntimeException;
use Throwable;
use Modules\AdminMarketplace\Models\MarketplacePackage;
use Modules\AdminMarketplace\Services\MarketplacePackageService;
use Modules\AdminMarketplace\Services\ShopProductCatalogService;

class AdminMarketplaceController extends Controller
{
    protected const DASHBOARD_UPDATE_NOTICE_CACHE_KEY = 'admin-dashboard:marketplace-update-notice';

    public function __construct(
        protected MarketplacePackageService $packages,
        protected ShopProductCatalogService $catalog,
    ) {}

    public function rescan(): RedirectResponse
    {
        $count = $this->packages->discover()->count();
        Cache::forget(self::DASHBOARD_UPDATE_NOTICE_CACHE_KEY);

        return back()
            ->with('status', __('Marketplace synced successfully. :count package(s) found.', ['count' => number_format($count)]));
    }

    public function install(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'install_method' => ['required', 'in:purchase,zip'],
            'purchase_code' => ['nullable', 'string'],
            'module_zip' => ['nullable', 'file', 'mimes:zip'],
        ]);

        try {
            $package = match ($validated['install_method']) {
                'purchase' => $this->installFromPurchaseCode($request),
                default => $this->installFromZipRequest($request),
            };

            $bundleModules = collect((array) data_get($package->meta, 'bundle_modules', []))
                ->filter(fn ($moduleName) => is_string($moduleName) && trim($moduleName) !== '')
                ->values();

            if ($bundleModules->isNotEmpty()) {
                MarketplacePackage::query()
                    ->whereIn('module_name', $bundleModules->all())
                    ->get()
                    ->each(fn (MarketplacePackage $bundlePackage) => $this->packages->activate($bundlePackage));
            } else {
                $this->packages->activate($package);
            }

            Cache::forget(self::DASHBOARD_UPDATE_NOTICE_CACHE_KEY);

            return redirect()
                ->back()
                ->with('status', __('Package ":title" installed successfully.', ['title' => $package->title]));
        } catch (RuntimeException $exception) {
            return back()
                ->withInput()
                ->withErrors([
                    ($validated['install_method'] ?? 'zip') === 'purchase' ? 'purchase_code' : 'module_zip' => $exception->getMessage(),
                ]);
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors([
                    ($validated['install_method'] ?? 'zip') === 'purchase' ? 'purchase_code' : 'module_zip' => __('Package install failed. Check the input and try again.'),
                ]);
        }
    }

    protected function installFromZipRequest(Request $request): MarketplacePackage
    {
        $zip = $request->file('module_zip');

        if (! $zip) {
            throw new RuntimeException(__('Module ZIP is required.'));
        }

        return $this->packages->installFromZip($zip);
    }

    protected function installFromPurchaseCode(Request $request): MarketplacePackage
    {
        $apiBase = $this->catalog->marketplaceApiBase();

        if (! $apiBase) {
            throw new RuntimeException(__('Marketplace install API is not configured.'));
        }

        $domain = preg_replace('/^www\./', '', (string) $request->getHost());

        return $this->packages->installFromPurchaseCode(
            (string) $request->input('purchase_code'),
            $apiBase,
            trim((string) $domain),
            url('/')
        );
    }

    public function update(Request $request, MarketplacePackage $package): RedirectResponse
    {
        try {
            $apiBase = $this->catalog->marketplaceApiBase();

            if (! $apiBase) {
                throw new RuntimeException(__('Marketplace update API is not configured.'));
            }

            $domain = preg_replace('/^www\./', '', (string) $request->getHost());
            $package = $this->packages->updateFromPurchase(
                $package,
                $apiBase,
                trim((string) $domain),
                url('/')
            );
            Cache::forget(self::DASHBOARD_UPDATE_NOTICE_CACHE_KEY);

            return redirect()
                ->back()
                ->with('status', __('Package ":title" updated successfully to version :version.', [
                    'title' => $package->title,
                    'version' => $package->version ?: __('Unknown'),
                ]));
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->back()
                ->withErrors(['marketplace' => $exception->getMessage()]);
        }
    }

    public function destroy(MarketplacePackage $package): RedirectResponse
    {
        $title = $package->title;

        try {
            $this->packages->uninstall($package);
            Cache::forget(self::DASHBOARD_UPDATE_NOTICE_CACHE_KEY);

            return redirect()
                ->back()
                ->with('status', __('Package ":title" uninstalled successfully.', ['title' => $title]));
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->back()
                ->withErrors(['marketplace' => $exception->getMessage()]);
        }
    }

    public function storefrontCart(Request $request): JsonResponse
    {
        return $this->forwardStorefrontRequest($request, '/cart', 'GET');
    }

    public function storefrontCartAdd(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'product_id' => ['required', 'integer'],
            'license_type' => ['required', 'string', 'in:regular,extended'],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        return $this->forwardStorefrontRequest($request, '/cart/items', 'POST', [
            'product_id' => (int) $payload['product_id'],
            'license_type' => (string) $payload['license_type'],
            'quantity' => (int) ($payload['quantity'] ?? 1),
        ]);
    }

    public function storefrontCartRemove(Request $request, string $itemKey): JsonResponse
    {
        return $this->forwardStorefrontRequest($request, '/cart/items/'.rawurlencode($itemKey), 'DELETE');
    }

    public function storefrontCartClear(Request $request): JsonResponse
    {
        return $this->forwardStorefrontRequest($request, '/cart', 'DELETE');
    }

    public function dashboardUpdateNotice(): JsonResponse
    {
        $notice = $this->catalog->marketplaceUpdateNotice($this->packages);

        return response()->json([
            'status' => 1,
            'data' => $notice,
        ]);
    }

    protected function forwardStorefrontRequest(
        Request $request,
        string $path,
        string $method,
        array $payload = []
    ): JsonResponse {
        $apiBase = $this->catalog->storefrontApiBase();

        if (! $apiBase) {
            return response()->json([
                'status' => 0,
                'message' => __('Storefront API is not configured.'),
            ], 500);
        }

        $cartToken = trim((string) $request->header('X-Cart-Token', ''));
        $http = Http::withOptions(['verify' => false])
            ->acceptJson()
            ->timeout(30);

        if ($cartToken !== '') {
            $http = $http->withHeaders([
                'X-Cart-Token' => $cartToken,
            ]);
        }

        $response = match (strtoupper($method)) {
            'POST' => $http->post(rtrim($apiBase, '/').$path, $payload),
            'DELETE' => $http->delete(rtrim($apiBase, '/').$path),
            default => $http->get(rtrim($apiBase, '/').$path),
        };

        $json = $response->json();
        $headerToken = trim((string) $response->header('X-Cart-Token', ''));
        $responseToken = $headerToken !== '' ? $headerToken : $cartToken;

        return response()
            ->json(is_array($json) ? $json : [
                'status' => $response->successful() ? 1 : 0,
                'message' => $response->successful()
                    ? __('Storefront request completed successfully.')
                    : __('Storefront request failed.'),
            ], $response->status())
            ->header('X-Cart-Token', $responseToken);
    }
}
