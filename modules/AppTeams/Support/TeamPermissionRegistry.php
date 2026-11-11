<?php

namespace Modules\AppTeams\Support;

class TeamPermissionRegistry
{
    /** @var array<string, array<string, mixed>> */
    protected static array $modules = [];

    /** @var array<string, array<string, mixed>> */
    protected static array $permissions = [];

    public static function registerModule(string $key, array $definition): void
    {
        $existing = static::$modules[$key] ?? [];
        $module = array_merge([
            'key' => $key,
            'label' => str($key)->headline()->value(),
            'configurable' => true,
            'enabled_by_default' => true,
            'permissions' => [],
            'defaults' => [],
        ], $existing, $definition);

        static::$modules[$key] = $module;

        foreach ((array) ($module['permissions'] ?? []) as $permissionKey => $permissionDefinition) {
            if (is_string($permissionDefinition)) {
                $permissionDefinition = ['label' => $permissionDefinition];
            }

            static::registerPermission((string) $permissionKey, array_merge(
                ['module' => $key],
                (array) $permissionDefinition
            ));
        }
    }

    public static function registerPermission(string $key, array $definition): void
    {
        $existing = static::$permissions[$key] ?? [];

        static::$permissions[$key] = array_merge([
            'key' => $key,
            'label' => str($key)->replace('.', ' ')->headline()->value(),
            'module' => null,
            'defaults' => [],
        ], $existing, $definition);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function modules(bool $configurableOnly = false): array
    {
        $modules = collect(static::$modules)
            ->filter(fn (array $module) => ! $configurableOnly || (bool) ($module['configurable'] ?? true))
            ->sortBy(fn (array $module, string $key) => (string) ($module['label'] ?? $key))
            ->mapWithKeys(fn (array $module, string $key) => [$key => $module])
            ->all();

        return $modules;
    }

    /**
     * @return array<string, string>
     */
    public static function moduleLabels(bool $configurableOnly = false): array
    {
        return collect(static::modules($configurableOnly))
            ->mapWithKeys(fn (array $module, string $key) => [$key => (string) $module['label']])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function defaultEnabledModuleKeys(): array
    {
        return collect(static::modules(true))
            ->filter(fn (array $module) => (bool) ($module['enabled_by_default'] ?? true))
            ->keys()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function alwaysEnabledModuleKeys(): array
    {
        return collect(static::modules())
            ->filter(fn (array $module) => ! (bool) ($module['configurable'] ?? true))
            ->keys()
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function permissionLabels(?array $enabledModules = null): array
    {
        return collect(static::$permissions)
            ->filter(fn (array $permission) => static::permissionAvailable($permission, $enabledModules))
            ->sortBy(fn (array $permission, string $key) => (string) ($permission['label'] ?? $key))
            ->mapWithKeys(fn (array $permission, string $key) => [$key => (string) $permission['label']])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function defaultPermissionsForRole(string $role, ?array $enabledModules = null): array
    {
        return collect(static::$permissions)
            ->filter(fn (array $permission) => static::permissionAvailable($permission, $enabledModules))
            ->filter(function (array $permission) use ($role) {
                $defaults = (array) ($permission['defaults'] ?? []);

                return in_array($permission['key'], (array) ($defaults[$role] ?? []), true)
                    || in_array($role, (array) ($permission['roles'] ?? []), true);
            })
            ->keys()
            ->values()
            ->all();
    }

    public static function hasPermission(string $permissionKey): bool
    {
        return array_key_exists($permissionKey, static::$permissions);
    }

    public static function permissionModule(string $permissionKey): ?string
    {
        return static::$permissions[$permissionKey]['module'] ?? null;
    }

    protected static function permissionAvailable(array $permission, ?array $enabledModules = null): bool
    {
        $moduleKey = $permission['module'] ?? null;

        if (! $moduleKey) {
            return true;
        }

        $module = static::$modules[$moduleKey] ?? null;

        if (! $module) {
            return false;
        }

        if (! (bool) ($module['configurable'] ?? true)) {
            return true;
        }

        if ($enabledModules === null) {
            return true;
        }

        return in_array($moduleKey, $enabledModules, true);
    }
}
