<?php

namespace Modules\AppAutomation\Observers;

use Illuminate\Support\Facades\DB;
use Modules\AppAutomation\Services\AutomationWorkflowApprovalReconciler;
use Modules\AppTeams\Models\TeamPostReview;
use Throwable;

class AutomationTeamPostReviewObserver
{
    public function updated(TeamPostReview $review): void
    {
        if (! $review->wasChanged('status') || ! in_array((string) $review->status, ['approved', 'rejected'], true)) {
            return;
        }

        $metadata = (array) $review->metadata;
        if (($metadata['source'] ?? null) !== 'automation_workflow'
            || (int) ($metadata['workflow_run_id'] ?? 0) <= 0
            || (int) ($metadata['workflow_step_id'] ?? 0) <= 0
            || (int) $review->decided_by_user_id <= 0) {
            return;
        }

        DB::afterCommit(function () use ($review): void {
            try {
                app(AutomationWorkflowApprovalReconciler::class)->reconcileReview($review->fresh());
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }
}
