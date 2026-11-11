<?php

namespace Modules\AppAutomation\Runtime\Handlers;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Modules\AppAIStudio\Support\AiContentStudioService;
use Modules\AppAutomation\Contracts\AutomationNodeHandler;
use Modules\AppAutomation\Exceptions\AutomationNodeException;
use Modules\AppAutomation\Models\AutomationWorkflowRun;
use Modules\AppAutomation\Models\AutomationWorkflowStepRun;
use Modules\AppAutomation\Runtime\AutomationNodeContext;
use Modules\AppAutomation\Runtime\AutomationNodeResult;
use Modules\AppAutomation\Support\AutomationWorkflowWorkspaceGuard;
use Modules\AppChannels\Models\SocialAccount;
use Modules\AppPublishing\Models\PublishingPost;
use Modules\AppPublishing\Support\PublishingContentValidationRegistry;
use Modules\AppPublishing\Support\PublishingMediaValidationRegistry;
use Modules\AppPublishing\Support\PublishingNetworkConfigRegistry;
use Modules\AppPublishing\Support\PublishingNetworkOptionsRegistry;
use Modules\AppTeams\Models\TeamPostReview;

class SchedulePostNodeHandler implements AutomationNodeHandler
{
    public function __construct(
        protected AutomationWorkflowWorkspaceGuard $guard,
        protected PublishingContentValidationRegistry $contentValidation,
        protected PublishingMediaValidationRegistry $mediaValidation,
        protected PublishingNetworkConfigRegistry $networkConfigs,
        protected PublishingNetworkOptionsRegistry $networkOptions,
        protected AiContentStudioService $studio,
    ) {}

