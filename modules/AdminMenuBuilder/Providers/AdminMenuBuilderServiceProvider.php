<?php

namespace Modules\AdminMenuBuilder\Providers;

use Illuminate\Support\ServiceProvider;

class AdminMenuBuilderServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'adminmenubuilder');

        register_sidebar_section('frontend', 'Settings', 40);

        register_sidebar_item('frontend', [
            'label' => 'Menu Builder',
            'route_name' => 'admin.menu-builder',
            'active_when' => ['admin.menu-builder'],
            'icon' => 'fa-light fa-bars-sort',
            'order' => 26,
        ]);
    }
}
