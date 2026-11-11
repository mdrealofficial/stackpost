<?php

namespace Modules\AppIntegrations\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AppIntegrations\Support\IntegrationCatalog;

class AppIntegrationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.appintegrations');
        $this->app->singleton(IntegrationCatalog::class);

        $helper = __DIR__.'/../Support/helpers.php';

        if (is_file($helper)) {
            require_once $helper;
        }
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'appintegrations');

        register_sidebar_item('frontend', [
            'label' => 'API Integration',
            'active_when' => ['admin.integrations'],
            'icon' => 'fa-light fa-plug-circle-bolt',
            'order' => 25,
            'children_resolver' => fn (): array => integration_sidebar_items(),
        ]);
    }
}
