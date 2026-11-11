<?php

namespace Modules\AdminCache\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AdminCache\Support\CacheActionRegistry;

class AdminCacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.admincache');
        $this->app->singleton(CacheActionRegistry::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'admincache');

        register_setting_item('general', [
            'label' => 'Cache',
            'description' => 'Clear application caches and manage sessions safely.',
            'route_name' => 'admin-cache.index',
            'active_when' => ['admin-cache.*'],
            'order' => 120,
        ]);
    }
}
