<?php

namespace Modules\AdminUser\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class AdminPermissionCatalog
{
    /**
     * @return array<string, array{label:string,permissions:array<int, array{key:string,label:string}>}>
     */
    public static function groups(): array
    {
        $groups = [];

        foreach (self::catalogItems() as $item) {
            $routeNames = self::matchedRouteNames($item);
            $groupKey = self::baseGroupKey((string) ($item['route_name'] ?? ''));

            if ($routeNames === [] && ! empty($item['route_name'])) {
                $routeNames = [(string) $item['route_name']];
            }

            foreach ($routeNames as $routeName) {
                $action = self::actionForRoute($routeName, $routeName === ($item['route_name'] ?? null));

                if (! $action) {
                    continue;
                }

                if ($groupKey === '') {
                    $permission = self::permissionForRoute($routeName);
                    $groupKey = $permission ? Str::beforeLast($permission, '.') : '';
                }

                if ($groupKey === '') {
                    continue;
                }

                if (! isset($groups[$groupKey])) {
                    $groups[$groupKey] = [
                        'label' => (string) ($item['label'] ?? self::labelFromPermissionGroup($groupKey)),
                        'permissions' => [],
                    ];
                }

                $groups[$groupKey]['permissions'][$action] = [
                    'key' => $groupKey.'.'.$action,
                    'label' => self::labelForAction($action),
                ];
            }
        }

        foreach ($groups as &$group) {
            $group['permissions'] = collect($group['permissions'])
                ->sortBy(fn (array $permission, string $action) => self::actionOrder($action))
                ->values()
                ->all();
        }
        unset($group);

        return $groups;
    }

    public static function permissionForRoute(?string $routeName): ?string
    {
        $routeName = trim((string) $routeName);

        if ($routeName === '' || $routeName === 'livewire.update') {
            return null;
        }

        if ($routeName === 'dashboard' || str_starts_with($routeName, 'dashboard.')) {
            return null;
        }

        if (str_starts_with($routeName, 'portal.')) {
            return null;
        }

        $action = self::actionForRoute($routeName, true);

        if ($action) {
            return self::baseGroupKey($routeName).'.'.$action;
        }

        if (str_starts_with($routeName, 'admin-') || str_starts_with($routeName, 'settings.')) {
            return $routeName.'.view';
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function catalogItems(): array
    {
        $items = [];

        foreach (sidebar_registry()->editableSections('admin') as $section) {
            foreach ($section['items'] ?? [] as $item) {
                array_push($items, ...self::flattenNavigationItem($item));
            }
        }

        foreach (settings_navigation_sections() as $section) {
            foreach ($section['items'] ?? [] as $item) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<int, array<string, mixed>>
     */
    protected static function flattenNavigationItem(array $item): array
    {
        $children = array_values(array_filter($item['children'] ?? [], fn ($child) => is_array($child)));

        if ($children === []) {
            return [$item];
        }

        $items = [];

        foreach ($children as $child) {
            $items = [...$items, ...self::flattenNavigationItem($child)];
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<int, string>
     */
    protected static function matchedRouteNames(array $item): array
    {
        $patterns = collect(array_merge(
            Arr::wrap($item['active_when'] ?? []),
            Arr::wrap($item['route_name'] ?? []),
        ))
            ->filter(fn ($pattern) => is_string($pattern) && trim($pattern) !== '')
            ->map(fn (string $pattern) => trim($pattern))
            ->unique()
            ->values();

        if ($patterns->isEmpty()) {
            return [];
        }

        return collect(Route::getRoutes()->getRoutesByName())
            ->keys()
            ->filter(fn ($routeName) => is_string($routeName) && $routeName !== 'livewire.update')
            ->filter(function (string $routeName) use ($patterns): bool {
                return $patterns->contains(fn (string $pattern) => Str::is($pattern, $routeName));
            })
            ->values()
            ->all();
    }

    protected static function labelForAction(string $action): string
    {
        return __(Str::headline($action));
    }

    protected static function labelFromPermissionGroup(string $group): string
    {
        return __(Str::headline(str_replace(['admin-', 'settings.'], ['', ''], $group)));
    }

    protected static function baseGroupKey(string $routeName): string
    {
        $routeName = trim($routeName);

        if ($routeName === '') {
            return '';
        }

        foreach (['index', 'show', 'create', 'store', 'edit', 'update', 'destroy', 'delete', 'export', 'import'] as $suffix) {
            if (str_ends_with($routeName, '.'.$suffix)) {
                return Str::beforeLast($routeName, '.');
            }
        }

        return $routeName;
    }

    protected static function actionForRoute(string $routeName, bool $allowBaseView = false): ?string
    {
        $routeName = trim($routeName);

        if (
            $routeName === ''
            || $routeName === 'livewire.update'
            || $routeName === 'dashboard'
            || str_starts_with($routeName, 'dashboard.')
            || str_starts_with($routeName, 'portal.')
        ) {
            return null;
        }

        if ($routeName === 'admin-users.impersonate') {
            return 'edit';
        }

        if (str_ends_with($routeName, '.bulk-destroy')) {
            return 'delete';
        }

        if (str_contains($routeName, '.members.')) {
            return 'edit';
        }

        $actionMap = [
            'index' => 'view',
            'show' => 'view',
            'create' => 'create',
            'store' => 'create',
            'edit' => 'edit',
            'update' => 'edit',
            'destroy' => 'delete',
            'delete' => 'delete',
            'export' => 'export',
            'import' => 'import',
        ];

        foreach ($actionMap as $suffix => $permissionAction) {
            if (str_ends_with($routeName, '.'.$suffix)) {
                return $permissionAction;
            }
        }

        if (
            str_ends_with($routeName, '.status')
            || str_ends_with($routeName, '.toggle')
            || str_ends_with($routeName, '.reorder')
            || str_ends_with($routeName, '.run')
            || str_ends_with($routeName, '.sync')
            || str_ends_with($routeName, '.duplicate')
            || str_ends_with($routeName, '.layout.update')
        ) {
            return 'edit';
        }

        return $allowBaseView ? 'view' : null;
    }

    protected static function actionOrder(string $action): int
    {
        return match ($action) {
            'view' => 10,
            'create' => 20,
            'edit' => 30,
            'delete' => 40,
            'export' => 50,
            'import' => 60,
            default => 100,
        };
    }
}
