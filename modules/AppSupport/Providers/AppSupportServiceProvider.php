<?php

namespace Modules\AppSupport\Providers;

use Illuminate\Support\ServiceProvider;

class AppSupportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.appsupport');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'appsupport');

        register_plan_permission([
            'key' => 'support',
            'label' => __('Support'),
            'type' => 'toggle',
            'order' => 170,
        ]);

        register_user_sidebar_item('help-desk', [
            'label' => 'Support',
            'route_name' => 'portal.support.index',
            'active_when' => ['portal.support.*'],
            'icon' => 'support',
            'order' => 10,
            'visible' => fn () => auth()->user()?->canUsePlanFeature('support') ?? false,
        ]);

        $this->app->booted(function (): void {
            \Pricing::addSubFeatures([
                'sort' => 190,
                'parent' => 'features',
                'tab_id' => 'ops',
                'tab_name' => __('Operations'),
                'key' => 'support',
                'label' => __('Premium Support'),
                'check' => true,
                'type' => 'boolean',
                'raw' => 0,
            ]);

            \Pricing::add([
                'sort' => 600,
                'key' => 'support',
                'label' => __('Premium Support'),
                'check' => true,
                'type' => 'boolean',
                'raw' => 0,
            ]);
        });
    }
}
