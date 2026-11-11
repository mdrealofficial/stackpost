<?php

namespace Modules\AppAutomation\Services;

use Modules\AdminUser\Models\User;
use Modules\AppAutomation\Models\AutomationWorkflowRun;
use Modules\AppAutomation\Models\AutomationWorkflowStepRun;
use Modules\AppTeams\Models\TeamPostReview;
use Throwable;

class AutomationWorkflowApprovalReconciler
{
    public function __construct(protected AutomationWorkflowApprovalService $approval) {}

    public function reconcileReview(TeamPostReview $review): string
    {
        $metadata = (array) $review->metadata;
        $runId = (int) ($metadata['workflow_run_id'] ?? 0);
        $stepId = (int) ($metadata['workflow_step_id'] ?? 0);

        if (($metadata['source'] ?? null) !== 'automation_workflow' || $runId <= 0 || $stepId <= 0) {
            return 'ignored';
        }

        $step = AutomationWorkflowStepRun::query()
            ->whereKey($stepId)
            ->where('run_id', $runId)
            ->first();

        if (! $step) {
            return 'missing';
        }

        return $this->reconcileStep($step);
    }

    /**
     * @return array{checked:int,approved:int,rejected:int,waiting:int,missing:int,failed:int}
     */
    public function reconcileWaiting(int $limit = 100): array
    {
        $stats = ['checked' => 0, 'approved' => 0, 'rejected' => 0, 'waiting' => 0, 'missing' => 0, 'failed' => 0];

        $steps = AutomationWorkflowStepRun::query()
            ->where('status', AutomationWorkflowStepRun::STATUS_WAITING_APPROVAL)
            ->whereHas('run', fn ($query) => $query->where('status', AutomationWorkflowRun::STATUS_WAITING_APPROVAL))
            ->orderBy('id')
            ->limit(max(1, min(1000, $limit)))
            ->get();

        foreach ($steps as $step) {
            $stats['checked']++;

            try {
                $result = $this->reconcileStep($step);
                if (array_key_exists($result, $stats)) {
                    $stats[$result]++;
                }
            } catch (Throwable $exception) {
                report($exception);
                $stats['failed']++;
            }
        }

        return $stats;
    }

    protected function reconcileStep(AutomationWorkflowStepRun $step): string
    {
        $run = $step->run;
        if (! $run || (string) $run->status !== AutomationWorkflowRun::STATUS_WAITING_APPROVAL
            || (string) $step->status !== AutomationWorkflowStepRun::STATUS_WAITING_APPROVAL) {
            return 'ignored';
        }

        $reviewIds = collect((array) data_get($step->output, 'approval.review_ids', []))
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($reviewIds->isEmpty()) {
            return 'waiting';
        }

        $reviews = TeamPostReview::query()->whereIn('id', $reviewIds->all())->get();
        if ($reviews->count() !== $reviewIds->count()
            || $reviews->contains(fn (TeamPostReview $review): bool => ! $this->belongsToStep($review, $run, $step))) {
            return 'missing';
        }

        $rejected = $reviews
            ->where('status', 'rejected')
            ->sortByDesc('decided_at')
            ->first();
        if ($rejected) {
            $actor = User::query()->find((int) $rejected->decided_by_user_id);
            if (! $actor) {
                return 'missing';
            }

            $this->approval->reject((int) $run->id, (int) $step->id, $actor, $rejected->decision_note);

            return 'rejected';
        }

        if (! $reviews->every(fn (TeamPostReview $review): bool => (string) $review->status === 'approved')) {
            return 'waiting';
        }

        $lastApproval = $reviews->sortByDesc('decided_at')->first();
        $actor = $lastApproval ? User::query()->find((int) $lastApproval->decided_by_user_id) : null;
        if (! $actor) {
            return 'missing';
        }

        $this->approval->approve((int) $run->id, (int) $step->id, $actor, $lastApproval->decision_note);

        return 'approved';
    }

    protected function belongsToStep(
        TeamPostReview $review,
        AutomationWorkflowRun $run,
        AutomationWorkflowStepRun $step,
    ): bool {
        $metadata = (array) $review->metadata;

        return ($metadata['source'] ?? null) === 'automation_workflow'
            && (int) ($metadata['workflow_run_id'] ?? 0) === (int) $run->id
            && (int) ($metadata['workflow_step_id'] ?? 0) === (int) $step->id
            && (int) $review->team_id === (int) $run->team_id;
    }
}
