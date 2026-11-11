<?php

namespace Modules\AppBulkPosts\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\AppTeams\Support\TeamPermissionRegistry;
use Modules\AppTeams\Support\TeamWorkspaceAccess;

class AppBulkPostsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.appbulkposts');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'appbulkposts');

        TeamPermissionRegistry::registerModule('bulk_posts', [
            'label' => __('Bulk posts'),
            'configurable' => true,
            'enabled_by_default' => true,
            'permissions' => [
                'bulk_posts.view' => [
                    'label' => __('Access bulk posts'),
                    'defaults' => [
                        'admin' => ['bulk_posts.view'],
                        'editor' => ['bulk_posts.view'],
                    ],
                ],
            ],
        ]);

        register_plan_permission([
            'key' => 'bulk_posts',
            'label' => __('Bulk Posts'),
            'type' => 'config',
            'order' => 100,
            'fields' => [
                [
                    'key' => 'max_bulk_posts_per_csv',
                    'label' => __('Maximum Posts per CSV Import'),
                    'type' => 'number',
                    'default' => 100,
                    'description' => __('Allow users to create bulk posts by uploading a CSV file. Enter -1 for unlimited rows per import.'),
                ],
            ],
        ]);

        register_user_sidebar_item('workspace', [
            'label' => 'Bulk Import',
            'route_name' => 'portal.bulk-posts',
            'active_when' => ['portal.bulk-posts', 'portal.bulk-posts.*'],
            'icon' => 'fa-light fa-file-csv',
            'order' => 14,
            'visible' => function (): bool {
                $user = auth()->user();
                $team = TeamWorkspaceAccess::activeTeam($user);
                $planOwner = $team?->owner ?: $user;

                if (! TeamWorkspaceAccess::teamHasModule($team, 'bulk_posts')) {
                    return false;
                }

                if (! TeamWorkspaceAccess::hasPermission($user, 'bulk_posts.view', $team)) {
                    return false;
                }

                return $planOwner?->canUsePlanFeature('bulk_posts') ?? false;
            },
        ]);

        $this->app->booted(function (): void {
            \Pricing::addSubFeatures([
                ['sort' => 60, 'parent' => 'features', 'tab_id' => 'content', 'tab_name' => __('Content'), 'key' => 'bulk_posts', 'label' => __('Bulk Posts'), 'check' => true, 'type' => 'boolean', 'raw' => 0],
                ['sort' => 70, 'parent' => 'features', 'tab_id' => 'content', 'tab_name' => __('Content'), 'key' => 'max_bulk_posts_per_csv', 'label' => __('Bulk rows / CSV'), 'check' => true, 'type' => 'number', 'raw' => 0],
            ]);
        });
    }
}