    public function handle(AutomationNodeContext $context): AutomationNodeResult
    {
        $actor = $this->guard->resolve($context, ['workflow.execute', 'post.create']);
        $payload = $context->payload();
        $postIds = collect((array) ($payload['post_ids'] ?? []))
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();
        if ($postIds->isEmpty()) {
            throw AutomationNodeException::permanent(__('The Schedule node did not receive any publishing drafts.'));
        }
        if ((string) data_get($payload, 'approval.decision') !== 'approved') {
            throw AutomationNodeException::permanent(__('Publishing drafts must complete the workflow approval step before scheduling.'));
        }

        $posts = PublishingPost::query()
            ->where('user_id', $context->ownerUserId())
            ->where('team_id', $context->teamId())
            ->where('function', 'post')
            ->whereIn('id', $postIds->all())
            ->get()
            ->keyBy('id');
        if ($posts->count() !== $postIds->count()) {
            throw AutomationNodeException::permanent(__('One or more approved drafts are unavailable in this workflow workspace.'));
        }
        $replayed = $posts->filter(fn (PublishingPost $post): bool => $this->matchesScheduleEffect($post, $context));
        $drafts = $posts->filter(fn (PublishingPost $post): bool => (int) $post->status === PublishingPost::STATUS_DRAFT);
        if ($replayed->count() + $drafts->count() !== $posts->count()) {
            throw AutomationNodeException::permanent(__('A publishing post was already changed or scheduled outside this workflow run.'));
        }

        $timezone = $this->resolveTimezone(
            (string) $context->config('timezone', 'workspace'),
            (string) $actor['owner']->timezone,
        );
        $strategy = (string) $context->config('strategy', 'best_time');
        if (! in_array($strategy, ['best_time', 'fixed_time', 'next_slot'], true)) {
            throw AutomationNodeException::permanent(__('The scheduling strategy is not supported.'));
        }

        if ($drafts->isEmpty()) {
            return $this->scheduleResult(
                $payload,
                $strategy,
                $timezone,
                $postIds->map(fn (int $postId): PublishingPost => $replayed->get($postId))->values(),
                $postIds->all(),
                collect(),
            );
        }

        $accounts = $this->guard->publishingAccounts($actor, $drafts->pluck('account_id')->map(fn ($id): int => (int) $id)->all())
            ->keyBy('id');
        $this->assertPublishingReviewsApproved($context, $drafts->values()->all());

        $prepared = $drafts->keys()->map(function (int $postId) use ($posts, $accounts, $context, $strategy, $timezone): array {
            /** @var PublishingPost $post */
            $post = $posts->get($postId);
            /** @var SocialAccount $account */
            $account = $accounts->get((int) $post->account_id);
            $providerKey = strtolower(trim((string) $account->provider_key));
            $data = (array) $post->data;
            $config = $this->networkConfigs->get($providerKey, [
                'default_post_to' => 'feed',
                'post_to_options' => [['key' => 'feed', 'label' => __('Feed')]],
            ]);
            $resolved = $this->networkOptions->resolve($providerKey, (array) ($data['options'] ?? []), $config);
            $options = (array) ($resolved['options'] ?? []);
            $validationContext = [
                'provider_key' => $providerKey,
                'account' => $account,
                'caption' => trim((string) ($data['caption'] ?? '')),
                'options' => $options,
                'media_items' => array_values((array) ($data['medias'] ?? [])),
            ];
            $contentError = $this->contentValidation->validate($providerKey, $validationContext);
            $mediaError = $this->mediaValidation->validate($providerKey, $validationContext);
            $message = trim((string) ($contentError['message'] ?? $mediaError ?? ''));
            if ($message !== '') {
                throw AutomationNodeException::permanent(__(':account cannot be scheduled: :message', [
                    'account' => $account->display_name ?: $account->username ?: $providerKey,
                    'message' => $message,
                ]));
            }

            [$scheduleAt, $scheduleSource] = $this->scheduleFor(
                $strategy,
                $context,
                $timezone,
                (int) $post->account_id,
                $context->ownerUserId(),
            );

            return [
                'post' => $post,
                'options' => $options,
                'schedule_at' => $scheduleAt,
                'schedule_source' => $scheduleSource,
            ];
        });

        $newlyScheduled = $prepared->isEmpty()
            ? collect()
            : DB::transaction(function () use ($context, $prepared, $timezone, $strategy) {
                $run = AutomationWorkflowRun::query()->whereKey($context->run->id)->lockForUpdate()->firstOrFail();
                $step = AutomationWorkflowStepRun::query()->whereKey($context->step->id)->lockForUpdate()->firstOrFail();
                if ((string) $run->status !== AutomationWorkflowRun::STATUS_RUNNING
                    || blank($context->run->claim_token)
                    || (string) $run->claim_token !== (string) $context->run->claim_token
                    || (string) $step->status !== AutomationWorkflowStepRun::STATUS_RUNNING
                    || blank($context->step->claim_token)
                    || (string) $step->claim_token !== (string) $context->step->claim_token) {
                    throw new AutomationNodeException(__('This worker no longer owns the scheduling node lease.'));
                }

                SocialAccount::query()
                    ->whereIn('id', $prepared->map(fn (array $item): int => (int) $item['post']->account_id)->all())
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                return $prepared->map(function (array $item) use ($context, $timezone, $strategy): PublishingPost {
                    /** @var PublishingPost $post */
                    $post = PublishingPost::query()->whereKey($item['post']->id)->lockForUpdate()->firstOrFail();
                    if ((int) $post->user_id !== $context->ownerUserId()
                        || (int) $post->team_id !== (int) $context->teamId()) {
                        throw AutomationNodeException::permanent(__('An approved draft moved outside this workflow workspace.'));
                    }
                    if ($this->matchesScheduleEffect($post, $context)) {
                        return $post->refresh();
                    }
                    if ((int) $post->status !== PublishingPost::STATUS_DRAFT) {
                        throw AutomationNodeException::permanent(__('An approved draft changed before the workflow could schedule it.'));
                    }

                    $scheduleAt = $strategy === 'fixed_time'
                        ? $item['schedule_at']
                        : $this->avoidAccountCollision(
                            $item['schedule_at'],
                            (int) $post->account_id,
                            $strategy === 'best_time' && $item['schedule_source'] === 'workspace_analytics' ? 'week' : 'minutes',
                        );
                    $data = (array) $post->data;
                    $data['options'] = [
                        ...$item['options'],
                        'schedule_mode' => 'specific_days_times',
                        'schedule_slot_index' => 0,
                        'timezone' => $timezone,
                        'automation_schedule' => [
                            'strategy' => $strategy,
                            'source' => $item['schedule_source'],
                            'workflow_id' => (int) $context->run->workflow_id,
                            'run_id' => (int) $context->run->id,
                            'step_id' => (int) $context->step->id,
                            'node_id' => (string) $context->step->node_id,
                        ],
                    ];
                    $post->forceFill([
                        'data' => $data,
                        'time_post' => $scheduleAt->timestamp,
                        'status' => PublishingPost::STATUS_SCHEDULED,
                        'changed' => now()->timestamp,
                    ])->save();

                    return $post->refresh();
                })->values();
            });
        $scheduled = $postIds
            ->map(fn (int $postId): ?PublishingPost => $newlyScheduled->firstWhere('id', $postId) ?: $replayed->get($postId))
            ->filter()
            ->values();
        $replayedIds = $scheduled
            ->filter(function (PublishingPost $post) use ($context, $drafts): bool {
                if (! $drafts->has((int) $post->id)) {
                    return true;
                }

                return (int) data_get($post->data, 'options.automation_schedule.step_id', 0) !== (int) $context->step->id;
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return $this->scheduleResult($payload, $strategy, $timezone, $scheduled, $replayedIds, $prepared);
    }

    protected function scheduleResult(
        array $payload,
        string $strategy,
        string $timezone,
        \Illuminate\Support\Collection $scheduled,
        array $replayedIds,
        \Illuminate\Support\Collection $prepared,
    ): AutomationNodeResult {
        return AutomationNodeResult::success([
            ...$payload,
            'status' => 'scheduled',
            'strategy' => $strategy,
            'timezone' => $timezone,
            'scheduled_count' => $scheduled->count(),
            'replayed_count' => count($replayedIds),
            'scheduled_posts' => $scheduled->map(function (PublishingPost $post) use ($prepared, $replayedIds, $timezone): array {
                $item = $prepared->first(fn (array $candidate): bool => (int) $candidate['post']->id === (int) $post->id);
                $automation = (array) data_get($post->data, 'options.automation_schedule', []);

                return [
                    'post_id' => (int) $post->id,
                    'account_id' => (int) $post->account_id,
                    'provider' => (string) $post->social_network,
                    'scheduled_at' => Carbon::createFromTimestamp((int) $post->time_post, $timezone)->toIso8601String(),
                    'source' => (string) ($item['schedule_source'] ?? $automation['source'] ?? 'calendar'),
                    'replayed' => in_array((int) $post->id, $replayedIds, true),
                ];
            })->all(),
        ]);
    }

    protected function matchesScheduleEffect(PublishingPost $post, AutomationNodeContext $context): bool
    {
        if ((int) $post->status !== PublishingPost::STATUS_SCHEDULED) {
            return false;
        }

        $automation = (array) data_get($post->data, 'options.automation_schedule', []);

        return (int) ($automation['workflow_id'] ?? 0) === (int) $context->run->workflow_id
            && (int) ($automation['run_id'] ?? 0) === (int) $context->run->id
            && (string) ($automation['node_id'] ?? '') === (string) $context->step->node_id;
    }

    /** @param array<int, PublishingPost> $posts */
    protected function assertPublishingReviewsApproved(AutomationNodeContext $context, array $posts): void
    {
        if (! $context->teamId()) {
            return;
        }

        foreach ($posts as $post) {
            $review = TeamPostReview::query()
                ->where('team_id', $context->teamId())
                ->where('post_id', $post->id)
                ->orderByDesc('id')
                ->get()
                ->first(fn (TeamPostReview $item): bool => (int) data_get($item->metadata, 'workflow_run_id') === (int) $context->run->id);

            if (! $review || (string) $review->status !== 'approved') {
                throw AutomationNodeException::permanent(__('Every draft must have an approved Team Post Review from this workflow run before scheduling.'));
            }
        }
    }

    /** @return array{0: CarbonInterface, 1: string} */
    protected function scheduleFor(
        string $strategy,
        AutomationNodeContext $context,
        string $timezone,
        int $accountId,
        int $ownerUserId,
    ): array {
        if ($strategy === 'fixed_time') {
            $value = trim((string) $context->config('fixed_time', '09:00'));
            if (! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value)) {
                throw AutomationNodeException::permanent(__('The fixed scheduling time must use 24-hour HH:MM format.'));
            }

            [$hour, $minute] = array_map('intval', explode(':', $value));
            $candidate = now($timezone)->copy()->setTime($hour, $minute, 0);
            if ($candidate->lessThanOrEqualTo(now($timezone)->addMinutes(10))) {
                $candidate->addDay();
            }

            return [$candidate, 'fixed_time'];
        }

        if ($strategy === 'best_time') {
            $historyExists = PublishingPost::query()
                ->ownedBy($ownerUserId)
                ->where('account_id', $accountId)
                ->where('function', 'post')
                ->whereIn('status', [PublishingPost::STATUS_SCHEDULED, PublishingPost::STATUS_PUBLISHED])
                ->whereNotNull('time_post')
                ->exists();
            if ($historyExists) {
                $suggestion = collect($this->studio->bestTimeSuggestions($ownerUserId, [$accountId], $timezone, 4))->first();
                if (is_array($suggestion)) {
                    $candidate = $this->nextBestTime($suggestion, $timezone);

                    return [$this->avoidAccountCollision($candidate, $accountId, 'week'), 'workspace_analytics'];
                }
            }
        }

        return [$this->nextOpenSlot($accountId, $timezone), $strategy === 'best_time' ? 'next_slot_fallback' : 'next_slot'];
    }

    protected function nextBestTime(array $suggestion, string $timezone): CarbonInterface
    {
        $weekdays = ['mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6, 'sun' => 7];
        $targetDay = $weekdays[strtolower((string) ($suggestion['weekday'] ?? ''))] ?? null;
        $hour = (int) ($suggestion['hour'] ?? -1);
        if (! $targetDay || $hour < 0 || $hour > 23) {
            throw AutomationNodeException::permanent(__('The Best Time service returned an invalid calendar slot.'));
        }

        $now = now($timezone);
        $candidate = $now->copy()->startOfDay()->addDays(($targetDay - $now->dayOfWeekIso + 7) % 7)->setTime($hour, 0, 0);
        if ($candidate->lessThanOrEqualTo($now->copy()->addMinutes(10))) {
            $candidate->addWeek();
        }

        return $candidate;
    }

    protected function nextOpenSlot(int $accountId, string $timezone): CarbonInterface
    {
        $candidate = now($timezone)->addMinutes(15)->second(0);
        $minute = (int) $candidate->minute;
        if ($minute === 0 || $minute <= 30) {
            $candidate->minute(30);
        } else {
            $candidate->addHour()->minute(0);
        }

        return $this->avoidAccountCollision($candidate, $accountId, 'minutes');
    }

    protected function avoidAccountCollision(CarbonInterface $candidate, int $accountId, string $increment): CarbonInterface
    {
        $resolved = Carbon::instance($candidate);
        for ($attempt = 0; $attempt < 96; $attempt++) {
            $occupied = PublishingPost::query()
                ->where('account_id', $accountId)
                ->where('function', 'post')
                ->where('status', PublishingPost::STATUS_SCHEDULED)
                ->where('time_post', $resolved->timestamp)
                ->exists();
            if (! $occupied) {
                return $resolved;
            }

            $increment === 'week' ? $resolved->addWeek() : $resolved->addMinutes(30);
        }

        throw AutomationNodeException::permanent(__('No open publishing slot could be found for the selected account.'));
    }

    protected function resolveTimezone(string $configured, string $ownerTimezone): string
    {
        if ($configured !== 'workspace' && trim($configured) !== '') {
            try {
                new \DateTimeZone($configured);

                return $configured;
            } catch (\Throwable) {
                throw AutomationNodeException::permanent(__('The Schedule node timezone is invalid.'));
            }
        }

        $candidates = $configured === 'workspace'
            ? [$ownerTimezone, (string) config('app.timezone', 'UTC'), 'UTC']
            : [$ownerTimezone, (string) config('app.timezone', 'UTC'), 'UTC'];

        foreach ($candidates as $timezone) {
            try {
                if (trim($timezone) !== '') {
                    new \DateTimeZone($timezone);

                    return $timezone;
                }
            } catch (\Throwable) {
            }
        }

        return 'UTC';
    }
}
