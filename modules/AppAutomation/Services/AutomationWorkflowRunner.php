<?php

namespace Modules\AppAutomation\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\AppAutomation\Exceptions\AutomationNodeException;
use Modules\AppAutomation\Exceptions\AutomationNodeRetryAtException;
use Modules\AppAutomation\Models\AutomationWorkflow;
use Modules\AppAutomation\Models\AutomationWorkflowRevision;
use Modules\AppAutomation\Models\AutomationWorkflowRun;
use Modules\AppAutomation\Models\AutomationWorkflowStepRun;
use Modules\AppAutomation\Runtime\AutomationNodeContext;
use Modules\AppAutomation\Runtime\AutomationNodeResult;
use Modules\AppAutomation\Support\AutomationWorkflowGraph;
use Modules\AppAutomation\Support\AutomationWorkflowTrace;
use RuntimeException;
use Throwable;

class AutomationWorkflowRunner
{
    public function __construct(
        protected AutomationWorkflowHandlerRegistry $handlers,
        protected AutomationWorkflowRuntimeReadiness $readiness,
    ) {}

    /**
     * Claim and advance a persisted run until it completes or reaches a durable
     * wait/retry state. The database lease, not the queue delivery, is the
     * authority: duplicate jobs safely return without running a node twice.
     */
    public function execute(int $runId): void
    {
        $claim = $this->claimRun($runId);
        if (! $claim) {
            return;
        }

        /** @var AutomationWorkflowRun $run */
        $run = $claim['run'];
        $token = $claim['token'];
        try {
            $definition = $this->definitionForRun($run);
            $workflow = AutomationWorkflow::query()->findOrFail($run->workflow_id);
            $this->readiness->assertRunnable($workflow, $definition, $run->requested_by_user_id ?: null);
        } catch (Throwable $exception) {
            $this->failRun($run, $token, $exception->getMessage());

            return;
        }
        $maximumNodes = max(1, min(500, (int) config('modules.appautomation.runtime.max_nodes_per_run', 100)));

        for ($processed = 0; $processed < $maximumNodes; $processed++) {
            $run->refresh();
            if ((string) $run->claim_token !== $token || (string) $run->status !== AutomationWorkflowRun::STATUS_RUNNING) {
                return;
            }

            $nodeId = trim((string) data_get($run->trace, 'cursor'));
            $node = collect($definition['nodes'])->firstWhere('id', $nodeId);
            if (! is_array($node)) {
                $this->failRun($run, $token, __('The workflow cursor does not point to a valid node.'));

                return;
            }

            $prepared = $this->prepareStep($run, $token, $node, $definition);
            if (($prepared['state'] ?? null) === 'waiting') {
                return;
            }
            if (($prepared['state'] ?? null) === 'advanced') {
                continue;
            }

            $step = $prepared['step'] ?? null;
            if (! $step instanceof AutomationWorkflowStepRun) {
                return;
            }

            try {
                $handler = $this->handlers->resolve((string) $node['type']);
                $result = $handler->handle(new AutomationNodeContext($run->fresh(), $step->fresh(), $node, $definition));

                if ($result->status === AutomationNodeResult::WAITING_APPROVAL) {
                    $this->persistWait($run, $step, $token, $node, $definition, $result, AutomationWorkflowRun::STATUS_WAITING_APPROVAL);

                    return;
                }

                if ($result->status === AutomationNodeResult::WAITING_DELAY) {
                    if (! $result->resumeAt || $result->resumeAt->lessThanOrEqualTo(now())) {
                        throw AutomationNodeException::permanent(__('The Wait node returned an invalid future resume time.'));
                    }

                    $this->persistWait($run, $step, $token, $node, $definition, $result, AutomationWorkflowRun::STATUS_WAITING_DELAY);

                    return;
                }

                if ($result->status !== AutomationNodeResult::SUCCEEDED) {
                    throw AutomationNodeException::permanent(__('The workflow node returned an unsupported runtime status.'));
                }

                if (! $this->persistSuccess($run, $step, $token, $node, $definition, $result)) {
                    return;
                }
            } catch (Throwable $exception) {
                $retryable = ! $exception instanceof AutomationNodeException || $exception->retryable;
                $this->persistFailure($run, $step, $token, $node, $exception, $retryable);

                return;
            }
        }

        $this->failRun($run, $token, __('The workflow exceeded the maximum number of runtime node transitions. Check the graph for a cycle.'));
    }

