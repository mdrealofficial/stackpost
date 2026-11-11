<?php

namespace Modules\AdminPaymentHistory\Providers;

use Illuminate\Support\ServiceProvider;

class AdminPaymentHistoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.adminpaymenthistory');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'adminpaymenthistory');

        register_sidebar_section('finance', 'Finance', 40);

        register_sidebar_item('finance', [
            'label' => 'Payment History',
            'route_name' => 'admin-payment-history.index',
            'active_when' => ['admin-payment-history.*'],
            'icon' => 'fa-light fa-receipt',
            'order' => 20,
        ]);
    }
}
