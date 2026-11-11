<?php

namespace Modules\AdminManualPayments\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AdminManualPayments\Services\ManualPaymentService;

class AdminManualPaymentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.adminmanualpayments');
        $this->app->singleton(ManualPaymentService::class, ManualPaymentService::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'adminmanualpayments');

        register_sidebar_section('finance', 'Finance', 40);

        register_sidebar_item('finance', [
            'label' => 'Manual Payments',
            'route_name' => 'admin-manual-payments.index',
            'active_when' => ['admin-manual-payments.*'],
            'icon' => 'fa-light fa-money-check-dollar-pen',
            'order' => 16,
        ]);

        register_admin_dashboard_item('admin-manual-payments.snapshot', [
            'title' => 'Manual payments',
            'view' => 'adminmanualpayments::dashboard.snapshot',
            'width' => 'full',
            'order' => 80,
            'data' => fn () => [
                'metrics' => [
                    'total' => \Modules\AdminManualPayments\Models\ManualPayment::query()->count(),
                    'pending' => \Modules\AdminManualPayments\Models\ManualPayment::query()->where('status', 0)->count(),
                    'approved' => \Modules\AdminManualPayments\Models\ManualPayment::query()->where('status', 1)->count(),
                    'pending_volume' => (float) \Modules\AdminManualPayments\Models\ManualPayment::query()->where('status', 0)->sum('amount'),
                ],
                'route' => route('admin-manual-payments.index'),
            ],
        ]);
    }
}
