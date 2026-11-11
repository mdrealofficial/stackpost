<?php

namespace Modules\AppAIAssistant\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\AdminUser\Models\User;
use Modules\AppAIAssistant\Models\AssistantIdea;
use Modules\AppChannels\Models\SocialAccount;
use Modules\AppPublishing\Jobs\PublishScheduledPostJob;
use Modules\AppPublishing\Models\PublishingPost;
use Modules\AppPublishing\Support\PublishingContentValidationRegistry;
use Modules\AppPublishing\Support\PublishingMediaValidationRegistry;
use Modules\AppPublishing\Support\PublishingNetworkConfigRegistry;
use Modules\AppPublishing\Support\PublishingNetworkOptionsRegistry;
use Modules\AppTeams\Models\TeamPostReview;
use Modules\AppTeams\Support\TeamWorkspaceAccess;
use RuntimeException;
use Throwable;

class AssistantPublishingWorkflow
{
    public function __construct(
        protected PublishingContentValidationRegistry $contentValidation,
        protected PublishingMediaValidationRegistry $mediaValidation,
        protected PublishingNetworkConfigRegistry $networkConfigs,
        protected PublishingNetworkOptionsRegistry $networkOptions,
    ) {}

    public function availableAccounts(?User $user): Collection
    {
        if (! $user) {
            return collect();
        }

        $publishableCapabilityKeys = publishable_channel_capability_keys($user);

        return TeamWorkspaceAccess::accessibleAccountsQuery($user)
            ->where('is_active', true)
            ->where(function ($query) use ($publishableCapabilityKeys): void {
                $query->whereIn('capability_key', $publishableCapabilityKeys)
                    ->orWhere(function ($fallbackQuery) use ($publishableCapabilityKeys): void {
                        $fallbackQuery->whereNull('capability_key')
                            ->whereIn('provider_key', $publishableCapabilityKeys);
                    });
            })
            ->orderBy('provider_key')
            ->orderBy('display_name')
            ->get();
    }