    protected function claimRun(int $runId): ?array
    {
        return DB::transaction(function () use ($runId): ?array {
            $run = AutomationWorkflowRun::query()->whereKey($runId)->lockForUpdate()->first();
            if (! $run || $run->isTerminal() || (string) $run->status === AutomationWorkflowRun::STATUS_WAITING_APPROVAL) {
                return null;
            }

            if (in_array((string) $run->status, [AutomationWorkflowRun::STATUS_RETRYING, AutomationWorkflowRun::STATUS_WAITING_DELAY], true)
                && $run->next_attempt_at?->isFuture()) {
                return null;
            }

            $leaseSeconds = max(30, (int) config('modules.appautomation.runtime.lease_seconds', 300));
            $isFreshRunningLease = (string) $run->status === AutomationWorkflowRun::STATUS_RUNNING
                && $run->heartbeat_at?->greaterThan(now()->subSeconds($leaseSeconds));
            if ($isFreshRunningLease) {
                return null;
            }

            $recovering = (string) $run->status === AutomationWorkflowRun::STATUS_RUNNING;
            $token = Str::uuid()->toString();
            $trace = AutomationWorkflowTrace::append(
                (array) $run->trace,
                $recovering ? 'run.lease_recovered' : 'run.started',
                null,
                ['attempt' => (int) $run->attempt + 1]
            );

            $run->forceFill([
                'status' => AutomationWorkflowRun::STATUS_RUNNING,
                'attempt' => (int) $run->attempt + 1,
                'next_attempt_at' => null,
                'claimed_at' => now(),
                'heartbeat_at' => now(),
                'claim_token' => $token,
                'waiting_reason' => null,
                'started_at' => $run->started_at ?: now(),
                'completed_at' => null,
                'error' => null,
                'trace' => $trace,
            ])->save();

            return ['run' => $run, 'token' => $token];
        });
    }

