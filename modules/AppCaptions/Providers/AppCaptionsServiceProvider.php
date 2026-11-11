<?php

namespace Modules\AppCaptions\Providers;

use Illuminate\Support\ServiceProvider;

class AppCaptionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.appcaptions');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'appcaptions');

        register_plan_permission([
            'key' => 'captions',
            'label' => __('Captions'),
            'type' => 'toggle',
            'order' => 110,
        ]);

        register_user_sidebar_item('content-tools', [
            'label' => 'Captions',
            'route_name' => 'portal.captions',
            'active_when' => ['portal.captions', 'portal.captions.*'],
            'icon' => 'fa-light fa-pen-field',
            'order' => 10,
            'visible' => fn () => auth()->user()?->canUsePlanFeature('captions') ?? false,
        ]);

        $this->app->booted(function (): void {
            \Pricing::addSubFeatures([
                'sort' => 50,
                'parent' => 'features',
                'tab_id' => 'content',
                'tab_name' => __('Content'),
                'key' => 'captions',
                'label' => __('Captions'),
                'check' => true,
                'type' => 'boolean',
                'raw' => 0,
            ]);
        });
    }
}
