<?php

namespace Modules\AppAutomation\Services;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\AdminUser\Models\Team;
use Modules\AdminUser\Models\User;
use Modules\AppAutomation\Models\AutomationWorkflowRun;
use Modules\AppAutomation\Models\AutomationWorkflowStepRun;
use Modules\AppAutomation\Support\AutomationWorkflowTrace;
use Modules\AppTeams\Support\TeamWorkspaceAccess;
use RuntimeException;

class AutomationWorkflowApprovalService
{
    public function __construct(protected AutomationWorkflowDispatcher $dispatcher) {}

    public function approve(int $runId, int $stepId, User $actor, ?string $note = null): AutomationWorkflowRun
    {
        return $this->decide($runId, $stepId, $actor, 'approved', $note);
    }

    public function reject(int $runId, int $stepId, User $actor, ?string $note = null): AutomationWorkflowRun
    {
        return $this->decide($runId, $stepId, $actor, 'rejected', $note);
    }

    /**
     * Authorization is checked against the persisted run workspace. No active
     * team from the current session is used.
     */
    public function decide(
        int $runId,
        int $stepId,
        User $actor,
        string $decision,
        ?string $note = null,
    ): AutomationWorkflowRun {
        if (! in_array($decision, ['approved', 'rejected'], true)) {
            throw new RuntimeException(__('The workflow approval decision is invalid.'));
        }

        $runForAccess = AutomationWorkflowRun::query()->findOrFail($runId);
        $stepForAccess = AutomationWorkflowStepRun::query()->findOrFail($stepId);
        if ((int) $stepForAccess->run_id !== (int) $runForAccess->id) {
            throw new RuntimeException(__('The approval step does not belong to this workflow run.'));
        }
        $this->authorize($runForAccess, $stepForAccess, $actor);

        $enqueue = false;
        $run = DB::transaction(function () use ($runId, $stepId, $actor, $decision, $note, &$enqueue): AutomationWorkflowRun {
            $run = AutomationWorkflowRun::query()->whereKey($runId)->lockForUpdate()->firstOrFail();
            $step = AutomationWorkflowStepRun::query()->whereKey($stepId)->lockForUpdate()->firstOrFail();
            if ((int) $step->run_id !== (int) $run->id) {
                throw new RuntimeException(__('The approval step does not belong to this workflow run.'));
            }

            $existingDecision = (string) data_get($step->output, 'approval.decision', '');
            if ($existingDecision !== '') {
                if ($existingDecision !== $decision) {
                    throw new RuntimeException(__('This approval step already has a different final decision.'));
                }

                return $run;
            }

            if ((string) $run->status !== AutomationWorkflowRun::STATUS_WAITING_APPROVAL
                || (string) $step->status !== AutomationWorkflowStepRun::STATUS_WAITING_APPROVAL) {
                throw new RuntimeException(__('This workflow run is not waiting for this approval.'));
            }

            $output = (array) $step->output;
            $output['approval'] = [
                'decision' => $decision,
                'actor_user_id' => (int) $actor->id,
                'decided_at' => now()->toIso8601String(),
                'note' => $note !== null ? Str::limit(trim($note), 1000, '') : null,
            ];

            if ($decision === 'rejected') {
                $step->forceFill([
                    'status' => AutomationWorkflowStepRun::STATUS_REJECTED,
                    'output' => $output,
                    'completed_at' => now(),
                ])->save();
                $run->forceFill([
                    'status' => AutomationWorkflowRun::STATUS_REJECTED,
                    'output' => $this->mergeRunOutput((array) $run->output, (string) $step->node_id, $output),
                    'trace' => AutomationWorkflowTrace::append(
                        (array) $run->trace,
                        'approval.rejected',
                        (string) $step->node_id,
                        ['actor_user_id' => (int) $actor->id]
                    ),
                    'waiting_reason' => null,
                    'next_attempt_at' => null,
                    'claim_token' => null,
                    'claimed_at' => null,
                    'completed_at' => now(),
                ])->save();

                return $run;
            }

            $nextNodeId = trim((string) data_get($output, '_runtime.next_node_id'));
            if ($nextNodeId === '') {
                throw new RuntimeException(__('The approval step has no persisted continuation path.'));
            }

            $step->forceFill([
                'status' => AutomationWorkflowStepRun::STATUS_SUCCEEDED,
                'output' => $output,
                'completed_at' => now(),
            ])->save();
            $run->forceFill([
                'status' => AutomationWorkflowRun::STATUS_QUEUED,
                'output' => $this->mergeRunOutput((array) $run->output, (string) $step->node_id, $output),
                'trace' => AutomationWorkflowTrace::append(
                    (array) $run->trace,
                    'approval.approved',
                    (string) $step->node_id,
                    ['actor_user_id' => (int) $actor->id],
                    $nextNodeId
                ),
                'waiting_reason' => null,
                'next_attempt_at' => now(),
                'claim_token' => null,
                'claimed_at' => null,
                'completed_at' => null,
                'error' => null,
            ])->save();
            $enqueue = true;

            return $run;
        });

        if ($enqueue) {
            $this->dispatcher->enqueue($run);
        }

        return $run->fresh();
    }

    protected function authorize(AutomationWorkflowRun $run, AutomationWorkflowStepRun $step, User $actor): void
    {
        $team = null;
        if ($run->team_id) {
            $team = Team::query()->with('members')->find($run->team_id);
            if (! $team || (int) $team->owner_user_id !== (int) $run->owner_user_id) {
                throw new AuthorizationException(__('The workflow workspace is no longer available.'));
            }
        }

        $isOwner = (int) $actor->id === (int) $run->owner_user_id;
        if (! $team && ! $isOwner) {
            throw new AuthorizationException(__('Only the workspace owner can approve this workflow run.'));
        }
        if ($team && ! $isOwner && ! TeamWorkspaceAccess::hasPermission($actor, 'workflow.approve', $team)) {
            throw new AuthorizationException(__('You do not have permission to approve this workflow run.'));
        }

        $requiredRole = (string) data_get($step->output, 'approver_role', 'admin');
        if ($requiredRole === 'owner' && ! $isOwner) {
            throw new AuthorizationException(__('This workflow step requires approval from the workspace owner.'));
        }
    }

    protected function mergeRunOutput(array $current, string $nodeId, array $output): array
    {
        $nodes = (array) ($current['nodes'] ?? []);
        $nodes[$nodeId] = $output;

        return ['nodes' => $nodes, 'last' => $output];
    }
}
