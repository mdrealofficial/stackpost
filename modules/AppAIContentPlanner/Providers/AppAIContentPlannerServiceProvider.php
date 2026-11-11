<?php

namespace Modules\AppAIContentPlanner\Providers;

use Illuminate\Support\ServiceProvider;

class AppAIContentPlannerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.appaicontentplanner');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'appaicontentplanner');
    }
}