    protected function prepareStep(AutomationWorkflowRun $run, string $runToken, array $node, array $definition): array
    {
        return DB::transaction(function () use ($run, $runToken, $node, $definition): array {
            $lockedRun = AutomationWorkflowRun::query()->whereKey($run->id)->lockForUpdate()->firstOrFail();
            if ((string) $lockedRun->claim_token !== $runToken || (string) $lockedRun->status !== AutomationWorkflowRun::STATUS_RUNNING) {
                return ['state' => 'waiting'];
            }

            $latest = AutomationWorkflowStepRun::query()
                ->where('run_id', $lockedRun->id)
                ->where('node_id', (string) $node['id'])
                ->orderByDesc('attempt')
                ->lockForUpdate()
                ->first();

            if ($latest?->status === AutomationWorkflowStepRun::STATUS_SUCCEEDED) {
                $this->advanceFromPersistedStep($lockedRun, $latest, $runToken, $node, $definition);

                return ['state' => 'advanced'];
            }

            if ($latest?->status === AutomationWorkflowStepRun::STATUS_WAITING_APPROVAL) {
                $this->releaseRunForWait($lockedRun, AutomationWorkflowRun::STATUS_WAITING_APPROVAL, null, 'approval', (string) $node['id']);

                return ['state' => 'waiting'];
            }

            if ($latest?->status === AutomationWorkflowStepRun::STATUS_WAITING_DELAY) {
                if ($latest->available_at?->isFuture()) {
                    $this->releaseRunForWait($lockedRun, AutomationWorkflowRun::STATUS_WAITING_DELAY, $latest->available_at, 'delay', (string) $node['id']);

                    return ['state' => 'waiting'];
                }

                $latest->forceFill([
                    'status' => AutomationWorkflowStepRun::STATUS_SUCCEEDED,
                    'available_at' => null,
                    'claim_token' => null,
                    'heartbeat_at' => now(),
                    'completed_at' => now(),
                ])->save();
                $this->advanceFromPersistedStep($lockedRun, $latest, $runToken, $node, $definition, 'node.delay_elapsed');

                return ['state' => 'advanced'];
            }

            $leaseSeconds = max(30, (int) config('modules.appautomation.runtime.lease_seconds', 300));
            if ($latest?->status === AutomationWorkflowStepRun::STATUS_RUNNING) {
                if ($latest->heartbeat_at?->greaterThan(now()->subSeconds($leaseSeconds))) {
                    return ['state' => 'waiting'];
                }

                $latest->forceFill([
                    'status' => AutomationWorkflowStepRun::STATUS_FAILED,
                    'error' => __('The worker lease expired before this attempt completed.'),
                    'claim_token' => null,
                    'completed_at' => now(),
                ])->save();
            }

            if ($latest?->status === AutomationWorkflowStepRun::STATUS_FAILED && $latest->available_at?->isFuture()) {
                $this->releaseRunForWait($lockedRun, AutomationWorkflowRun::STATUS_RETRYING, $latest->available_at, 'retry', (string) $node['id']);

                return ['state' => 'waiting'];
            }

            $attempt = $latest ? ((int) $latest->attempt + 1) : 1;
            if ($attempt > max(1, (int) $lockedRun->max_attempts)) {
                $this->markRunFailedLocked($lockedRun, __('This workflow node exhausted its retry attempts.'), (string) $node['id']);

                return ['state' => 'waiting'];
            }

            $stepToken = Str::uuid()->toString();
            $step = AutomationWorkflowStepRun::query()->create([
                'run_id' => (int) $lockedRun->id,
                'node_id' => (string) $node['id'],
                'node_type' => (string) $node['type'],
                'attempt' => $attempt,
                'status' => AutomationWorkflowStepRun::STATUS_RUNNING,
                'input' => [
                    'event' => (array) $lockedRun->input,
                    'previous' => (array) data_get($lockedRun->output, 'last', []),
                ],
                'claimed_at' => now(),
                'heartbeat_at' => now(),
                'claim_token' => $stepToken,
                'started_at' => now(),
            ]);
            $lockedRun->forceFill([
                'heartbeat_at' => now(),
                'trace' => AutomationWorkflowTrace::append(
                    (array) $lockedRun->trace,
                    'node.started',
                    (string) $node['id'],
                    ['node_type' => (string) $node['type'], 'attempt' => $attempt]
                ),
            ])->save();

            return ['state' => 'claimed', 'step' => $step];
        });
    }

