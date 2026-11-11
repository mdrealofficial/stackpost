<?php

namespace Modules\AppAIAssistant\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AppTeams\Support\TeamPermissionRegistry;

class AppAIAssistantServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.appaiassistant');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'appaiassistant');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        TeamPermissionRegistry::registerModule('ai_assistant', [
            'label' => __('AI Assistant'),
            'configurable' => false,
            'enabled_by_default' => true,
            'permissions' => [
                'assistant.use' => [
                    'label' => __('Use AI Assistant'),
                    'defaults' => [
                        'admin' => ['assistant.use'],
                        'editor' => ['assistant.use'],
                        'member' => ['assistant.use'],
                    ],
                ],
            ],
        ]);

        register_credit_action([
            'key' => 'ai_assistant_turn',
            'plan_key' => 'credit_cost_ai_assistant_turn',
            'label' => __('AI Assistant Analysis Turn'),
            'default_cost' => 1,
            'order' => 21,
            'description' => __('Credits deducted for an AI Assistant conversation or workspace analysis turn. Tool actions use their existing credit action instead.'),
        ]);

        register_plan_permission([
            'key' => 'ai_studio',
            'fields' => [
                [
                    'key' => 'ai_assistant',
                    'label' => __('Workspace AI Assistant'),
                    'type' => 'boolean',
                    'default' => '1',
                    'order' => 5,
                ],
            ],
        ]);

        $this->app->booted(function (): void {
            \Pricing::addSubFeatures([
                'sort' => 80,
                'parent' => 'features',
                'tab_id' => 'ai_studio',
                'tab_name' => __('AI Studio'),
                'standalone' => true,
                'standalone_sort' => 200,
                'standalone_key' => 'ai_studio',
                'key' => 'ai_assistant',
                'label' => __('Workspace AI Assistant'),
                'check' => true,
                'type' => 'boolean',
                'raw' => 0,
            ]);
        });
    }
}