    public function execute(
        User $user,
        AssistantIdea $idea,
        array $accountIds,
        string $mode,
        ?string $scheduleAt = null,
    ): array {
        $mode = strtolower(trim($mode));

        if (! in_array($mode, ['draft', 'schedule', 'publish'], true)) {
            throw ValidationException::withMessages([
                'publishMode' => __('Choose draft, schedule, or publish now.'),
            ]);
        }

        $team = TeamWorkspaceAccess::activeTeam($user);
        $planOwner = $team?->owner ?: $user;

        if (! TeamWorkspaceAccess::teamHasModule($team, 'publishing')
            || ! ($planOwner?->canUsePlanFeature('publishing') ?? false)) {
            throw new RuntimeException(__('Publishing is not available in this workspace plan.'));
        }

        if (! TeamWorkspaceAccess::hasPermission($user, 'post.create', $team)) {
            throw new RuntimeException(__('You do not have permission to create publishing posts.'));
        }

        if ($mode === 'publish' && ! TeamWorkspaceAccess::hasPermission($user, 'post.publish', $team)) {
            throw new RuntimeException(__('Your workspace role requires approval before a post can be published.'));
        }

        $accountIds = collect($accountIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        if ($accountIds->isEmpty()) {
            throw ValidationException::withMessages([
                'selectedAccountIds' => __('Select at least one connected account.'),
            ]);
        }

        $accounts = $this->availableAccounts($user)
            ->whereIn('id', $accountIds->all())
            ->keyBy('id');

        if ($accounts->count() !== $accountIds->count()) {
            throw ValidationException::withMessages([
                'selectedAccountIds' => __('One or more selected accounts are no longer available in this workspace.'),
            ]);
        }

        $timezone = (string) ($user->timezone ?: config('app.timezone', 'UTC'));
        $resolvedSchedule = $this->resolveSchedule($mode, $scheduleAt, $timezone);
        $requiresApproval = $mode === 'schedule'
            && ! TeamWorkspaceAccess::hasPermission($user, 'post.publish', $team);

        $prepared = $accountIds->map(function (int $accountId) use ($accounts, $idea, $mode): array {
            /** @var SocialAccount $account */
            $account = $accounts->get($accountId);
            $caption = $idea->captionForProvider((string) $account->provider_key);
            $config = $this->networkConfigs->get((string) $account->provider_key, [
                'label' => Str::headline((string) $account->provider_key),
                'default_post_to' => 'feed',
                'post_to_options' => [
                    ['key' => 'feed', 'label' => __('Feed')],
                ],
            ]);
            $resolvedOptions = $this->networkOptions->resolve((string) $account->provider_key, [], $config);
            $options = (array) ($resolvedOptions['options'] ?? []);

            if ($caption === '') {
                throw ValidationException::withMessages([
                    'selectedAccountIds' => __('No caption is available for :account.', [
                        'account' => $account->display_name ?: $account->username ?: Str::headline((string) $account->provider_key),
                    ]),
                ]);
            }

            // Incomplete media requirements are acceptable for a safe draft. They
            // become mandatory before scheduling or publishing to a provider.
            if ($mode !== 'draft') {
                $context = [
                    'provider_key' => (string) $account->provider_key,
                    'options' => $options,
                    'caption' => $caption,
                    'media_items' => [],
                ];
                $contentError = $this->contentValidation->validate((string) $account->provider_key, $context);
                $mediaError = $this->mediaValidation->validate((string) $account->provider_key, $context);
                $message = trim((string) ($contentError['message'] ?? $mediaError ?? ''));

                if ($message !== '') {
                    throw ValidationException::withMessages([
                        'selectedAccountIds' => __(':account: :message Save it as a draft, add the required media in Publishing, then schedule it.', [
                            'account' => $account->display_name ?: $account->username ?: Str::headline((string) $account->provider_key),
                            'message' => $message,
                        ]),
                    ]);
                }
            }

            return [
                'account' => $account,
                'caption' => $caption,
                'options' => $options,
                'media_type' => (string) ($resolvedOptions['media_type'] ?? ''),
            ];
        });

        $postIds = DB::transaction(function () use (
            $prepared,
            $user,
            $team,
            $idea,
            $mode,
            $resolvedSchedule,
            $timezone,
            $requiresApproval,
        ): array {
            $nowTimestamp = now()->timestamp;
            $ids = [];

            foreach ($prepared as $item) {
                /** @var SocialAccount $account */
                $account = $item['account'];
                $status = $mode === 'draft' || $requiresApproval
                    ? PublishingPost::STATUS_DRAFT
                    : PublishingPost::STATUS_SCHEDULED;
                $post = PublishingPost::query()->create([
                    'id_secure' => Str::random(32),
                    'user_id' => (int) $user->id,
                    'team_id' => $team?->id,
                    'campaign' => null,
                    'labels' => [],
                    'account_id' => (int) $account->id,
                    'social_network' => (string) $account->provider_key,
                    'category' => (string) ($account->capability_key ?: $account->category ?: $account->provider_key),
                    'module' => (string) $account->provider_key,
                    'function' => 'post',
                    'api_type' => 1,
                    'type' => 'text',
                    'method' => 'basic',
                    'query_id' => null,
                    'data' => [
                        'title' => trim((string) Str::limit($item['caption'], 46, '...')),
                        'caption' => $item['caption'],
                        'media_type' => '',
                        'medias' => [],
                        'ai' => [
                            'assistant_idea_id' => (int) $idea->id,
                            'caption_variants' => array_values((array) $idea->variants),
                            'source' => (string) ($idea->generated_source ?: 'manual'),
                        ],
                        'options' => [
                            ...$item['options'],
                            'schedule_mode' => $mode === 'schedule' ? 'specific_days_times' : 'immediately',
                            'schedule_slot_index' => 0,
                            'timezone' => $timezone,
                            'notes' => __('Created from AI Assistant idea #:id', ['id' => $idea->id]),
                            'assistant_idea_id' => (int) $idea->id,
                            'assistant_requires_media_review' => true,
                        ],
                    ],
                    'time_post' => $resolvedSchedule->timestamp,
                    'delay' => 0,
                    'repost_frequency' => 0,
                    'repost_until' => null,
                    'result' => null,
                    'tmp' => null,
                    'custom_data_1' => null,
                    'custom_data_2' => null,
                    'custom_data_3' => null,
                    'status' => $status,
                    'changed' => $nowTimestamp,
                    'created' => $nowTimestamp,
                ]);

                $ids[] = (int) $post->id;

                if ($requiresApproval && $team) {
                    TeamPostReview::query()->create([
                        'team_id' => (int) $team->id,
                        'post_id' => (int) $post->id,
                        'submitted_by_user_id' => (int) $user->id,
                        'status' => 'pending',
                        'submitted_at' => now(),
                        'metadata' => [
                            'requested_status' => PublishingPost::STATUS_SCHEDULED,
                            'schedule_mode' => 'specific_days_times',
                            'assistant_idea_id' => (int) $idea->id,
                        ],
                    ]);
                }
            }

            return $ids;
        });

        $publishErrors = [];

        if ($mode === 'publish') {
            foreach ($postIds as $postId) {
                try {
                    PublishScheduledPostJob::dispatchSync($postId);
                    $post = PublishingPost::query()->find($postId);

                    if ((int) ($post?->status ?? 0) === PublishingPost::STATUS_FAILED) {
                        $publishErrors[] = trim((string) data_get($post?->result, 'error', __('The provider rejected this post.')));
                    }
                } catch (Throwable $exception) {
                    $publishErrors[] = trim((string) $exception->getMessage());
                }
            }
        }

        $status = match (true) {
            $publishErrors !== [] => 'failed',
            $mode === 'publish' => 'published',
            $requiresApproval => 'pending_approval',
            $mode === 'schedule' => 'scheduled',
            default => 'draft',
        };

        return [
            'post_ids' => $postIds,
            'status' => $status,
            'requires_approval' => $requiresApproval,
            'errors' => array_values(array_filter($publishErrors)),
            'message' => match ($status) {
                'published' => __('The approved posts were sent to the selected accounts.'),
                'scheduled' => __('The approved posts were added to the publishing calendar.'),
                'pending_approval' => __('The posts were submitted for workspace approval.'),
                'failed' => __('The posts were created, but one or more providers rejected the publish action.'),
                default => __('Draft posts were created. Add media or final network options in Publishing when needed.'),
            },
        ];
    }

    protected function resolveSchedule(string $mode, ?string $scheduleAt, string $timezone): CarbonInterface
    {
        if ($mode !== 'schedule') {
            return now($timezone);
        }

        if (! filled($scheduleAt)) {
            throw ValidationException::withMessages([
                'scheduleAt' => __('Choose a date and time for the scheduled post.'),
            ]);
        }

        try {
            $resolved = Carbon::parse((string) $scheduleAt, $timezone);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'scheduleAt' => __('Choose a valid schedule date and time.'),
            ]);
        }

        if ($resolved->lessThanOrEqualTo(now($timezone))) {
            throw ValidationException::withMessages([
                'scheduleAt' => __('The schedule time must be in the future.'),
            ]);
        }

        return $resolved;
    }
}
