<?php

namespace Modules\AppGroups\Providers;

use Illuminate\Support\ServiceProvider;

class AppGroupsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.appgroups');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'appgroups');

        register_plan_permission([
            'key' => 'groups',
            'label' => __('Groups'),
            'type' => 'toggle',
            'order' => 120,
        ]);

        $this->app->booted(function (): void {
            \Pricing::addSubFeatures([
                'sort' => 110,
                'parent' => 'features',
                'tab_id' => 'workspace',
                'tab_name' => __('Workspace'),
                'key' => 'groups',
                'label' => __('Groups'),
                'check' => true,
                'type' => 'boolean',
                'raw' => 0,
            ]);
        });
    }
}
