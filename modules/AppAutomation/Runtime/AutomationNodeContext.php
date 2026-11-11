<?php

namespace Modules\AppAutomation\Runtime;

use Modules\AppAutomation\Models\AutomationWorkflowRun;
use Modules\AppAutomation\Models\AutomationWorkflowStepRun;

final readonly class AutomationNodeContext
{
    public function __construct(
        public AutomationWorkflowRun $run,
        public AutomationWorkflowStepRun $step,
        public array $node,
        public array $definition,
    ) {}

    public function config(?string $key = null, mixed $default = null): mixed
    {
        $config = (array) ($this->node['config'] ?? []);

        return $key === null ? $config : data_get($config, $key, $default);
    }

    public function originalInput(): array
    {
        return (array) $this->run->input;
    }

    public function stepOutputs(): array
    {
        return (array) data_get($this->run->output, 'nodes', []);
    }

    public function lastOutput(): array
    {
        return (array) data_get($this->run->output, 'last', []);
    }

    public function payload(): array
    {
        $last = $this->lastOutput();

        return $last !== [] ? $last : $this->originalInput();
    }

    public function value(string $path, mixed $default = null): mixed
    {
        $sentinel = new \stdClass();
        $value = data_get($this->payload(), $path, $sentinel);

        if ($value !== $sentinel) {
            return $value;
        }

        return data_get($this->originalInput(), $path, $default);
    }

    /**
     * Stable across retries. Side-effect handlers should pass this key to the
     * downstream provider or persist it beside the created resource.
     */
    public function idempotencyKey(): string
    {
        return 'automation-run:'.(int) $this->run->id.':node:'.(string) $this->step->node_id;
    }

    public function ownerUserId(): int
    {
        return (int) $this->run->owner_user_id;
    }

    public function requestedByUserId(): ?int
    {
        $id = (int) $this->run->requested_by_user_id;

        return $id > 0 ? $id : null;
    }

    public function teamId(): ?int
    {
        $id = (int) $this->run->team_id;

        return $id > 0 ? $id : null;
    }
}