    protected function persistSuccess(
        AutomationWorkflowRun $run,
        AutomationWorkflowStepRun $step,
        string $runToken,
        array $node,
        array $definition,
        AutomationNodeResult $result,
    ): bool {
        return DB::transaction(function () use ($run, $step, $runToken, $node, $definition, $result): bool {
            $lockedRun = AutomationWorkflowRun::query()->whereKey($run->id)->lockForUpdate()->firstOrFail();
            $lockedStep = AutomationWorkflowStepRun::query()->whereKey($step->id)->lockForUpdate()->firstOrFail();
            if ((string) $lockedRun->claim_token !== $runToken
                || (string) $lockedStep->claim_token !== (string) $step->claim_token
                || ! in_array((string) $lockedStep->status, [AutomationWorkflowStepRun::STATUS_RUNNING, AutomationWorkflowStepRun::STATUS_SUCCEEDED], true)) {
                return false;
            }

            $nextNodeId = $this->nextNodeId($definition, $node, $result->branch);
            $filteredOut = (string) $node['type'] === 'condition' && $result->branch === 'no' && $nextNodeId === null;
            $terminal = (string) $node['type'] === 'end' || $filteredOut;
            if ($nextNodeId === null && ! $terminal) {
                throw AutomationNodeException::permanent(__('The workflow node has no valid outgoing path.'));
            }

            $output = [
                ...$result->output,
                '_runtime' => [
                    'branch' => $result->branch,
                    'next_node_id' => $nextNodeId,
                    'idempotency_key' => 'automation-run:'.(int) $run->id.':node:'.(string) $node['id'],
                ],
            ];
            $lockedStep->forceFill([
                'status' => AutomationWorkflowStepRun::STATUS_SUCCEEDED,
                'output' => $output,
                'error' => null,
                'available_at' => null,
                'claim_token' => null,
                'heartbeat_at' => now(),
                'completed_at' => now(),
            ])->save();

            $runOutput = $this->mergeRunOutput((array) $lockedRun->output, (string) $node['id'], $output);
            $trace = AutomationWorkflowTrace::append(
                (array) $lockedRun->trace,
                $terminal ? 'run.succeeded' : 'node.succeeded',
                (string) $node['id'],
                ['node_type' => (string) $node['type'], 'branch' => $result->branch, 'filtered_out' => $filteredOut],
                $nextNodeId
            );
            $lockedRun->forceFill([
                'status' => $terminal ? AutomationWorkflowRun::STATUS_SUCCEEDED : AutomationWorkflowRun::STATUS_RUNNING,
                'output' => $runOutput,
                'trace' => $trace,
                'heartbeat_at' => now(),
                'claim_token' => $terminal ? null : $runToken,
                'claimed_at' => $terminal ? null : $lockedRun->claimed_at,
                'completed_at' => $terminal ? now() : null,
                'waiting_reason' => null,
                'next_attempt_at' => null,
            ])->save();

            return true;
        });
    }

    protected function persistWait(
        AutomationWorkflowRun $run,
        AutomationWorkflowStepRun $step,
        string $runToken,
        array $node,
        array $definition,
        AutomationNodeResult $result,
        string $runStatus,
    ): void {
        DB::transaction(function () use ($run, $step, $runToken, $node, $definition, $result, $runStatus): void {
            $lockedRun = AutomationWorkflowRun::query()->whereKey($run->id)->lockForUpdate()->firstOrFail();
            $lockedStep = AutomationWorkflowStepRun::query()->whereKey($step->id)->lockForUpdate()->firstOrFail();
            if ((string) $lockedRun->claim_token !== $runToken || (string) $lockedStep->claim_token !== (string) $step->claim_token) {
                return;
            }

            $nextNodeId = $this->nextNodeId($definition, $node, null);
            if (! $nextNodeId) {
                throw AutomationNodeException::permanent(__('A waiting workflow node needs an outgoing path.'));
            }

            $resumeAt = $result->resumeAt;
            $output = [
                ...$result->output,
                '_runtime' => [
                    'next_node_id' => $nextNodeId,
                    'resume_at' => $resumeAt?->toIso8601String(),
                    'idempotency_key' => 'automation-run:'.(int) $run->id.':node:'.(string) $node['id'],
                ],
            ];
            $stepStatus = $runStatus === AutomationWorkflowRun::STATUS_WAITING_APPROVAL
                ? AutomationWorkflowStepRun::STATUS_WAITING_APPROVAL
                : AutomationWorkflowStepRun::STATUS_WAITING_DELAY;
            $lockedStep->forceFill([
                'status' => $stepStatus,
                'output' => $output,
                'available_at' => $resumeAt,
                'heartbeat_at' => now(),
                'claim_token' => null,
            ])->save();
            $lockedRun->forceFill([
                'status' => $runStatus,
                'output' => $this->mergeRunOutput((array) $lockedRun->output, (string) $node['id'], $output),
                'trace' => AutomationWorkflowTrace::append(
                    (array) $lockedRun->trace,
                    $runStatus === AutomationWorkflowRun::STATUS_WAITING_APPROVAL ? 'node.waiting_approval' : 'node.waiting_delay',
                    (string) $node['id'],
                    ['resume_at' => $resumeAt?->toIso8601String()]
                ),
                'next_attempt_at' => $resumeAt,
                'waiting_reason' => $runStatus === AutomationWorkflowRun::STATUS_WAITING_APPROVAL ? 'approval' : 'delay',
                'claim_token' => null,
                'claimed_at' => null,
                'heartbeat_at' => now(),
            ])->save();
        });
    }

