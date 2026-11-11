<?php

namespace Modules\AdminBlogCategories\Providers;

use Illuminate\Support\ServiceProvider;

class AdminBlogCategoriesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.adminblogcategories');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'adminblogcategories');
    }
}
