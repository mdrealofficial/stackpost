<?php

namespace App\Providers;

use App\Http\Middleware\PreventDemoModeWriteOperations;
use App\Livewire\DemoModeActionGuard;
use App\Support\Dashboard\AdminDashboardRegistry;
use App\Support\Dashboard\UserDashboardRegistry;
use App\Support\Navigation\HeaderRegistry;
use App\Support\Navigation\SidebarRegistry;
use App\Support\Plans\PlanPermissionRegistry;
use App\Support\Storage\SocialAvatarStore;
use App\Support\Storage\StorageDriverManager;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SidebarRegistry::class);
        $this->app->singleton(HeaderRegistry::class);
        $this->app->singleton(AdminDashboardRegistry::class);
        $this->app->singleton(UserDashboardRegistry::class);
        $this->app->singleton(PlanPermissionRegistry::class);
        $this->app->singleton(StorageDriverManager::class);
        $this->app->singleton(SocialAvatarStore::class);

        $this->app->booting(function (): void {
            Livewire::componentHook(DemoModeActionGuard::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        $this->configureDefaults();
        $this->configureLivewireAssets();
        $this->configureLivewireMiddleware();
        $this->configureFortifyFeatures();
        $this->configureStorageDisks();
        $this->configureTestingViewPath();
        $this->registerSharedComponents();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function configureFortifyFeatures(): void
    {
        if (! function_exists('sync_fortify_features_from_settings')) {
            return;
        }

        sync_fortify_features_from_settings();
    }

    protected function configureStorageDisks(): void
    {
        if (! class_exists(StorageDriverManager::class)) {
            return;
        }

        app(StorageDriverManager::class)->configureDisks();
    }

    protected function configureLivewireAssets(): void
    {
        // Auto-detect non-standard docroot and use a relative JS path to avoid mixed-content.
        $assetUrl = null;

        if (! $this->app->runningInConsole()) {
            $documentRoot = request()->server('DOCUMENT_ROOT');
            $publicPath = public_path();

            if (is_string($documentRoot) && $documentRoot !== '') {
                $resolvedDocumentRoot = realpath($documentRoot);
                $resolvedPublicPath = realpath($publicPath);

                if ($resolvedDocumentRoot && $resolvedPublicPath && $resolvedDocumentRoot !== $resolvedPublicPath) {
                    $baseUrl = trim((string) request()->getBaseUrl());
                    $assetUrl = ($baseUrl !== '' ? $baseUrl : '').'/public/livewire/livewire.js';
                }
            }
        }

        config(['livewire.asset_url' => $assetUrl]);
    }

    protected function configureLivewireMiddleware(): void
    {
        Livewire::addPersistentMiddleware([
            PreventDemoModeWriteOperations::class,
        ]);
    }

    protected function configureTestingViewPath(): void
    {
        if (! $this->app->runningUnitTests()) {
            return;
        }

        $compiledPath = storage_path('framework/views/testing-'.getmypid());

        File::ensureDirectoryExists($compiledPath);

        config(['view.compiled' => $compiledPath]);
    }

    protected function registerSharedComponents(): void
    {
        $themeComponentPath = resource_path('themes/app/default/resources/views/components');
        $sharedComponentPath = resource_path('themes/shared/views/components');

        if (File::isDirectory($themeComponentPath)) {
            Blade::anonymousComponentPath($themeComponentPath);
        }

        if (! File::isDirectory($sharedComponentPath)) {
            return;
        }

        Blade::anonymousComponentPath($sharedComponentPath, 'shared');
    }
}
