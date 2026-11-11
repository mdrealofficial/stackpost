<?php

namespace Modules\AdminSettings\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;

class SettingsPageRegistry
{
    /**
     * @var array<string, array{key:string,label:?string,order:int,items:array<int, array<string,mixed>>}>
     */
    protected array $sections = [];

    public function section(string $key, ?string $label = null, int $order = 100): static
    {
        $section = $this->sections[$key] ?? [
            'key' => $key,
            'label' => $label,
            'order' => $order,
            'items' => [],
        ];

        if ($label !== null) {
            $section['label'] = $label;
        }

        $section['order'] = $order;

        $this->sections[$key] = $section;

        return $this;
    }

    public function register(string $sectionKey, array $item): static
    {
        if (! isset($this->sections[$sectionKey])) {
            $this->section($sectionKey);
        }

        $item['order'] = (int) ($item['order'] ?? 100);

        $this->sections[$sectionKey]['items'][] = $item;

        return $this;
    }

    /**
     * @return array<int, array{key:string,label:?string,items:array<int, array<string,mixed>>}>
     */
    public function sections(): array
    {
        $sections = array_values($this->sections);

        usort($sections, fn (array $a, array $b) => $a['order'] <=> $b['order']);

        return array_values(array_map(function (array $section): array {
            $items = $section['items'];
            usort($items, fn (array $a, array $b) => ($a['order'] ?? 100) <=> ($b['order'] ?? 100));
            $items = array_values(array_filter($items, fn (array $item): bool => $this->isVisible($item)));

            return [
                'key' => $section['key'],
                'label' => $section['label'],
                'items' => array_values(array_map(fn (array $item) => $this->normalizeItem($item), $items)),
            ];
        }, $sections));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function items(): array
    {
        return collect($this->sections())
            ->flatMap(fn (array $section) => $section['items'])
            ->values()
            ->all();
    }

    public function defaultUrl(): string
    {
        $firstItem = $this->items()[0] ?? null;

        if (is_array($firstItem) && isset($firstItem['route'])) {
            return $firstItem['route'];
        }

        return url('/admin/settings');
    }

    protected function normalizeItem(array $item): array
    {
        if (! isset($item['route']) && isset($item['route_name']) && Route::has($item['route_name'])) {
            $item['route'] = $this->resolveRouteUrl((string) $item['route_name']).(isset($item['fragment']) ? '#'.ltrim((string) $item['fragment'], '#') : '');
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
        $routeName = $this->currentRouteName($request);

        if ($routeName !== null) {
            $activeWhen = Arr::wrap($item['active_when'] ?? []);

            if ($activeWhen !== [] && collect($activeWhen)->contains(fn ($pattern) => str($routeName)->is($pattern))) {
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

        if ($visible instanceof \Closure) {
            return (bool) $visible($item);
        }

        return (bool) $visible;
    }

    protected function currentRouteName(?Request $request): ?string
    {
        if (! $request instanceof Request) {
            return null;
        }

        $current = $request->route()?->getName();

        if (filled($current) && $current !== 'livewire.update') {
            return $current;
        }

        $referer = (string) $request->headers->get('referer', '');

        if ($referer === '') {
            return $current ?: null;
        }

        $path = parse_url($referer, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return $current ?: null;
        }

        try {
            $matchedRoute = app('router')->getRoutes()->match(Request::create($path, 'GET'));

            return $matchedRoute?->getName() ?: ($current ?: null);
        } catch (\Throwable) {
            return $current ?: null;
        }
    }
}
