<?php

namespace Modules\AdminAITemplate\Providers;

use Illuminate\Support\ServiceProvider;

class AdminAITemplateServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.adminaitemplate');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'adminaitemplate');

        register_sidebar_item('ai-settings', [
            'label' => 'AI Templates',
            'route_name' => 'admin-ai-templates.index',
            'active_when' => ['admin-ai-templates.*'],
            'icon' => 'fa-light fa-sparkles',
            'order' => 45,
        ]);
    }
}
