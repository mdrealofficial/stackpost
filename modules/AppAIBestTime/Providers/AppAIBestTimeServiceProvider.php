<?php

namespace Modules\AppAIBestTime\Providers;

use Illuminate\Support\ServiceProvider;

class AppAIBestTimeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.appaibesttime');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'appaibesttime');
    }
}
