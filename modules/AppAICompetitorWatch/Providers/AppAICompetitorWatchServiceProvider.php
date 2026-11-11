<?php

namespace Modules\AppAICompetitorWatch\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AppAIStudio\Support\AIStudioToolRegistry;
use Modules\AppTeams\Support\TeamPermissionRegistry;

class AppAICompetitorWatchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.appaicompetitorwatch');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'appaicompetitorwatch');

        TeamPermissionRegistry::registerModule('ai_competitor_watch', [
            'label' => __('AI Competitor Watch'),
            'configurable' => true,
            'enabled_by_default' => true,
        ]);

        register_credit_action([
            'key' => 'ai_studio_competitor_watch',
            'plan_key' => 'credit_cost_ai_studio_competitor_watch',
            'label' => __('AI Competitor Watch'),
            'default_cost' => 2,
            'order' => 25,
            'description' => __('Credits deducted each time AI analyzes competitor positioning and content opportunities.'),
        ]);

        register_plan_permission([
            'key' => 'ai_studio',
            'fields' => [
                ['key' => 'ai_studio_competitor_watch', 'label' => __('Competitor Watch'), 'type' => 'boolean', 'default' => '1', 'order' => 28],
            ],
        ]);

        app(AIStudioToolRegistry::class)->register([
            'key' => 'competitor_watch',
            'label' => __('Competitor Watch'),
            'description' => __('Analyze competitor positioning and uncover reusable content opportunities.'),
            'route_name' => 'portal.ai-competitor-watch',
            'active_when' => ['portal.ai-competitor-watch'],
            'feature' => 'ai_studio_competitor_watch',
            'team_module' => 'ai_competitor_watch',
            'icon' => 'fa-light fa-binoculars',
            'tone' => 'rgba(var(--theme-warning-color-rgb),0.10)',
            'order' => 47,
        ]);

        $this->app->booted(function (): void {
            \Pricing::addSubFeatures([
                'sort' => 86,
                'parent' => 'features',
                'tab_id' => 'ai_studio',
                'tab_name' => __('AI Studio'),
                'standalone' => true,
                'standalone_sort' => 200,
                'standalone_key' => 'ai_studio',
                'key' => 'ai_studio_competitor_watch',
                'label' => __('Competitor Watch'),
                'check' => true,
                'type' => 'boolean',
                'raw' => 0,
            ]);
        });
    }
}
