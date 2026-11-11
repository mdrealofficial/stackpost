<?php

namespace Modules\AppAIStudio\Support;

use Illuminate\Support\Facades\Route;
use Modules\AppTeams\Support\TeamWorkspaceAccess;

class AIStudioToolRegistry
{
    protected array $tools = [];

    public function register(array $tool): static
    {
        $key = trim((string) ($tool['key'] ?? ''));

        if ($key === '') {
            throw new \InvalidArgumentException('AI Studio tool key is required.');
        }

        $tool['key'] = $key;
        $tool['order'] = (int) ($tool['order'] ?? 100);

        $this->tools[$key] = array_merge($this->tools[$key] ?? [], $tool);

        return $this;
    }

    public function sidebarChildren(callable $featureEnabled): array
    {
        return $this->visibleTools($featureEnabled)
            ->map(fn (array $tool): array => [
                'label' => $tool['label'],
                'route_name' => $tool['route_name'],
                'active_when' => $tool['active_when'] ?? [$tool['route_name']],
                'order' => $tool['order'],
                'visible' => true,
            ])
            ->values()
            ->all();
    }

    public function dashboardTools(callable $routeResolver, callable $toolVisible): array
    {
        return $this->visibleTools(fn (string $feature): bool => (bool) $toolVisible($this->toolsByFeature()[$feature]['route_name'] ?? '', $feature))
            ->map(fn (array $tool): array => [
                'label' => $tool['label'],
                'description' => $tool['description'] ?? '',
                'route' => $routeResolver($tool['route_name']),
                'icon' => $tool['icon'] ?? 'fa-light fa-wand-magic-sparkles',
                'tone' => $tool['tone'] ?? 'rgba(var(--theme-accent-rgb),0.10)',
                'visible' => true,
            ])
            ->values()
            ->all();
    }

    public function redirectRouteNames(callable $featureEnabled): array
    {
        return $this->visibleTools($featureEnabled)
            ->pluck('route_name')
            ->values()
            ->all();
    }

    protected function visibleTools(callable $featureEnabled): \Illuminate\Support\Collection
    {
        return collect($this->tools)
            ->filter(function (array $tool) use ($featureEnabled): bool {
                $routeName = (string) ($tool['route_name'] ?? '');
                $feature = (string) ($tool['feature'] ?? '');
                $teamModule = (string) ($tool['team_module'] ?? '');

                return $routeName !== ''
                    && Route::has($routeName)
                    && ($teamModule === '' || TeamWorkspaceAccess::teamHasModule(TeamWorkspaceAccess::activeTeam(auth()->user()), $teamModule))
                    && ($feature === '' || (bool) $featureEnabled($feature));
            })
            ->sortBy(fn (array $tool): int => (int) ($tool['order'] ?? 100))
            ->values();
    }

    protected function toolsByFeature(): array
    {
        return collect($this->tools)
            ->filter(fn (array $tool): bool => filled($tool['feature'] ?? null))
            ->keyBy(fn (array $tool): string => (string) $tool['feature'])
            ->all();
    }
}
