<?php

namespace App\Support\Navigation;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;

class HeaderRegistry
{
    /**
     * @var array<string, array<int, array<string, mixed>>>
     */
    protected array $items = [];

    public function register(string $area, array $item): static
    {
        $item['order'] = (int) ($item['order'] ?? 100);
        $item['position'] = $item['position'] ?? 'end';
        $this->items[$area][] = $item;

        return $this;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function items(string $area = 'admin', ?string $position = null): array
    {
        $items = $this->items[$area] ?? [];

        if ($position !== null) {
            $items = array_values(array_filter($items, fn (array $item) => ($item['position'] ?? 'end') === $position));
        }

        $items = array_values(array_filter($items, fn (array $item) => $this->isVisible($item)));

        usort($items, fn (array $a, array $b) => ($a['order'] ?? 100) <=> ($b['order'] ?? 100));

        return array_values(array_map(fn (array $item) => $this->normalizeItem($item), $items));
    }

    protected function normalizeItem(array $item): array
    {
        if (! isset($item['route']) && isset($item['route_name']) && Route::has($item['route_name'])) {
            $item['route'] = $this->resolveRouteUrl((string) $item['route_name']);
        }

        $item['active'] = $this->resolveActive($item);

        return $item;
    }

    protected function resolveRouteUrl(string $routeName): string
    {
        $request = request();

        if ($request instanceof Request) {
            return rtrim($request->getBaseUrl(), '/').route($routeName, absolute: false);
        }

        return route($routeName);
    }

    protected function resolveActive(array $item): bool
    {
        $request = request();

        if ($request instanceof Request) {
            $activeWhen = Arr::wrap($item['active_when'] ?? []);

            if ($activeWhen !== [] && $request->routeIs(...$activeWhen)) {
                return true;
            }
        }

        return (bool) ($item['active'] ?? false);
    }

    protected function isVisible(array $item): bool
    {
        $user = auth()->user();

        if (isset($item['permission']) && $user && method_exists($user, 'hasPermission')) {
            return (bool) $user->hasPermission((string) $item['permission']);
        }

        if (isset($item['route_name']) && $user && method_exists($user, 'permissionForRoute')) {
            $permission = $user->permissionForRoute((string) $item['route_name']);

            if ($permission) {
                return (bool) $user->hasPermission($permission);
            }
        }

        $visible = $item['visible'] ?? true;

        if ($visible instanceof Closure) {
            return (bool) $visible($item);
        }

        return (bool) $visible;
    }
}
