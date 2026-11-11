<?php

namespace Modules\AppWatermark\Providers;

use Illuminate\Support\ServiceProvider;

class AppWatermarkServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.appwatermark');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'appwatermark');

        register_plan_permission([
            'key' => 'watermark',
            'label' => __('Watermark'),
            'type' => 'toggle',
            'order' => 200,
        ]);

        register_user_sidebar_item('content-tools', [
            'label' => 'Watermark',
            'route_name' => 'portal.watermarks',
            'active_when' => ['portal.watermarks', 'portal.watermarks.*'],
            'icon' => 'fa-light fa-droplet-percent',
            'order' => 40,
            'visible' => fn () => auth()->user()?->canUsePlanFeature('watermark') ?? false,
        ]);

        $this->app->booted(function (): void {
            \Pricing::addSubFeatures([
                'sort' => 180,
                'parent' => 'features',
                'tab_id' => 'ops',
                'tab_name' => __('Operations'),
                'key' => 'watermark',
                'label' => __('Watermark'),
                'check' => true,
                'type' => 'boolean',
                'raw' => 0,
            ]);

            \Pricing::add([
                'sort' => 500,
                'key' => 'watermark',
                'label' => __('Watermark'),
                'check' => true,
                'type' => 'boolean',
                'raw' => 0,
            ]);
        });
    }
}
