<?php

namespace Modules\AdminUser\Providers;

use Illuminate\Support\ServiceProvider;

class AdminUserServiceProvider extends ServiceProvider
{
    /**
     * Register module services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.adminuser');
    }

    /**
     * Bootstrap module services.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'adminuser');

        register_setting_item('general', [
            'label' => 'Authentication Rules',
            'description' => 'Control sign-up, account changes, and social sign-in providers.',
            'route_name' => 'settings.auth',
            'active_when' => ['settings.auth'],
            'order' => 14,
        ]);

        register_sidebar_section('ai-settings', 'AI Center', 20);
        register_sidebar_section('user-portal', 'User Portal', 30);

        register_sidebar_item('user-portal', [
            'label' => 'Users',
            'route_name' => 'admin-users.index',
            'active_when' => ['admin-users.*'],
            'icon' => 'fa-light fa-user-group',
            'order' => 10,
            'permission' => 'admin-users.view',
        ]);

        register_sidebar_item('user-portal', [
            'label' => 'Teams',
            'route_name' => 'admin-user-teams.index',
            'active_when' => ['admin-user-teams.*'],
            'icon' => 'fa-light fa-people-group',
            'order' => 20,
            'permission' => 'admin-user-teams.view',
        ]);

        register_sidebar_item('user-portal', [
            'label' => 'User Report',
            'route_name' => 'admin-user-report.index',
            'active_when' => ['admin-user-report.*'],
            'icon' => 'fa-light fa-chart-column',
            'order' => 30,
            'permission' => 'admin-user-report.view',
        ]);

        register_sidebar_item('user-portal', [
            'label' => 'User Logs',
            'route_name' => 'admin-user-logs.index',
            'active_when' => ['admin-user-logs.*'],
            'icon' => 'fa-light fa-clipboard-list-check',
            'order' => 35,
            'permission' => 'admin-user-logs.view',
        ]);

        register_sidebar_item('user-portal', [
            'label' => 'User Roles',
            'route_name' => 'admin-user-roles.index',
            'active_when' => ['admin-user-roles.*'],
            'icon' => 'fa-light fa-user-shield',
            'order' => 40,
            'permission' => 'admin-user-roles.view',
        ]);

        register_user_sidebar_section('overview', 'Overview', 10);
        register_user_sidebar_section('workspace', 'Workspace', 20);
        register_user_sidebar_section('content-tools', 'Content Tools', 30);
        register_user_sidebar_section('library', 'Library', 40);
        register_user_sidebar_section('help-desk', 'Help Desk', 50);
        register_user_sidebar_section('portal', 'Apps', 90);

        register_user_sidebar_item('overview', [
            'label' => 'Dashboard',
            'route_name' => 'portal.dashboard',
            'active_when' => ['portal.dashboard'],
            'icon' => 'dashboard',
            'order' => 10,
        ]);

        register_admin_dashboard_item('admin-users.snapshot', [
            'title' => 'Users',
            'view' => 'adminuser::dashboard.snapshot',
            'width' => 'full',
            'order' => 20,
            'data' => fn () => [
                'signupReport' => (function () {
                    $query = \Modules\AdminUser\Models\User::query();
                    $today = now()->startOfDay();
                    $weekStart = now()->subDays(6)->startOfDay();

                    $daily = collect(range(6, 0))->map(function (int $offset) use ($query) {
                        $date = now()->subDays($offset)->startOfDay();

                        return [
                            'label' => $date->format('d M'),
                            'value' => (clone $query)
                                ->whereBetween('created_at', [$date, (clone $date)->endOfDay()])
                                ->count(),
                        ];
                    })->values();

                    return [
                        'today' => (clone $query)->whereBetween('created_at', [$today, (clone $today)->endOfDay()])->count(),
                        'week' => (clone $query)->where('created_at', '>=', $weekStart)->count(),
                        'month' => (clone $query)->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count(),
                        'total' => (clone $query)->count(),
                        'daily' => $daily,
                    ];
                })(),
                'metrics' => [
                    'users' => \Modules\AdminUser\Models\User::query()->count(),
                    'teams' => \Modules\AdminUser\Models\Team::query()->count(),
                    'roles' => \Modules\AdminUser\Models\AdminRole::query()->count(),
                    'new_users' => \Modules\AdminUser\Models\User::query()->where('created_at', '>=', now()->subDays(7))->count(),
                ],
                'route' => route('admin-users.index'),
                'reportRoute' => route('admin-user-report.index'),
            ],
        ]);

    }
}
