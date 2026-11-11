<?php

namespace Modules\AdminCoupons\Providers;

use Illuminate\Support\ServiceProvider;

class AdminCouponsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.admincoupons');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'admincoupons');

        register_sidebar_section('finance', 'Finance', 40);

        register_sidebar_item('finance', [
            'label' => 'Coupons',
            'route_name' => 'admin-coupons.index',
            'active_when' => ['admin-coupons.*'],
            'icon' => 'fa-light fa-ticket',
            'order' => 12,
        ]);
    }
}