    protected function persistFailure(
        AutomationWorkflowRun $run,
        AutomationWorkflowStepRun $step,
        string $runToken,
        array $node,
        Throwable $exception,
        bool $retryable,
    ): void {
        DB::transaction(function () use ($run, $step, $runToken, $node, $exception, $retryable): void {
            $lockedRun = AutomationWorkflowRun::query()->whereKey($run->id)->lockForUpdate()->first();
            $lockedStep = AutomationWorkflowStepRun::query()->whereKey($step->id)->lockForUpdate()->first();
            if (! $lockedRun || ! $lockedStep || (string) $lockedRun->claim_token !== $runToken) {
                return;
            }

            $message = Str::limit(trim($exception->getMessage()) ?: __('The workflow node failed.'), 4000, '');
            $canRetry = $retryable && (int) $lockedStep->attempt < max(1, (int) $lockedRun->max_attempts);
            $retryAt = $canRetry
                ? ($exception instanceof AutomationNodeRetryAtException && $exception->retryAt->isFuture()
                    ? $exception->retryAt
                    : now()->addSeconds($this->backoffSeconds((int) $lockedStep->attempt)))
                : null;

            $lockedStep->forceFill([
                'status' => AutomationWorkflowStepRun::STATUS_FAILED,
                'error' => $message,
                'available_at' => $retryAt,
                'claim_token' => null,
                'heartbeat_at' => now(),
                'completed_at' => now(),
            ])->save();
            $lockedRun->forceFill([
                'status' => $canRetry ? AutomationWorkflowRun::STATUS_RETRYING : AutomationWorkflowRun::STATUS_FAILED,
                'error' => $message,
                'trace' => AutomationWorkflowTrace::append(
                    (array) $lockedRun->trace,
                    $canRetry ? 'node.retry_scheduled' : 'run.failed',
                    (string) $node['id'],
                    ['attempt' => (int) $lockedStep->attempt, 'retry_at' => $retryAt?->toIso8601String(), 'error' => $message]
                ),
                'next_attempt_at' => $retryAt,
                'waiting_reason' => $canRetry ? 'retry' : null,
                'claim_token' => null,
                'claimed_at' => null,
                'heartbeat_at' => now(),
                'completed_at' => $canRetry ? null : now(),
            ])->save();
        });
    }

    protected function advanceFromPersistedStep(
        AutomationWorkflowRun $run,
        AutomationWorkflowStepRun $step,
        string $runToken,
        array $node,
        array $definition,
        string $event = 'node.replayed',
    ): void {
        $nextNodeId = (string) data_get($step->output, '_runtime.next_node_id', '');
        if ($nextNodeId === '') {
            $branch = data_get($step->output, '_runtime.branch');
            $nextNodeId = (string) ($this->nextNodeId($definition, $node, is_string($branch) ? $branch : null) ?? '');
        }
        $terminal = (string) $node['type'] === 'end'
            || ((string) $node['type'] === 'condition' && data_get($step->output, '_runtime.branch') === 'no' && $nextNodeId === '');

        $run->forceFill([
            'status' => $terminal ? AutomationWorkflowRun::STATUS_SUCCEEDED : AutomationWorkflowRun::STATUS_RUNNING,
            'trace' => AutomationWorkflowTrace::append(
                (array) $run->trace,
                $terminal ? 'run.succeeded' : $event,
                (string) $node['id'],
                [],
                $nextNodeId !== '' ? $nextNodeId : null
            ),
            'output' => $this->mergeRunOutput((array) $run->output, (string) $node['id'], (array) $step->output),
            'claim_token' => $terminal ? null : $runToken,
            'claimed_at' => $terminal ? null : $run->claimed_at,
            'heartbeat_at' => now(),
            'completed_at' => $terminal ? now() : null,
            'next_attempt_at' => null,
            'waiting_reason' => null,
        ])->save();
    }

