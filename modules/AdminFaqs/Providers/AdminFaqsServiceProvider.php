<?php

namespace Modules\AdminFaqs\Providers;

use Illuminate\Support\ServiceProvider;

class AdminFaqsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.adminfaqs');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'adminfaqs');

        register_sidebar_item('main', [
            'label' => 'FAQs',
            'route_name' => 'admin-faqs.index',
            'active_when' => ['admin-faqs.*'],
            'icon' => 'fa-light fa-circle-question',
            'order' => 15,
        ]);
    }
}
