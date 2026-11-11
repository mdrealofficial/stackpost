<?php

namespace Modules\AppAITrendFinder\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\AppAIStudio\Support\AIStudioPlanFeature;
use Modules\AppAIStudio\Support\AIStudioToolRegistry;
use Modules\AppTeams\Support\TeamPermissionRegistry;
use Modules\AppTeams\Support\TeamWorkspaceAccess;

class AppAITrendFinderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.appaitrendfinder');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'appaitrendfinder');

        TeamPermissionRegistry::registerModule('ai_trend_finder', [
            'label' => __('AI Trend Finder'),
            'configurable' => true,
            'enabled_by_default' => true,
        ]);

        register_credit_action([
            'key' => 'ai_studio_find_trends',
            'plan_key' => 'credit_cost_ai_studio_find_trends',
            'label' => __('AI Studio Trend Finder'),
            'default_cost' => 1,
            'order' => 24,
            'description' => __('Credits deducted each time AI Studio discovers trend-based content opportunities.'),
        ]);

        register_plan_permission([
            'key' => 'ai_studio',
            'fields' => [
                ['key' => 'ai_studio_trend_finder', 'label' => __('Trend Finder'), 'type' => 'boolean', 'default' => '1', 'order' => 35],
            ],
        ]);

        app(AIStudioToolRegistry::class)->register([
            'key' => 'trend_finder',
            'label' => __('Trend Finder'),
            'description' => __('Discover timely content opportunities and turn them into platform-ready ideas.'),
            'route_name' => 'portal.ai-trend-finder',
            'active_when' => ['portal.ai-trend-finder'],
            'feature' => 'ai_studio_trend_finder',
            'team_module' => 'ai_trend_finder',
            'icon' => 'fa-light fa-radar',
            'tone' => 'rgba(var(--theme-success-color-rgb),0.10)',
            'order' => 45,
        ]);

        $this->app->booted(function (): void {
            \Pricing::addSubFeatures([
                'sort' => 85,
                'parent' => 'features',
                'tab_id' => 'ai_studio',
                'tab_name' => __('AI Studio'),
                'standalone' => true,
                'standalone_sort' => 200,
                'standalone_key' => 'ai_studio',
                'key' => 'ai_studio_trend_finder',
                'label' => __('Trend Finder'),
                'check' => true,
                'type' => 'boolean',
                'raw' => 0,
            ]);
        });
    }

    public static function visible(): bool
    {
        if (! Route::has('portal.ai-trend-finder')) {
            return false;
        }

        $user = auth()->user();
        $team = TeamWorkspaceAccess::activeTeam($user);
        $planOwner = $team?->owner ?: $user;

        return TeamWorkspaceAccess::teamHasModule($team, 'ai_trend_finder')
            && ($planOwner?->canUsePlanFeature('ai_studio') ?? false)
            && AIStudioPlanFeature::enabled($planOwner, 'ai_studio_trend_finder');
    }
}
