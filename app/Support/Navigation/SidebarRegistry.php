<?php

namespace App\Support\Navigation;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\AdminSettings\Support\OptionStore;

class SidebarRegistry
{
    /**
     * @var array<string, array<string, array{key:string,label:?string,order:int,items:array<int, array<string,mixed>>}>>
     */
    protected array $sections = [];

    protected ?array $marketplaceModuleNames = null;

    public function __construct(
        protected OptionStore $options,
    ) {}

    public function section(string $area, string $key, ?string $label = null, int $order = 100): static
    {
        $section = $this->sections[$area][$key] ?? [
            'key' => $key,
            'label' => $label,
            'order' => $order,
            'items' => [],
        ];

        if ($label !== null) {
            $section['label'] = $label;
        }

        $section['order'] = $order;

        $this->sections[$area][$key] = $section;

        return $this;
    }

    public function register(string $area, string $sectionKey, array $item): static
    {
        if (! isset($this->sections[$area][$sectionKey])) {
            $this->section($area, $sectionKey, null);
        }

        $item['order'] = (int) ($item['order'] ?? 100);
        $item['source_module'] = $item['source_module'] ?? $this->inferSourceModule();

        $this->sections[$area][$sectionKey]['items'][] = $item;

        return $this;
    }

    /**
     * @return array<int, array{label:?string,items:array<int, array<string,mixed>>}>
     */
    public function sections(string $area = 'admin'): array
    {
        $sections = array_values($this->sections[$area] ?? []);

        usort($sections, fn (array $a, array $b) => $a['order'] <=> $b['order']);

        $resolvedSections = array_map(function (array $section): array {
            $items = $section['items'];
            usort($items, fn (array $a, array $b) => ($a['order'] ?? 100) <=> ($b['order'] ?? 100));
            $items = array_values(array_filter($items, fn (array $item): bool => $this->isVisible($item)));

            return [
                'key' => $section['key'],
                'label' => $section['label'],
                'order' => $section['order'],
                'items' => array_values(array_map(fn (array $item) => $this->normalizeItem($item), $items)),
            ];
        }, $sections);

        $resolvedSections = $this->applyOverrides($area, $resolvedSections);

        return array_values(array_filter($resolvedSections, fn (array $section): bool => $section['items'] !== []));
    }

    /**
     * @return array<int, array{key:string,label:?string,order:int,items:array<int, array<string,mixed>>}>
     */
    public function editableSections(string $area = 'admin'): array
    {
        $sections = array_values($this->sections[$area] ?? []);

        usort($sections, fn (array $a, array $b) => $a['order'] <=> $b['order']);

        $resolvedSections = array_map(function (array $section): array {
            $items = $section['items'];
            usort($items, fn (array $a, array $b) => ($a['order'] ?? 100) <=> ($b['order'] ?? 100));

            return [
                'key' => $section['key'],
                'label' => $section['label'],
                'order' => $section['order'],
                'items' => array_values(array_map(fn (array $item) => $this->normalizeItem($item, true), $items)),
            ];
        }, $sections);

        return $this->applyOverrides($area, $resolvedSections);
    }

    protected function normalizeItem(array $item, bool $forEditor = false): array
    {
        $item['key'] = $this->resolveItemKey($item);
        $item = $this->applyMarketplaceModuleBadge($item);

        if (! isset($item['route']) && isset($item['route_name']) && Route::has($item['route_name'])) {
            $item['route'] = $this->resolveRouteUrl((string) $item['route_name']).(isset($item['fragment']) ? '#'.ltrim((string) $item['fragment'], '#') : '');
        }

        $children = $item['children'] ?? [];

        if (($item['children_resolver'] ?? null) instanceof \Closure) {
            $children = $item['children_resolver']();
        }

        if (! $forEditor) {
            $children = array_values(array_filter($children, fn (array $child): bool => $this->isVisible($child)));
        }

        $children = array_values(array_map(fn (array $child) => $this->normalizeItem($child, $forEditor), $children));

        if ($children !== []) {
            usort($children, fn (array $a, array $b) => ($a['order'] ?? 100) <=> ($b['order'] ?? 100));
        }

        $item['children'] = $children;
        unset($item['children_resolver']);
        $item['active'] = $forEditor ? false : $this->resolveActive($item);

        return $item;
    }

    protected function resolveItemKey(array $item): string
    {
        $raw = (string) ($item['key'] ?? $item['route_name'] ?? '');

        if ($raw !== '') {
            return $raw;
        }

        $raw = trim((string) ($item['label'] ?? 'item'));

        return Str::slug($raw, '.') ?: 'item';
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

        foreach ($item['children'] ?? [] as $child) {
            if ($this->resolveActive($child)) {
                return true;
            }
        }

        return (bool) ($item['active'] ?? false);
    }

