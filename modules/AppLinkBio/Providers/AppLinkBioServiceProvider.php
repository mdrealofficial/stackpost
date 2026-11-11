<?php

namespace Modules\AppLinkBio\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AppLinkBio\Support\LinkBioAccess;

class AppLinkBioServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.applinkbio');
        $this->app->singleton(LinkBioAccess::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'applinkbio');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        register_credit_action([
            'key' => 'link_bio_ai_assistant',
            'plan_key' => 'credit_cost_link_bio_ai_assistant',
            'label' => __('Link Bio AI Assistant'),
            'default_cost' => 1,
            'order' => 30,
            'description' => __('Credits deducted each time AI generates a Link Bio draft.'),
        ]);

        register_user_sidebar_item('content-tools', [
            'label' => 'Link Bio',
            'route_name' => 'portal.link-bio.index',
            'active_when' => ['portal.link-bio.*'],
            'icon' => 'fa-light fa-link-horizontal',
            'order' => 45,
            'visible' => fn () => app(LinkBioAccess::class)->enabled(auth()->user()),
        ]);

        register_setting_item('general', [
            'label' => 'Link Bio',
            'description' => 'Set default templates, publishing status, and branding text for new bio pages.',
            'route_name' => 'settings.link-bio',
            'active_when' => ['settings.link-bio'],
            'order' => 108,
        ]);

        register_plan_permission([
            'key' => 'link_bio',
            'label' => __('Link Bio'),
            'type' => 'config',
            'order' => 145,
            'fields' => [
                [
                    'key' => 'max_link_bio_pages',
                    'label' => __('Max. bio pages'),
                    'type' => 'number',
                    'default' => 1,
                ],
                [
                    'key' => 'link_bio_remove_branding',
                    'label' => __('Remove Link Bio branding'),
                    'type' => 'boolean',
                    'default' => '0',
                ],
                [
                    'key' => 'link_bio_ai_assistant',
                    'label' => __('AI Bio Assistant'),
                    'type' => 'boolean',
                    'default' => '0',
                ],
            ],
        ]);

        $this->app->booted(function (): void {
            \Pricing::addSubFeatures([
                [
                    'sort' => 240,
                    'parent' => 'features',
                    'tab_id' => 'marketing',
                    'tab_name' => __('Marketing'),
                    'key' => 'link_bio',
                    'label' => __('Link Bio'),
                    'check' => true,
                    'type' => 'boolean',
                    'raw' => 0,
                ],
                [
                    'sort' => 250,
                    'parent' => 'features',
                    'tab_id' => 'marketing',
                    'tab_name' => __('Marketing'),
                    'key' => 'max_link_bio_pages',
                    'label' => __('Max. bio pages'),
                    'check' => true,
                    'type' => 'number',
                    'raw' => 0,
                ],
                [
                    'sort' => 251,
                    'parent' => 'features',
                    'tab_id' => 'marketing',
                    'tab_name' => __('Marketing'),
                    'key' => 'link_bio_ai_assistant',
                    'label' => __('AI Bio Assistant'),
                    'check' => true,
                    'type' => 'boolean',
                    'raw' => 0,
                ],
                [
                    'sort' => 252,
                    'parent' => 'features',
                    'tab_id' => 'marketing',
                    'tab_name' => __('Marketing'),
                    'key' => 'link_bio_remove_branding',
                    'label' => __('Remove Link Bio branding'),
                    'check' => true,
                    'type' => 'boolean',
                    'raw' => 0,
                ],
            ]);

            \Pricing::add([
                [
                    'sort' => 650,
                    'key' => 'link_bio',
                    'label' => __('Link Bio'),
                    'check' => true,
                    'type' => 'boolean',
                    'raw' => 0,
                ],
                [
                    'sort' => 651,
                    'key' => 'link_bio_ai_assistant',
                    'label' => __('AI Bio Assistant'),
                    'check' => true,
                    'type' => 'boolean',
                    'raw' => 0,
                ],
            ]);
        });
    }
}
