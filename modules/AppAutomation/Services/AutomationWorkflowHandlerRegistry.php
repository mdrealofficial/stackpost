<?php

namespace Modules\AppAutomation\Services;

use Closure;
use Modules\AppAutomation\Contracts\AutomationNodeHandler;
use Modules\AppAutomation\Exceptions\AutomationNodeException;

class AutomationWorkflowHandlerRegistry
{
    /** @var array<string, AutomationNodeHandler|class-string<AutomationNodeHandler>|Closure> */
    protected array $handlers = [];

    public function register(string $nodeType, AutomationNodeHandler|string|Closure $handler): self
    {
        $this->handlers[$nodeType] = $handler;

        return $this;
    }

    public function supports(string $nodeType): bool
    {
        return array_key_exists($nodeType, $this->handlers);
    }

    public function resolve(string $nodeType): AutomationNodeHandler
    {
        $concrete = $this->handlers[$nodeType] ?? null;

        if ($concrete instanceof Closure) {
            $concrete = app()->call($concrete);
        } elseif (is_string($concrete)) {
            $concrete = app($concrete);
        }

        if (! $concrete instanceof AutomationNodeHandler) {
            throw AutomationNodeException::permanent(
                __('No runtime handler is registered for workflow node :node.', ['node' => $nodeType])
            );
        }

        return $concrete;
    }

    public function registeredTypes(): array
    {
        return array_keys($this->handlers);
    }
}
