<?php

namespace Modules\AppAIRepurpose\Providers;

use Illuminate\Support\ServiceProvider;

class AppAIRepurposeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.appairepurpose');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'appairepurpose');
    }
}