    protected function releaseRunForWait(
        AutomationWorkflowRun $run,
        string $status,
        ?CarbonInterface $nextAttemptAt,
        string $reason,
        string $nodeId,
    ): void {
        $run->forceFill([
            'status' => $status,
            'next_attempt_at' => $nextAttemptAt,
            'waiting_reason' => $reason,
            'claim_token' => null,
            'claimed_at' => null,
            'heartbeat_at' => now(),
            'trace' => AutomationWorkflowTrace::append((array) $run->trace, 'run.waiting', $nodeId, [
                'reason' => $reason,
                'resume_at' => $nextAttemptAt?->toIso8601String(),
            ]),
        ])->save();
    }

    protected function failRun(AutomationWorkflowRun $run, string $runToken, string $message): void
    {
        DB::transaction(function () use ($run, $runToken, $message): void {
            $locked = AutomationWorkflowRun::query()->whereKey($run->id)->lockForUpdate()->first();
            if (! $locked || (string) $locked->claim_token !== $runToken) {
                return;
            }

            $this->markRunFailedLocked($locked, $message, (string) data_get($locked->trace, 'cursor'));
        });
    }

    protected function markRunFailedLocked(AutomationWorkflowRun $run, string $message, ?string $nodeId): void
    {
        $message = Str::limit($message, 4000, '');
        $run->forceFill([
            'status' => AutomationWorkflowRun::STATUS_FAILED,
            'error' => $message,
            'trace' => AutomationWorkflowTrace::append((array) $run->trace, 'run.failed', $nodeId, ['error' => $message]),
            'claim_token' => null,
            'claimed_at' => null,
            'heartbeat_at' => now(),
            'waiting_reason' => null,
            'next_attempt_at' => null,
            'completed_at' => now(),
        ])->save();
    }

    protected function nextNodeId(array $definition, array $node, ?string $branch): ?string
    {
        $edges = collect($definition['edges'])->where('from', (string) $node['id']);

        if ($branch !== null) {
            $branched = $edges->firstWhere('branch', $branch);
            if (is_array($branched)) {
                return (string) $branched['to'];
            }

            if ($branch === 'no') {
                return null;
            }
        }

        $edge = $edges->first(fn (array $edge): bool => (string) ($edge['branch'] ?? '') === '') ?: $edges->first();

        return is_array($edge) ? (string) $edge['to'] : null;
    }

    protected function mergeRunOutput(array $current, string $nodeId, array $output): array
    {
        $nodes = (array) ($current['nodes'] ?? []);
        $nodes[$nodeId] = $output;

        return ['nodes' => $nodes, 'last' => $output];
    }

    protected function definitionForRun(AutomationWorkflowRun $run): array
    {
        $revision = AutomationWorkflowRevision::query()
            ->where('workflow_id', $run->workflow_id)
            ->where('revision', $run->workflow_revision)
            ->first();

        if (! $revision) {
            throw new RuntimeException(__('The immutable workflow revision for this run is missing. The run was stopped instead of executing a different graph.'));
        }

        return AutomationWorkflowGraph::sanitize((array) $revision->definition);
    }

    protected function backoffSeconds(int $attempt): int
    {
        $backoff = collect((array) config('modules.appautomation.runtime.backoff_seconds', [30, 120, 600]))
            ->map(fn ($seconds): int => max(1, (int) $seconds))
            ->values();

        return (int) ($backoff->get(max(0, $attempt - 1)) ?? $backoff->last() ?? 60);
    }
}
