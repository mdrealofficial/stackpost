<?php

namespace Modules\AdminPaymentManualConfig\Providers;

use Illuminate\Support\ServiceProvider;

class AdminPaymentManualConfigServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.adminpaymentmanualconfig');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'adminpaymentmanualconfig');
    }
}
