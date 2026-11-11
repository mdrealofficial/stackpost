<?php

namespace Modules\AdminSupport\Providers;

use Illuminate\Support\ServiceProvider;

class AdminSupportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.adminsupport');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'adminsupport');

        register_sidebar_item('main', [
            'label' => 'Support',
            'route_name' => 'admin-support.index',
            'active_when' => ['admin-support.*'],
            'icon' => 'fa-light fa-life-ring',
            'order' => 16,
            'children' => [
                [
                    'label' => 'New Ticket',
                    'route_name' => 'admin-support.create',
                    'active_when' => ['admin-support.create'],
                    'order' => 10,
                ],
                [
                    'label' => 'View All Tickets',
                    'route_name' => 'admin-support.index',
                    'active_when' => ['admin-support.index', 'admin-support.show'],
                    'order' => 20,
                ],
                [
                    'label' => 'Manage Categories',
                    'route_name' => 'admin-support.categories.index',
                    'active_when' => ['admin-support.categories.*'],
                    'order' => 30,
                ],
                [
                    'label' => 'Manage Labels',
                    'route_name' => 'admin-support.labels.index',
                    'active_when' => ['admin-support.labels.*'],
                    'order' => 40,
                ],
                [
                    'label' => 'Manage Types',
                    'route_name' => 'admin-support.types.index',
                    'active_when' => ['admin-support.types.*'],
                    'order' => 50,
                ],
            ],
        ]);

        register_admin_dashboard_item('admin-support.snapshot', [
            'title' => 'Support queue',
            'view' => 'adminsupport::dashboard.snapshot',
            'width' => 'full',
            'order' => 50,
            'data' => fn () => [
                'metrics' => [
                    'total' => \Modules\AdminSupport\Models\SupportTicket::query()->count(),
                    'open' => \Modules\AdminSupport\Models\SupportTicket::query()->where('status', 1)->count(),
                    'resolved' => \Modules\AdminSupport\Models\SupportTicket::query()->where('status', 2)->count(),
                    'unread' => \Modules\AdminSupport\Models\SupportTicket::query()->where('admin_read', false)->count(),
                ],
                'route' => route('admin-support.index'),
            ],
        ]);
    }
}
