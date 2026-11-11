<?php

namespace Modules\AppProfile\Providers;

use Illuminate\Support\ServiceProvider;

class AppProfileServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.appprofile');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'appprofile');

        register_user_dashboard_item('app-profile.welcome', [
            'title' => 'Welcome',
            'view' => 'appprofile::dashboard.welcome',
            'region' => 'welcome',
            'order' => 10,
            'data' => fn ($user) => (function () use ($user) {
                $user?->loadMissing(['plan', 'nextPlan']);

                $credits = credit_summary($user);
                $used = (int) ($credits['used'] ?? 0);
                $remaining = $credits['remaining'];
                $unlimited = (bool) ($credits['unlimited'] ?? false);
                $planUsed = (int) ($credits['plan_used'] ?? $used);
                $limit = $unlimited ? null : max(0, (int) ($credits['plan_limit'] ?? $credits['limit'] ?? 0));
                $usagePercent = $unlimited
                    ? 0
                    : ($limit > 0 ? min(100, (int) round(($planUsed / $limit) * 100)) : 0);

                return [
                    'user' => $user,
                    'todayLabel' => now()->translatedFormat('d M Y'),
                    'planName' => $user?->plan?->name ?? __('No plan assigned'),
                    'planExpiryLabel' => $user?->plan_expires_at
                        ? __('Expires: :date', ['date' => $user->plan_expires_at->format('Y-m-d')])
                        : __('Expires: Unlimited'),
                    'credits' => [
                        'used' => $used,
                        'plan_used' => $planUsed,
                        'remaining' => $remaining,
                        'unlimited' => $unlimited,
                        'limit' => $limit,
                        'usage_percent' => $usagePercent,
                    ],
                ];
            })(),
            'visible' => fn ($user) => $user !== null,
        ]);

        register_header_item([
            'view' => 'appprofile::partials.header-user-menu',
            'position' => 'end',
            'order' => 80,
        ], 'user');

        register_header_item([
            'view' => 'appprofile::partials.header-user-menu',
            'position' => 'end',
            'order' => 80,
        ], 'admin');

    }
}
