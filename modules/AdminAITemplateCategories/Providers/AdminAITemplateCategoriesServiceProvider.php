<?php

namespace Modules\AdminAITemplateCategories\Providers;

use Illuminate\Support\ServiceProvider;

class AdminAITemplateCategoriesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.adminaitemplatecategories');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'adminaitemplatecategories');

        register_sidebar_item('ai-settings', [
            'label' => 'AI Template Categories',
            'route_name' => 'admin-ai-template-categories.index',
            'active_when' => ['admin-ai-template-categories.*'],
            'icon' => 'ai-template',
            'order' => 40,
        ]);
    }
}
