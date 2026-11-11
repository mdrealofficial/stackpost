<?php

namespace Modules\AdminPaymentReport\Providers;

use Illuminate\Support\ServiceProvider;

class AdminPaymentReportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.adminpaymentreport');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'adminpaymentreport');

        register_sidebar_section('finance', 'Finance', 40);

        register_sidebar_item('finance', [
            'label' => 'Payment Report',
            'route_name' => 'admin-payment-report.index',
            'active_when' => ['admin-payment-report.*'],
            'icon' => 'fa-light fa-chart-pie-simple-circle-currency',
            'order' => 15,
        ]);
    }
}
