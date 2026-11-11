<?php

namespace Modules\AdminCrons\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AdminCrons\Support\SystemCronRegistry;

class AdminCronsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.admincrons');
        $this->app->singleton(SystemCronRegistry::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'admincrons');

        register_setting_item('general', [
            'label' => 'Crons',
            'description' => 'Manage scheduler commands, URL cron triggers, and the secure execution key.',
            'route_name' => 'admin-crons.index',
            'active_when' => ['admin-crons.*'],
            'order' => 110,
        ]);
    }
}
