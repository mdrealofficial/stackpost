<?php

namespace App\Support\Dashboard;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Modules\AdminSettings\Support\OptionStore;

class AdminDashboardRegistry
{
    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $items = [];

    public function __construct(
        protected OptionStore $options,
    ) {}

    public function register(string $id, array $item = []): static
    {
        $item['id'] = $id;
        $item['order'] = (int) ($item['order'] ?? 100);
        $item['width'] = $item['width'] ?? 'full';
        $item['region'] = $item['region'] ?? 'main';

        $this->items[$id] = array_merge($this->items[$id] ?? [], $item);

        return $this;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function items(?Authenticatable $user = null, ?string $region = null): array
    {
        $items = collect($this->items)
            ->map(fn (array $item) => $this->normalizeItem($item, $user))
            ->filter(fn (?array $item) => $item !== null && ($region === null || (($item['region'] ?? 'main') === $region)))
            ->filter(fn (?array $item) => $item !== null)
            ->values();

        return $this->applyLayoutOrder($items, $user)
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function layout(?Authenticatable $user = null): array
    {
        $stored = $this->options->get($this->layoutKey($user), []);

        if (is_string($stored)) {
            $decoded = json_decode($stored, true);
            $stored = is_array($decoded) ? $decoded : [];
        }

        return collect($stored)
            ->filter(fn ($id) => is_string($id) && $id !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $orderedIds
     */
    public function saveLayout(?Authenticatable $user, array $orderedIds): void
    {
        $validIds = collect(array_keys($this->items));

        $payload = collect($orderedIds)
            ->filter(fn ($id) => is_string($id) && $validIds->contains($id))
            ->unique()
            ->values()
            ->all();

        $this->options->set($this->layoutKey($user), $payload);
    }

    protected function layoutKey(?Authenticatable $user = null): string
    {
        return 'admin_dashboard_layout_'.($user?->getAuthIdentifier() ?? 'guest');
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    protected function normalizeItem(array $item, ?Authenticatable $user = null): ?array
    {
        if (! ($item['id'] ?? null)) {
            return null;
        }

        if (! $this->isVisible($item, $user)) {
            return null;
        }

        if (! isset($item['route']) && isset($item['route_name']) && Route::has($item['route_name'])) {
            $item['route'] = $this->resolveRouteUrl((string) $item['route_name']);
        }

        $data = $this->evaluate($item['data'] ?? [], $item, $user);
        $item['data'] = is_array($data) ? $data : [];

        if (isset($item['render'])) {
            $item['content'] = (string) $this->evaluate($item['render'], $item, $user);
        } elseif (isset($item['view'])) {
            $item['content'] = view($item['view'], array_merge($item['data'], [
                'item' => $item,
                'dashboardUser' => $user,
            ]))->render();
        } else {
            $item['content'] = (string) ($item['content'] ?? '');
        }

        return $item;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function isVisible(array $item, ?Authenticatable $user = null): bool
    {
        $visible = $item['visible'] ?? true;

        if ($visible instanceof Closure) {
            return (bool) $visible($user, $item);
        }

        if (isset($item['permission']) && method_exists($user, 'hasPermission')) {
            return (bool) $user?->hasPermission($item['permission']);
        }

        return (bool) $visible;
    }

    /**
     * @param  Closure|mixed  $value
     * @param  array<string, mixed>  $item
     */
    protected function evaluate(mixed $value, array $item, ?Authenticatable $user = null): mixed
    {
        if ($value instanceof Closure) {
            return $value($user, $item);
        }

        return $value;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return Collection<int, array<string, mixed>>
     */
    protected function applyLayoutOrder(Collection $items, ?Authenticatable $user = null): Collection
    {
        $layoutOrder = $this->layout($user);
        $positions = array_flip($layoutOrder);
        $baselinePositions = $items
            ->sortBy(fn (array $item): int => (int) ($item['order'] ?? 100))
            ->pluck('id')
            ->values()
            ->flip()
            ->all();

        return $items->sort(function (array $a, array $b) use ($positions, $baselinePositions): int {
            $baselineA = $baselinePositions[$a['id']] ?? 9999;
            $baselineB = $baselinePositions[$b['id']] ?? 9999;
            $positionA = $positions[$a['id']] ?? $baselineA;
            $positionB = $positions[$b['id']] ?? $baselineB;

            if ($positionA === $positionB) {
                return $baselineA <=> $baselineB;
            }

            return $positionA <=> $positionB;
        });
    }

    protected function resolveRouteUrl(string $routeName): string
    {
        $request = request();

        if ($request instanceof Request) {
            return rtrim($request->getBaseUrl(), '/').route($routeName, absolute: false);
        }

        return route($routeName);
    }
}
