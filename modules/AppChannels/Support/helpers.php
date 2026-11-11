<?php

use Modules\AppChannels\Support\ChannelCatalog;
use Modules\AppTeams\Support\TeamWorkspaceAccess;
use Illuminate\Support\Facades\Schema;

if (! function_exists('channel_registry')) {
    function channel_registry(): ChannelCatalog
    {
        return app(ChannelCatalog::class);
    }
}

if (! function_exists('register_channel_capability')) {
    function register_channel_capability(string $key, array $definition): ChannelCatalog
    {
        return channel_registry()->register($key, $definition + [
            'provider_key' => $definition['provider_key'] ?? data_get($definition, 'provider.key'),
            'supports_publishing' => $definition['supports_publishing'] ?? true,
        ]);
    }
}

if (! function_exists('register_channel_module')) {
    function register_channel_module(string $capabilityKey, array $definition): ChannelCatalog
    {
        $provider = $definition['provider'] ?? [];
        $providerKey = (string) ($provider['key'] ?? $definition['provider_key'] ?? '');

        if ($providerKey !== '' && $provider !== []) {
            register_integration_item($providerKey, array_diff_key($provider, ['key' => true]));
        }

        $capability = $definition['capability'] ?? $definition;

        if ($providerKey !== '' && ! isset($capability['provider_key'])) {
            $capability['provider_key'] = $providerKey;
        }

        return register_channel_capability($capabilityKey, $capability);
    }
}

if (! function_exists('channel_module_is_marketplace_addon')) {
    function channel_module_is_marketplace_addon(?string $moduleName): bool
    {
        if (! config('app.demo_mode')) {
            return false;
        }

        $moduleName = trim((string) $moduleName);

        if ($moduleName === '') {
            return false;
        }

        static $marketplaceModuleNames = null;

        if ($marketplaceModuleNames === null) {
            $marketplaceModuleNames = [];

            try {
                if (Schema::hasTable('marketplace_packages')) {
                    $marketplaceModuleNames = \Modules\AdminMarketplace\Models\MarketplacePackage::query()
                        ->whereNotNull('module_name')
                        ->pluck('module_name')
                        ->filter()
                        ->mapWithKeys(fn ($name) => [(string) $name => true])
                        ->all();
                }
            } catch (\Throwable) {
                $marketplaceModuleNames = [];
            }
        }

        return isset($marketplaceModuleNames[$moduleName]);
    }
}

if (! function_exists('channel_capability_is_marketplace_addon')) {
    function channel_capability_is_marketplace_addon(array $capability): bool
    {
        return channel_module_is_marketplace_addon(data_get($capability, 'module.name'));
    }
}

if (! function_exists('channel_capabilities')) {
    function channel_capabilities(): array
    {
        return channel_registry()->capabilities();
    }
}

if (! function_exists('channel_capability')) {
    function channel_capability(string $key): array
    {
        return channel_registry()->get($key);
    }
}

if (! function_exists('channel_capabilities_for_provider')) {
    function channel_capabilities_for_provider(string $providerKey): array
    {
        return collect(channel_capabilities())
            ->filter(fn (array $capability): bool => ($capability['provider_key'] ?? null) === $providerKey)
            ->values()
            ->all();
    }
}

if (! function_exists('publishable_channel_capability_keys')) {
    function publishable_channel_capability_keys($user = null): array
    {
        $planOwner = $user;

        if ($user) {
            $team = TeamWorkspaceAccess::activeTeam($user);
            $planOwner = $team?->owner ?: $user;
        }

        $keys = collect(channel_capabilities())
            ->filter(fn (array $capability): bool => (bool) ($capability['supports_publishing'] ?? true))
            ->keys()
            ->values()
            ->all();

        $planPermissions = (array) ($planOwner?->plan?->permissions ?? []);
        $hasExplicitPublishingSelections = collect($keys)
            ->contains(fn (string $key): bool => array_key_exists($key, $planPermissions));

        if (! $hasExplicitPublishingSelections) {
            return $keys;
        }

        return collect($keys)
            ->filter(fn (string $key): bool => $planOwner?->hasPlanFeature($key) ?? false)
            ->values()
            ->all();
    }
}

if (! function_exists('channel_provider_cards')) {
    function channel_provider_cards(): array
    {
        $providers = integration_items();

        return collect(channel_capabilities())
            ->groupBy(fn (array $capability): string => (string) ($capability['provider_key'] ?? ''))
            ->map(function ($capabilities, string $providerKey) use ($providers): ?array {
                $provider = $providers[$providerKey] ?? null;

                if ($provider === null) {
                    return null;
                }

                $state = integration_item_state($providerKey);

                return [
                    'key' => $providerKey,
                    'label' => $provider['label'],
                    'icon' => $provider['icon'],
                    'color' => $provider['color'],
                    'description' => $provider['description'],
                    'is_enabled' => $state['enabled'],
                    'is_configured' => $state['configured'],
                    'is_ready' => $state['ready'],
                    'missing_fields' => $state['missing_fields'],
                    'capabilities' => collect($capabilities)
                        ->sortBy(fn (array $capability) => $capability['order'] ?? 100)
                        ->map(function (array $capability): array {
                            $capability['is_addon'] = channel_capability_is_marketplace_addon($capability);

                            return $capability;
                        })
                        ->values()
                        ->all(),
                    'has_addon_capabilities' => collect($capabilities)
                        ->contains(fn (array $capability): bool => channel_capability_is_marketplace_addon($capability)),
                ];
            })
            ->filter()
            ->sortBy(fn (array $provider) => $provider['order'] ?? 100)
            ->values()
            ->all();
    }
}

if (! function_exists('social_integration_registry')) {
    function social_integration_registry(): ChannelCatalog
    {
        return channel_registry();
    }
}

if (! function_exists('register_social_integration')) {
    function register_social_integration(string $key, array $definition): ChannelCatalog
    {
        return register_channel_capability($key, $definition);
    }
}

if (! function_exists('social_integrations')) {
    function social_integrations(): array
    {
        return channel_capabilities();
    }
}
