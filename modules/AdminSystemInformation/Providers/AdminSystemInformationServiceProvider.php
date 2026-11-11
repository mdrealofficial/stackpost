<?php

namespace Modules\AdminSystemInformation\Providers;

use Illuminate\Support\ServiceProvider;

class AdminSystemInformationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.adminsysteminformation');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'adminsysteminformation');

        register_setting_item('general', [
            'label' => 'System information',
            'description' => 'Review the current runtime, environment, and infrastructure drivers.',
            'route_name' => 'settings.system-information',
            'active_when' => ['settings.system-information'],
            'order' => 130,
        ]);
    }
}