    protected function resolveRouteUrl(string $routeName): string
    {
        $request = request();

        if ($request instanceof Request) {
            return rtrim($request->getBaseUrl(), '/').route($routeName, absolute: false);
        }

        return route($routeName);
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

    /**
     * @param  array<int, array<string,mixed>>  $sections
     * @return array<int, array<string,mixed>>
     */
    protected function applyOverrides(string $area, array $sections): array
    {
        $payload = $this->menuOverrides();
        $areaPayload = is_array($payload[$area] ?? null) ? $payload[$area] : [];
        $sectionOverrides = collect((array) ($areaPayload['sections'] ?? []))
            ->filter(fn ($section) => is_array($section) && filled($section['key'] ?? null))
            ->keyBy(fn ($section) => (string) $section['key']);
        $sectionPositions = $sectionOverrides->keys()->values()->flip();

        $sections = array_map(function (array $section) use ($sectionOverrides): array {
            $override = $sectionOverrides->get((string) $section['key'], []);

            if (is_string($override['label'] ?? null)) {
                $section['label'] = trim((string) $override['label']);
            }

            $itemOverrides = collect((array) ($override['items'] ?? []))
                ->filter(fn ($item) => is_array($item) && filled($item['key'] ?? null))
                ->keyBy(fn ($item) => (string) $item['key']);
            $itemPositions = $itemOverrides->keys()->values()->flip();

            $section['items'] = array_map(function (array $item) use ($itemOverrides): array {
                $overrideItem = $itemOverrides->get((string) $item['key'], []);

                if (is_string($overrideItem['label'] ?? null)) {
                    $item['label'] = trim((string) $overrideItem['label']);
                }

                return $item;
            }, $section['items']);

            usort($section['items'], function (array $a, array $b) use ($itemPositions): int {
                $positionA = $itemPositions[(string) ($a['key'] ?? '')] ?? null;
                $positionB = $itemPositions[(string) ($b['key'] ?? '')] ?? null;

                if ($positionA !== null && $positionB !== null) {
                    return $positionA <=> $positionB;
                }

                if ($positionA !== null) {
                    return -1;
                }

                if ($positionB !== null) {
                    return 1;
                }

                return ((int) ($a['order'] ?? 100)) <=> ((int) ($b['order'] ?? 100));
            });

            return $section;
        }, $sections);

        usort($sections, function (array $a, array $b) use ($sectionPositions): int {
            $positionA = $sectionPositions[(string) ($a['key'] ?? '')] ?? null;
            $positionB = $sectionPositions[(string) ($b['key'] ?? '')] ?? null;

            if ($positionA !== null && $positionB !== null) {
                return $positionA <=> $positionB;
            }

            if ($positionA !== null) {
                return -1;
            }

            if ($positionB !== null) {
                return 1;
            }

            return ((int) ($a['order'] ?? 100)) <=> ((int) ($b['order'] ?? 100));
        });

        return $sections;
    }

    protected function menuOverrides(): array
    {
        $stored = $this->options->get('admin_menu_sidebar_overrides', []);

        if (is_string($stored)) {
            $decoded = json_decode($stored, true);
            $stored = is_array($decoded) ? $decoded : [];
        }

        return is_array($stored) ? $stored : [];
    }

    protected function inferSourceModule(): ?string
    {
        $modulesPath = str_replace('\\', '/', base_path('modules')).'/';

        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 12) as $frame) {
            $file = str_replace('\\', '/', (string) ($frame['file'] ?? ''));

            if ($file === '' || ! str_starts_with(strtolower($file), strtolower($modulesPath))) {
                continue;
            }

            $relative = substr($file, strlen($modulesPath));
            $moduleName = strtok($relative, '/');

            return is_string($moduleName) && $moduleName !== '' ? $moduleName : null;
        }

        return null;
    }

    protected function applyMarketplaceModuleBadge(array $item): array
    {
        if (! config('app.demo_mode')) {
            return $item;
        }

        $moduleName = trim((string) ($item['source_module'] ?? ''));

        if ($moduleName === '' || ! isset($this->marketplaceModuleNames()[$moduleName])) {
            return $item;
        }

        $item['badge'] = $item['badge'] ?? __('Addon');

        return $item;
    }

    /** @return array<string, bool> */
    protected function marketplaceModuleNames(): array
    {
        if ($this->marketplaceModuleNames !== null) {
            return $this->marketplaceModuleNames;
        }

        $this->marketplaceModuleNames = [];

        try {
            if (! Schema::hasTable('marketplace_packages')) {
                return $this->marketplaceModuleNames;
            }

            $this->marketplaceModuleNames = \Modules\AdminMarketplace\Models\MarketplacePackage::query()
                ->whereNotNull('module_name')
                ->pluck('module_name')
                ->filter()
                ->mapWithKeys(fn ($moduleName) => [(string) $moduleName => true])
                ->all();
        } catch (\Throwable) {
            $this->marketplaceModuleNames = [];
        }

        return $this->marketplaceModuleNames;
    }
}
