<?php

namespace Modules\AdminPaymentSubscriptions\Providers;

use Illuminate\Support\ServiceProvider;

class AdminPaymentSubscriptionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.adminpaymentsubscriptions');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'adminpaymentsubscriptions');

        register_sidebar_section('finance', 'Finance', 40);

        register_sidebar_item('finance', [
            'label' => 'Payment Subscriptions',
            'route_name' => 'admin-payment-subscriptions.index',
            'active_when' => ['admin-payment-subscriptions.*'],
            'icon' => 'fa-light fa-arrows-rotate-reverse',
            'order' => 18,
        ]);

        register_admin_dashboard_item('admin-payment-subscriptions.snapshot', [
            'title' => 'Subscriptions',
            'view' => 'adminpaymentsubscriptions::dashboard.snapshot',
            'width' => 'full',
            'order' => 85,
            'data' => fn () => [
                'metrics' => [
                    'total' => \Modules\AdminPaymentSubscriptions\Models\PaymentSubscription::query()->count(),
                    'active' => \Modules\AdminPaymentSubscriptions\Models\PaymentSubscription::query()->where('status', 1)->count(),
                    'cancelled' => \Modules\AdminPaymentSubscriptions\Models\PaymentSubscription::query()->where('status', 2)->count(),
                    'active_volume' => (float) \Modules\AdminPaymentSubscriptions\Models\PaymentSubscription::query()->where('status', 1)->sum('amount'),
                ],
                'route' => route('admin-payment-subscriptions.index'),
            ],
        ]);
    }
}
