<?php

namespace Modules\AppAICampaignWizard\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AppAIStudio\Support\AIStudioToolRegistry;
use Modules\AppTeams\Support\TeamPermissionRegistry;

class AppAICampaignWizardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.appaicampaignwizard');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'appaicampaignwizard');

        TeamPermissionRegistry::registerModule('ai_campaign_wizard', [
            'label' => __('AI Campaign Wizard'),
            'configurable' => true,
            'enabled_by_default' => true,
        ]);

        register_credit_action([
            'key' => 'ai_studio_campaign_wizard',
            'plan_key' => 'credit_cost_ai_studio_campaign_wizard',
            'label' => __('AI Studio Campaign Wizard'),
            'default_cost' => 2,
            'order' => 24,
            'description' => __('Credits deducted each time AI Studio builds a full campaign plan.'),
        ]);

        register_plan_permission([
            'key' => 'ai_studio',
            'fields' => [
                ['key' => 'ai_studio_campaign_wizard', 'label' => __('Campaign Wizard'), 'type' => 'boolean', 'default' => '1', 'order' => 25],
            ],
        ]);

        app(AIStudioToolRegistry::class)->register([
            'key' => 'campaign_wizard',
            'label' => __('Campaign Wizard'),
            'description' => __('Turn a campaign brief into a structured, channel-ready content plan.'),
            'route_name' => 'portal.ai-campaign-wizard',
            'active_when' => ['portal.ai-campaign-wizard'],
            'feature' => 'ai_studio_campaign_wizard',
            'team_module' => 'ai_campaign_wizard',
            'icon' => 'fa-light fa-bullseye-arrow',
            'tone' => 'rgba(var(--theme-accent-rgb),0.10)',
            'order' => 46,
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
                'key' => 'ai_studio_campaign_wizard',
                'label' => __('Campaign Wizard'),
                'check' => true,
                'type' => 'boolean',
                'raw' => 0,
            ]);
        });
    }
}
