<?php

namespace Modules\AdminPlans\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AdminPlans\Support\DefaultPlanPermissions;
use Modules\AdminPlans\Support\PlanService;
use Modules\AdminPlans\Support\PricingService;

class AdminPlansServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.adminplans');
        $this->app->singleton(PlanService::class);
        $this->app->singleton(PricingService::class);

        if (! class_exists('Plan')) {
            class_alias(\Modules\AdminPlans\Facades\Plan::class, 'Plan');
        }

        if (! class_exists('Pricing')) {
            class_alias(\Modules\AdminPlans\Facades\Pricing::class, 'Pricing');
        }
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'adminplans');

        register_sidebar_section('finance', 'Finance', 40);

        register_sidebar_item('finance', [
            'label' => 'Plans',
            'route_name' => 'admin-plans.index',
            'active_when' => ['admin-plans.*'],
            'icon' => 'fa-light fa-layer-group',
            'order' => 10,
        ]);

        register_admin_dashboard_item('admin-plans.snapshot', [
            'title' => 'Plans',
            'view' => 'adminplans::dashboard.snapshot',
            'width' => 'full',
            'order' => 30,
            'data' => fn () => [
                'metrics' => [
                    'total' => \Modules\AdminPlans\Models\AdminPlan::query()->count(),
                    'active' => \Modules\AdminPlans\Models\AdminPlan::query()->where('status', true)->count(),
                    'featured' => \Modules\AdminPlans\Models\AdminPlan::query()->where('featured', true)->count(),
                    'free' => \Modules\AdminPlans\Models\AdminPlan::query()->where('free_plan', true)->count(),
                ],
                'route' => route('admin-plans.index'),
            ],
        ]);

        foreach (DefaultPlanPermissions::definitions() as $definition) {
            register_plan_permission([
                ...$definition,
                'source' => 'default',
                'placement' => $definition['key'] === 'credits_usage' ? 'top' : 'normal',
            ]);
        }

        $this->app->booted(function (): void {
            \Pricing::add([
                [
                    'sort' => 100,
                    'key' => 'access_feature',
                    'label' => __('Access Features'),
                    'check' => false,
                    'type' => 'group',
                    'raw' => null,
                    'subfeature' => fn (): array => \Pricing::getSubFeatures('features'),
                ],
            ]);
        });
    }
}
