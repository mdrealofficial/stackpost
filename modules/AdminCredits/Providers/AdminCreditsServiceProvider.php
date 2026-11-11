<?php

namespace Modules\AdminCredits\Providers;

use Illuminate\Support\ServiceProvider;

class AdminCreditsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.admincredits');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'admincredits');

        register_sidebar_section('finance', 'Finance', 40);

        register_sidebar_item('finance', [
            'label' => 'Credits',
            'route_name' => 'admin-credits.packs',
            'active_when' => ['admin-credits.*'],
            'icon' => 'fa-light fa-coins',
            'order' => 11,
            'children' => [
                [
                    'label' => 'Credit Packs',
                    'route_name' => 'admin-credits.packs',
                    'active_when' => ['admin-credits.packs'],
                    'order' => 10,
                    'permission' => 'admin-credits.packs',
                ],
                [
                    'label' => 'Credit Ledger',
                    'route_name' => 'admin-credits.ledger',
                    'active_when' => ['admin-credits.ledger'],
                    'order' => 20,
                    'permission' => 'admin-credits.ledger',
                ],
                [
                    'label' => 'Credit Usage',
                    'route_name' => 'admin-credits.usage',
                    'active_when' => ['admin-credits.usage'],
                    'order' => 30,
                    'permission' => 'admin-credits.usage',
                ],
            ],
            'visible' => function (): bool {
                $user = auth()->user();

                if (! $user || ! method_exists($user, 'hasPermission')) {
                    return auth()->check();
                }

                return $user->hasPermission('admin-credits.packs')
                    || $user->hasPermission('admin-credits.ledger')
                    || $user->hasPermission('admin-credits.usage');
            },
        ]);
    }
}
