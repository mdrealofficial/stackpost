<?php

namespace Modules\AppDeveloperApi\Providers;

use Illuminate\Support\ServiceProvider;

class AppDeveloperApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.appdeveloperapi');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'appdeveloperapi');

        register_user_sidebar_item('workspace', [
            'key' => 'developer_api',
            'label' => __('Developer & API'),
            'route_name' => 'portal.developer-api.index',
            'active_when' => ['portal.developer-api.*'],
            'icon' => 'fa-light fa-code',
            'order' => 62,
            'visible' => fn (): bool => app(\Modules\AppAutomation\Support\AutomationAccess::class)->canManageCredentials(auth()->user()),
        ]);
    }
}
