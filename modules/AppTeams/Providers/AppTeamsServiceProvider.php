<?php

namespace Modules\AppTeams\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AdminUser\Models\Team;
use Modules\AppTeams\Support\TeamPermissionRegistry;
use Modules\AppTeams\Support\TeamWorkspaceAccess;

class AppTeamsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.appteams');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'appteams');

        TeamPermissionRegistry::registerModule('teams', [
            'label' => __('Teams'),
            'configurable' => false,
            'enabled_by_default' => true,
            'permissions' => [
                'team.manage' => [
                    'label' => __('Manage team profile'),
                    'defaults' => [
                        'admin' => ['team.manage'],
                    ],
                ],
                'member.manage' => [
                    'label' => __('Manage members and roles'),
                    'defaults' => [
                        'admin' => ['member.manage'],
                    ],
                ],
            ],
        ]);

        TeamPermissionRegistry::registerModule('chat', [
            'label' => __('Team chat'),
            'configurable' => true,
            'enabled_by_default' => true,
            'permissions' => [
                'chat.manage' => [
                    'label' => __('Create chat rooms'),
                    'defaults' => [
                        'admin' => ['chat.manage'],
                    ],
                ],
                'chat.participate' => [
                    'label' => __('Participate in chat'),
                    'defaults' => [
                        'admin' => ['chat.participate'],
                        'editor' => ['chat.participate'],
                        'member' => ['chat.participate'],
                    ],
                ],
            ],
        ]);

        register_plan_permission([
            'key' => 'teams',
            'label' => __('Teams'),
            'type' => 'config',
            'order' => 180,
            'fields' => [
                [
                    'key' => 'max_team_members',
                    'label' => __('Maximum Team Members'),
                    'type' => 'number',
                    'default' => 5,
                    'description' => __('Maximum members allowed in each team. Enter -1 for unlimited members.'),
                ],
            ],
        ]);

        register_user_sidebar_item('content-tools', [
            'label' => 'Teams',
            'route_name' => 'portal.teams',
            'active_when' => ['portal.teams', 'portal.teams.*'],
            'icon' => 'fa-light fa-user-group',
            'order' => 5,
            'visible' => function () {
                $user = auth()->user();

                if (! $user) {
                    return false;
                }

                $team = TeamWorkspaceAccess::activeTeam($user)
                    ?? Team::query()
                        ->where(function ($query) use ($user) {
                            $query->where('owner_user_id', $user->id)
                                ->orWhereHas('members', fn ($memberQuery) => $memberQuery->where('users.id', $user->id));
                        })
                        ->orderByRaw('CASE WHEN owner_user_id = ? THEN 0 ELSE 1 END', [$user->id])
                        ->orderBy('name')
                        ->first();
                $planOwner = $team?->owner ?: $user;

                return $planOwner?->canUseAnyPlanFeature(['teams', 'teams_feature']) ?? false;
            },
        ]);

        $this->app->booted(function (): void {
            \Pricing::addSubFeatures([
                'sort' => 300,
                'parent' => 'features',
                'tab_id' => 'workspace',
                'tab_name' => __('Workspace'),
                'key' => 'max_team_members',
                'label' => __('Team member'),
                'check' => true,
                'type' => 'number',
                'raw' => 0,
            ]);

            \Pricing::add([
                'sort' => 300,
                'key' => 'max_team_members',
                'label' => __('Team member'),
                'check' => true,
                'type' => 'number',
                'raw' => 0,
            ]);
        });
    }
}
