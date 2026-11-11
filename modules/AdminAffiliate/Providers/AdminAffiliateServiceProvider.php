<?php

namespace Modules\AdminAffiliate\Providers;

use Illuminate\Support\ServiceProvider;

class AdminAffiliateServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.adminaffiliate');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'adminaffiliate');

        register_sidebar_section('finance', 'Finance', 40);

        register_sidebar_item('finance', [
            'label' => 'Affiliate',
            'route_name' => 'admin-affiliate.index',
            'active_when' => ['admin-affiliate.*', 'admin-affiliate-commissions.*', 'admin-affiliate-withdrawals.*'],
            'icon' => 'fa-light fa-hand-holding-dollar',
            'order' => 35,
            'children' => [
                [
                    'label' => __('Overview'),
                    'route_name' => 'admin-affiliate.index',
                    'active_when' => ['admin-affiliate.*'],
                    'order' => 10,
                ],
                [
                    'label' => __('Commissions'),
                    'route_name' => 'admin-affiliate-commissions.index',
                    'active_when' => ['admin-affiliate-commissions.*'],
                    'order' => 20,
                ],
                [
                    'label' => __('Withdrawals'),
                    'route_name' => 'admin-affiliate-withdrawals.index',
                    'active_when' => ['admin-affiliate-withdrawals.*'],
                    'order' => 30,
                ],
            ],
        ]);
    }
}
