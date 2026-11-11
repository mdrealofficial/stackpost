<?php

namespace Modules\AppAffiliate\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AppAffiliate\Support\AffiliateService;

class AppAffiliateServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.appaffiliate');
        $this->app->singleton(AffiliateService::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'appaffiliate');

        register_setting_item('general', [
            'label' => 'Affiliate',
            'description' => 'Configure referral tracking, commission percentage, payout threshold, and eligible payment sources.',
            'route_name' => 'settings.affiliate',
            'active_when' => ['settings.affiliate'],
            'order' => 30,
        ]);

        register_plan_permission([
            'key' => 'affiliate',
            'label' => __('Affiliate'),
            'type' => 'toggle',
            'default' => true,
            'order' => 180,
        ]);

        register_user_sidebar_item('portal', [
            'label' => 'Affiliate',
            'route_name' => 'portal.affiliate.index',
            'active_when' => ['portal.affiliate.*'],
            'icon' => 'fa-light fa-hand-holding-dollar',
            'order' => 40,
            'visible' => fn () => app(AffiliateService::class)->userCanAccess(auth()->user()),
        ]);
    }
}
