<?php

namespace Modules\AdminMarketplace\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Modules\AdminCrons\Support\SystemCronRegistry;
use Modules\AdminMarketplace\Console\Commands\MarketplaceAutoUpdateCommand;
use Modules\AdminMarketplace\Services\MarketplacePackageService;
use Modules\AdminMarketplace\Services\ShopProductCatalogService;

class AdminMarketplaceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.adminmarketplace');
        $this->app->singleton(MarketplacePackageService::class);
        $this->app->singleton(ShopProductCatalogService::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'adminmarketplace');

        $this->commands([
            MarketplaceAutoUpdateCommand::class,
        ]);

        if ($this->app->runningInConsole()) {
            $this->app->booted(function (): void {
                $this->app->make(Schedule::class)
                    ->command('marketplace:auto-update')
                    ->hourly()
                    ->withoutOverlapping();
            });
        }

        $this->app->afterResolving(SystemCronRegistry::class, function (SystemCronRegistry $registry): void {
            $registry->register([
                'key' => 'marketplace-auto-update',
                'name' => __('Marketplace Auto Update'),
                'icon' => 'fa-light fa-arrows-rotate',
                'description' => __('Check installed marketplace purchase packages and upgrade them automatically when newer licensed versions are available. This task only acts when Marketplace auto-update is enabled in settings.'),
                'command' => 'marketplace:auto-update',
                'recommended' => false,
                'cron_expression' => '0 * * * *',
            ]);
        });

        register_sidebar_section('frontend', 'Settings', 40);

        register_sidebar_item('frontend', [
            'label' => 'Marketplace',
            'route_name' => 'admin-marketplace.index',
            'active_when' => ['admin-marketplace.*'],
            'icon' => 'fa-light fa-store',
            'order' => 15,
        ]);

        register_header_item([
            'view' => 'adminmarketplace::partials.header-item',
            'position' => 'end',
            'order' => 24,
        ], 'admin');

        register_setting_item('general', [
            'label' => 'Marketplace',
            'description' => 'Control automatic marketplace upgrades and the scheduler expectations behind them.',
            'route_name' => 'settings.marketplace',
            'active_when' => ['settings.marketplace'],
            'order' => 27,
        ]);

        register_admin_dashboard_item('admin-marketplace.update-notice', [
            'title' => 'Marketplace Updates',
            'width' => 'full',
            'order' => 5,
            'render' => function (): string {
                return view('adminmarketplace::dashboard.update-notice', [
                    'endpoint' => route('admin-marketplace.dashboard.update-notice'),
                ])->render();
            },
        ]);
    }
}
