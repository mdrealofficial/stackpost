<?php

namespace Modules\AppAutomation\Services;

use Illuminate\Support\Facades\DB;
use Modules\AdminUser\Models\User;
use Modules\AppAutomation\Models\AutomationWorkflowRun;
use Modules\AppAutomation\Support\AutomationAccess;
use Modules\AppAutomation\Support\AutomationWorkflowTrace;
use RuntimeException;

final class AutomationWorkflowRecovery
{
    public function __construct(protected AutomationAccess $access, protected AutomationWorkflowDispatcher $dispatcher, protected AutomationWorkflowRuntimeReadiness $readiness) {}

    public function recover(int $id, User $actor, string $action): void
    {
        abort_unless($this->access->canExecute($actor), 403);
        DB::transaction(function () use ($id, $actor, $action) {
            $run = AutomationWorkflowRun::where('owner_user_id', $this->access->workspaceOwnerUserId($actor))
                ->where('team_id', $this->access->teamId($actor))->lockForUpdate()->findOrFail($id);
            if ($action === 'cancel') {
                if (! in_array($run->status, ['queued', 'retrying', 'waiting_delay'], true)) {
                    throw new RuntimeException(__('Only a queued or delayed run can be stopped. Refresh to see its current state.'));
                }
                $run->update(['status' => 'cancelled', 'next_attempt_at' => null, 'waiting_reason' => null, 'claim_token' => null, 'completed_at' => now(),
                    'trace' => AutomationWorkflowTrace::append((array) $run->trace, 'run.cancelled', null, ['actor_user_id' => $actor->id])]);
                return;
            }
            if ($action !== 'retry' || $run->status !== 'failed') {
                throw new RuntimeException(__('Only a failed run can be retried.'));
            }
            $workflow = $run->workflow;
            if ($workflow && ((int) $workflow->owner_user_id !== (int) $run->owner_user_id || (int) $workflow->team_id !== (int) $run->team_id)) {
                throw new RuntimeException(__('The original workflow workspace is no longer available.'));
            }
            if (! $workflow || $workflow->status !== 'active') {
                throw new RuntimeException(__('Activate the workflow before retrying a run.'));
            }
            $revision = $workflow->revisions()->where('revision', $run->workflow_revision)->first();
            if (! $revision) {
                throw new RuntimeException(__('The original workflow version is unavailable.'));
            }
            $this->readiness->assertRunnable($workflow, (array) $revision->definition, (int) $actor->id);
            $cursor = (string) data_get($run->trace, 'cursor');
            $node = collect((array) data_get($revision->definition, 'nodes', []))->firstWhere('id', $cursor);
            if (! $node || ! in_array($node['type'], ['trigger_google_drive', 'condition', 'delay', 'import_drive_file', 'ai_write', 'create_draft', 'approval', 'schedule_post', 'end'], true)) {
                throw new RuntimeException(__('This failed step cannot be resumed from the workspace.'));
            }
            $step = $run->steps()->where('node_id', $cursor)->latest('id')->lockForUpdate()->first();
            if ($step && ! in_array($step->status, ['failed', 'succeeded'], true)) {
                throw new RuntimeException(__('This step is not ready to retry.'));
            }
            if ((int) ($step?->attempt ?? 0) >= 10) {
                throw new RuntimeException(__('This step has reached its recovery limit. Review its setup before creating another workflow.'));
            }
            $step?->update(['available_at' => null]);
            $run->update(['status' => 'queued', 'requested_by_user_id' => $actor->id,
                'max_attempts' => max((int) $run->max_attempts, (int) ($step?->attempt ?? 0) + 1),
                'next_attempt_at' => now(), 'waiting_reason' => null, 'claim_token' => null, 'claimed_at' => null,
                'completed_at' => null, 'error' => null,
                'trace' => AutomationWorkflowTrace::append((array) $run->trace, 'run.retry_requested', $cursor, ['actor_user_id' => $actor->id])]);
            $this->dispatcher->enqueue($run);
        });
    }
}
