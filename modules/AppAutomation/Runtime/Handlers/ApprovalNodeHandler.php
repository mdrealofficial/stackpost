<?php

namespace Modules\AppAutomation\Runtime\Handlers;

use Illuminate\Support\Facades\DB;
use Modules\AppAutomation\Contracts\AutomationNodeHandler;
use Modules\AppAutomation\Exceptions\AutomationNodeException;
use Modules\AppAutomation\Models\AutomationWorkflowRun;
use Modules\AppAutomation\Runtime\AutomationNodeContext;
use Modules\AppAutomation\Runtime\AutomationNodeResult;
use Modules\AppAutomation\Support\AutomationWorkflowWorkspaceGuard;
use Modules\AppPublishing\Models\PublishingPost;
use Modules\AppTeams\Models\TeamPostReview;

class ApprovalNodeHandler implements AutomationNodeHandler
{
    public function __construct(protected AutomationWorkflowWorkspaceGuard $guard) {}

    public function handle(AutomationNodeContext $context): AutomationNodeResult
    {
        $mode = (string) $context->config('mode', 'always');
        $previous = $context->lastOutput();
        $postIds = collect((array) ($previous['post_ids'] ?? []))
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($postIds->isNotEmpty()) {
            return $this->handlePublishingApproval($context, $previous, $postIds->all(), $mode);
        }

        $confidence = is_numeric($previous['confidence'] ?? null)
            ? max(0, min(100, (int) $previous['confidence']))
            : null;

        if ($mode === 'low_confidence'
            && (($previous['requires_approval'] ?? null) === false || ($confidence !== null && $confidence >= 90))) {
            return AutomationNodeResult::success([
                ...$previous,
                'approval' => 'not_required',
                'mode' => $mode,
                'confidence' => $confidence,
            ]);
        }

        return AutomationNodeResult::waitForApproval([
            ...$previous,
            'approval' => ['status' => 'pending'],
            'mode' => $mode,
            'approver_role' => (string) $context->config('approver_role', 'admin'),
            'preview' => $previous,
        ]);
    }

    /** @param array<int, int> $postIds */
    protected function handlePublishingApproval(
        AutomationNodeContext $context,
        array $previous,
        array $postIds,
        string $mode,
    ): AutomationNodeResult {
        $actor = $this->guard->resolve($context, ['workflow.execute', 'post.create']);
        if (! $actor['team']) {
            throw AutomationNodeException::permanent(__('Publishing workflow approvals require an explicit team workspace.'));
        }

        $posts = PublishingPost::query()
            ->where('user_id', $context->ownerUserId())
            ->where('team_id', $context->teamId())
            ->where('function', 'post')
            ->whereIn('id', $postIds)
            ->get()
            ->keyBy('id');
        if ($posts->count() !== count($postIds)) {
            throw AutomationNodeException::permanent(__('One or more publishing drafts are unavailable in this workflow workspace.'));
        }
        if ($posts->contains(fn (PublishingPost $post): bool => (int) $post->status !== PublishingPost::STATUS_DRAFT)) {
            throw AutomationNodeException::permanent(__('Only drafts can enter the workflow approval step.'));
        }

        $approverRole = (string) $context->config('approver_role', 'admin');
        $reviewIds = DB::transaction(function () use ($context, $actor, $postIds, $approverRole): array {
            AutomationWorkflowRun::query()->whereKey($context->run->id)->lockForUpdate()->firstOrFail();

            return collect($postIds)->map(function (int $postId) use ($context, $actor, $approverRole): int {
                $existing = TeamPostReview::query()
                    ->where('team_id', $context->teamId())
                    ->where('post_id', $postId)
                    ->orderByDesc('id')
                    ->get()
                    ->first(function (TeamPostReview $review) use ($context): bool {
                        return (int) data_get($review->metadata, 'workflow_run_id') === (int) $context->run->id
                            && (int) data_get($review->metadata, 'workflow_step_id') === (int) $context->step->id;
                    });

                if ($existing) {
                    return (int) $existing->id;
                }

                return (int) TeamPostReview::query()->create([
                    'team_id' => (int) $context->teamId(),
                    'post_id' => $postId,
                    'submitted_by_user_id' => (int) $actor['requester']->id,
                    'status' => 'pending',
                    'submitted_at' => now(),
                    'metadata' => [
                        'source' => 'automation_workflow',
                        'requested_status' => PublishingPost::STATUS_DRAFT,
                        'schedule_mode' => 'after_workflow_approval',
                        'workflow_id' => (int) $context->run->workflow_id,
                        'workflow_run_id' => (int) $context->run->id,
                        'workflow_step_id' => (int) $context->step->id,
                        'workflow_node_id' => (string) $context->step->node_id,
                        'workflow_idempotency_key' => $context->idempotencyKey(),
                        'approver_role' => $approverRole,
                    ],
                ])->id;
            })->all();
        });

        $reviews = TeamPostReview::query()->whereIn('id', $reviewIds)->get();
        if ($reviews->contains(fn (TeamPostReview $review): bool => (string) $review->status === 'rejected')) {
            throw AutomationNodeException::permanent(__('A required publishing review rejected this workflow action.'));
        }
        if ($reviews->isNotEmpty() && $reviews->every(fn (TeamPostReview $review): bool => (string) $review->status === 'approved')) {
            return AutomationNodeResult::success([
                ...$previous,
                'approval' => ['status' => 'approved', 'review_ids' => $reviewIds],
                'mode' => $mode,
                'approver_role' => $approverRole,
            ]);
        }

        return AutomationNodeResult::waitForApproval([
            ...$previous,
            'approval' => [
                'status' => 'pending',
                'review_ids' => $reviewIds,
                'post_ids' => $postIds,
            ],
            'mode' => $mode,
            'approver_role' => $approverRole,
            'preview' => [
                'post_ids' => $postIds,
                'posts' => $previous['posts'] ?? [],
                'campaign_id' => $previous['campaign_id'] ?? null,
                'label_ids' => $previous['label_ids'] ?? [],
            ],
        ]);
    }
}
