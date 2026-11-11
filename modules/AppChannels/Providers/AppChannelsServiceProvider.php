<?php

namespace Modules\AppChannels\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Modules\AppChannels\Support\ChannelCatalog;
use Modules\AppChannels\Support\ChannelPlanAccess;
use Modules\AppTeams\Support\TeamPermissionRegistry;
use Modules\AppTeams\Support\TeamWorkspaceAccess;

class AppChannelsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(\App\Social\SocialServiceProvider::class);
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.appchannels');
        $this->app->singleton(ChannelCatalog::class);
        $this->app->singleton(ChannelPlanAccess::class);

        $helper = __DIR__.'/../Support/helpers.php';

        if (is_file($helper)) {
            require_once $helper;
        }
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'appchannels');

        TeamPermissionRegistry::registerModule('channels', [
            'label' => __('Channels'),
            'configurable' => true,
            'enabled_by_default' => true,
            'permissions' => [
                'channel.view' => [
                    'label' => __('View channels'),
                    'defaults' => [
                        'admin' => ['channel.view'],
                        'editor' => ['channel.view'],
                    ],
                ],
                'channel.manage' => [
                    'label' => __('Manage channels'),
                    'defaults' => [
                        'admin' => ['channel.manage'],
                    ],
                ],
            ],
        ]);

        register_user_sidebar_item('workspace', [
            'label' => 'Channels',
            'route_name' => 'portal.channels',
            'active_when' => ['portal.channels'],
            'icon' => 'fa-light fa-share-nodes',
            'order' => 10,
            'visible' => fn () => app(ChannelPlanAccess::class)->channelsEnabled(auth()->user()),
        ]);

        register_user_dashboard_item('app-channels.summary', [
            'title' => 'Channels',
            'view' => 'appchannels::dashboard.user-summary',
            'width' => 'full',
            'order' => 21,
            'route_name' => 'portal.channels',
            'data' => function ($user): array {
                $query = TeamWorkspaceAccess::accessibleAccountsQuery($user);
                $accounts = (clone $query)->latest()->get();
                $limit = (int) ($user?->planLimit('max_channels', -1) ?? -1);
                $providerMap = collect(integration_items())
                    ->mapWithKeys(fn (array $item): array => [(string) ($item['key'] ?? '') => $item])
                    ->all();
                $providerCounts = (clone $query)
                    ->selectRaw('provider_key, count(*) as aggregate')
                    ->groupBy('provider_key')
                    ->pluck('aggregate', 'provider_key');
                $topProviders = $providerCounts
                    ->sortDesc()
                    ->take(6)
                    ->map(function ($count, $providerKey) use ($providerMap): array {
                        $provider = $providerMap[(string) $providerKey] ?? [];

                        return [
                            'name' => (string) ($provider['label'] ?? Str::headline((string) $providerKey)),
                            'y' => (int) $count,
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    'metrics' => [
                        'total' => $accounts->count(),
                        'active' => $accounts->where('is_active', true)->count(),
                        'paused' => $accounts->where('is_active', false)->count(),
                        'oauth' => $accounts->where('account_type', 'oauth')->count(),
                        'manual' => $accounts->where('account_type', 'manual')->count(),
                        'recent' => $accounts->filter(fn ($account) => $account->connected_at?->gte(now()->subDays(30)) ?? false)->count(),
                        'providers' => $providerCounts->count(),
                        'limit' => $limit,
                        'usage_percent' => $limit > -1 && $limit > 0 ? min(100, (int) round(($accounts->count() / $limit) * 100)) : null,
                    ],
                    'providerChart' => [[
                        'name' => __('Channels'),
                        'data' => $topProviders,
                    ]],
                    'recentAccounts' => $accounts
                        ->sortByDesc(fn ($account) => optional($account->connected_at)?->timestamp ?? 0)
                        ->take(5)
                        ->map(function ($account) use ($providerMap): array {
                            $provider = $providerMap[(string) ($account->provider_key ?? '')] ?? [];

                            return [
                                'name' => (string) ($account->display_name ?: __('Unnamed channel')),
                                'handle' => $account->username ? '@'.$account->username : null,
                                'provider' => (string) ($provider['label'] ?? Str::headline((string) ($account->provider_key ?? 'channel'))),
                                'avatar_url' => (string) ($account->avatar_url ?? ''),
                                'connected_at' => $account->connected_at?->format('Y-m-d H:i'),
                                'active' => (bool) $account->is_active,
                            ];
                        })
                        ->values()
                        ->all(),
                ];
            },
            'visible' => fn ($user) => $user && app(ChannelPlanAccess::class)->channelsEnabled($user),
        ]);

        register_plan_permission($this->planPermissionDefinition());

        $this->app->booted(function (): void {
            \Pricing::addSubFeatures([
                'sort' => 100,
                'parent' => 'features',
                'tab_id' => 'workspace',
                'tab_name' => __('Workspace'),
                'key' => 'max_channels',
                'label' => __('Channels'),
                'check' => true,
                'type' => 'number',
                'raw' => 0,
            ]);
        });
    }

    protected function planPermissionDefinition(): array
    {
        return [
            'key' => 'channels',
            'label' => __('Channels'),
            'type' => 'config',
            'order' => 20,
            'fields' => [
                [
                    'key' => 'max_channels',
                    'label' => __('Max channels'),
                    'type' => 'number',
                    'default' => 1000,
                    'description' => __('Enter the maximum number of channels allowed in this package. Set the value to -1 for unlimited channels.'),
                ],
                [
                    'key' => 'channel_count_mode',
                    'label' => __('The number of channels is calculated based on'),
                    'type' => 'choice',
                    'default' => 'entire_social_network',
                    'options' => [
                        ['value' => 'each_social_network', 'label' => __('Each social network')],
                        ['value' => 'entire_social_network', 'label' => __('Entire social network')],
                    ],
                ],
                [
                    'key' => 'allowed_channels',
                    'label' => __('Allow channels'),
                    'type' => 'checkbox_list',
                    'options' => collect(channel_capabilities())
                        ->map(fn (array $capability, string $key): array => [
                            'key' => $this->permissionKeyFromCapability($key, $capability),
                            'label' => $capability['title'] ?? $capability['label'] ?? $key,
                        ])
                        ->values()
                        ->all(),
                ],
            ],
        ];
    }

    protected function permissionKeyFromCapability(string $capabilityKey, array $capability): string
    {
        return match ($capabilityKey) {
            'facebook_page' => 'channel_facebook_pages',
            'instagram_profile' => 'channel_instagram_profiles',
            default => 'channel_'.str_replace('-', '_', $capabilityKey),
        };
    }
}
