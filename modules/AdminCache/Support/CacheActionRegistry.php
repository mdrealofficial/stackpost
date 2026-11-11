<?php

namespace Modules\AdminCache\Support;

use Illuminate\Contracts\Container\Container;
use Modules\AdminCache\Actions\ClearApplicationCacheAction;
use Modules\AdminCache\Actions\ClearConfigCacheAction;
use Modules\AdminCache\Actions\ClearRouteCacheAction;
use Modules\AdminCache\Actions\ClearSessionsAction;
use Modules\AdminCache\Actions\ClearViewCacheAction;
use Modules\AdminCache\Actions\Contracts\CacheAction;
use Modules\AdminCache\Actions\OptimizeApplicationAction;
use RuntimeException;

class CacheActionRegistry
{
    /**
     * @var array<string, class-string<CacheAction>>
     */
    protected array $actions = [
        'app' => ClearApplicationCacheAction::class,
        'config' => ClearConfigCacheAction::class,
        'route' => ClearRouteCacheAction::class,
        'view' => ClearViewCacheAction::class,
        'optimize' => OptimizeApplicationAction::class,
        'session' => ClearSessionsAction::class,
    ];

    public function __construct(
        protected Container $container,
    ) {
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function gridItems(): array
    {
        return collect(['app', 'config', 'route', 'view'])
            ->map(fn (string $key) => $this->metadata($this->make($key)))
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function optimizeItem(): array
    {
        return $this->metadata($this->make('optimize'));
    }

    /**
     * @return array<string, string>
     */
    public function sessionItem(): array
    {
        return $this->metadata($this->make('session'));
    }

    public function run(string $key): string
    {
        return $this->make($key)->handle();
    }

    protected function make(string $key): CacheAction
    {
        $class = $this->actions[$key] ?? null;

        if (! $class) {
            throw new RuntimeException(__('Invalid cache action.'));
        }

        return $this->container->make($class);
    }

    /**
     * @return array<string, string>
     */
    protected function metadata(CacheAction $action): array
    {
        return [
            'key' => $action->key(),
            'title' => $action->title(),
            'description' => $action->description(),
            'confirm_title' => $action->confirmTitle(),
            'icon' => $action->icon(),
            'button' => $action->buttonLabel(),
            'variant' => $action->buttonVariant(),
            'confirm' => $action->confirmMessage(),
        ];
    }
}
