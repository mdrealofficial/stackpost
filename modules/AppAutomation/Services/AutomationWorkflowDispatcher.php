<?php

namespace Modules\AppAutomation\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\AdminUser\Models\User;
use Modules\AppAutomation\Jobs\RunAutomationWorkflowJob;
use Modules\AppAutomation\Models\AutomationWorkflow;
use Modules\AppAutomation\Models\AutomationWorkflowRevision;
use Modules\AppAutomation\Models\AutomationWorkflowRun;
use Modules\AppAutomation\Support\AutomationWorkflowGraph;
use Modules\AppAutomation\Support\AutomationWorkflowTrace;
use RuntimeException;

class AutomationWorkflowDispatcher
{
    public function __construct(
        protected AutomationWorkflowRuntimeReadiness $readiness,
        protected AutomationQueueReadiness $queueReadiness,
    ) {}

    /**
     * Persist one immutable workflow-revision run and enqueue it after commit.
     * Supplying the same external event id for the same workflow returns the
     * original run and never enqueues a duplicate execution.
     */
    public function dispatchWorkflow(
        AutomationWorkflow|int $workflow,
        array $input,
        ?string $externalEventId = null,
        ?int $requestedByUserId = null,
        ?string $triggerKey = null,
    ): AutomationWorkflowRun {
        $workflow = $workflow instanceof AutomationWorkflow
            ? $workflow->fresh()
            : AutomationWorkflow::query()->find($workflow);

        if (! $workflow || (string) $workflow->status !== 'active' || ! $workflow->published_at) {
            throw new RuntimeException(__('Only an active published workflow can be dispatched.'));
        }

        $definition = $this->definitionFor($workflow);
        $this->readiness->assertRunnable($workflow, $definition, $requestedByUserId);

        $trigger = collect($definition['nodes'])
            ->first(fn (array $node): bool => str_starts_with((string) $node['type'], 'trigger_'));
        if (! is_array($trigger)) {
            throw new RuntimeException(__('The workflow trigger is missing.'));
        }

        $eventId = trim((string) $externalEventId);
        $eventId = $eventId !== ''
            ? Str::limit($eventId, 160, '')
            : 'manual:'.Str::uuid()->toString();
        $created = false;

        try {
            $run = DB::transaction(function () use (
                $workflow,
                $definition,
                $trigger,
                $triggerKey,
                $input,
                $eventId,
                $requestedByUserId,
                &$created,
            ): AutomationWorkflowRun {
                $locked = AutomationWorkflow::query()->whereKey($workflow->id)->lockForUpdate()->firstOrFail();
                if ((string) $locked->status !== 'active' || ! $locked->published_at) {
                    throw new RuntimeException(__('This workflow is no longer active.'));
                }

                $existing = AutomationWorkflowRun::query()
                    ->where('workflow_id', $locked->id)
                    ->where('external_event_id', $eventId)
                    ->first();
                if ($existing) {
                    return $existing;
                }

                $owner = User::query()->with('plan')->whereKey($locked->owner_user_id)->lockForUpdate()->firstOrFail();
                $this->assertMonthlyRunLimit($owner);

                $maxAttempts = max(1, min(10, (int) data_get(
                    $locked->settings,
                    'runtime.max_attempts',
                    config('modules.appautomation.runtime.max_attempts', 3)
                )));
                $run = AutomationWorkflowRun::query()->create([
                    'workflow_id' => (int) $locked->id,
                    'owner_user_id' => (int) $locked->owner_user_id,
                    'requested_by_user_id' => $requestedByUserId && $requestedByUserId > 0 ? $requestedByUserId : null,
                    'team_id' => $locked->team_id,
                    'workflow_revision' => (int) $locked->current_revision,
                    'trigger_key' => $triggerKey ?: (string) $locked->trigger_key,
                    'external_event_id' => $eventId,
                    'status' => AutomationWorkflowRun::STATUS_QUEUED,
                    'attempt' => 0,
                    'max_attempts' => $maxAttempts,
                    'next_attempt_at' => now(),
                    'input' => $input,
                    'trace' => AutomationWorkflowTrace::initial((string) $trigger['id'], [
                        'workflow_revision' => (int) $locked->current_revision,
                        'trigger_key' => $triggerKey ?: (string) $locked->trigger_key,
                        'external_event_id' => $eventId,
                    ]),
                    'output' => ['nodes' => [], 'last' => []],
                ]);

                $locked->forceFill(['last_run_at' => now()])->save();
                $created = true;

                return $run;
            });
        } catch (QueryException $exception) {
            $run = AutomationWorkflowRun::query()
                ->where('workflow_id', $workflow->id)
                ->where('external_event_id', $eventId)
                ->first();
            if (! $run) {
                throw $exception;
            }
        }

        if ($created) {
            $this->enqueue($run);
        }

        return $run;
    }

    /**
     * Dispatch an external trigger to all matching workflows in one explicit
     * persisted workspace. No auth session or active-team state is consulted.
     *
     * @return Collection<int, AutomationWorkflowRun>
     */
    public function dispatchTrigger(
        int $ownerUserId,
        ?int $teamId,
        string $triggerKey,
        string $externalEventId,
        array $input,
        ?int $requestedByUserId = null,
    ): Collection {
        $workflows = AutomationWorkflow::query()
            ->where('owner_user_id', $ownerUserId)
            ->when($teamId, fn ($query, $id) => $query->where('team_id', $id), fn ($query) => $query->whereNull('team_id'))
            ->where('status', 'active')
            ->whereNotNull('published_at')
            ->where('trigger_key', $triggerKey)
            ->orderBy('id')
            ->get();

        return $workflows->map(fn (AutomationWorkflow $workflow): AutomationWorkflowRun => $this->dispatchWorkflow(
            workflow: $workflow,
            input: $input,
            externalEventId: $externalEventId,
            requestedByUserId: $requestedByUserId,
            triggerKey: $triggerKey,
        ));
    }

    public function enqueue(AutomationWorkflowRun|int $run): void
    {
        $this->queueReadiness->assertConfigured();
        $runId = $run instanceof AutomationWorkflowRun ? (int) $run->id : (int) $run;
        $connection = $this->queueReadiness->connectionName();
        $queue = $this->queueReadiness->queueName();

        DB::afterCommit(fn () => RunAutomationWorkflowJob::dispatch($runId)
            ->onConnection($connection)
            ->onQueue($queue));
    }

    protected function definitionFor(AutomationWorkflow $workflow): array
    {
        $revision = AutomationWorkflowRevision::query()
            ->where('workflow_id', $workflow->id)
            ->where('revision', $workflow->current_revision)
            ->first();

        if (! $revision) {
            throw new RuntimeException(__('The published workflow revision is missing. Save and activate the workflow again before dispatching it.'));
        }

        return AutomationWorkflowGraph::sanitize((array) $revision->definition);
    }

    protected function assertMonthlyRunLimit(User $owner): void
    {
        if (! $owner->plan) {
            return;
        }

        $limit = (int) $owner->planLimit('max_automation_runs_per_month', 1000);
        if ($limit < 0) {
            return;
        }

        $used = AutomationWorkflowRun::query()
            ->where('owner_user_id', $owner->id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();
        if ($used >= $limit) {
            throw new RuntimeException(__('This workspace reached its monthly workflow run limit of :limit.', [
                'limit' => number_format($limit),
            ]));
        }
    }
}
