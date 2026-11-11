<?php

namespace Modules\AdminDashboard\Providers;

use Illuminate\Support\ServiceProvider;

class AdminDashboardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.admindashboard');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        register_sidebar_section('main', null, 10);

        register_sidebar_item('main', [
            'label' => 'Dashboard',
            'route_name' => 'dashboard',
            'active_when' => ['dashboard'],
            'icon' => 'dashboard',
            'order' => 10,
        ]);
    }
}
