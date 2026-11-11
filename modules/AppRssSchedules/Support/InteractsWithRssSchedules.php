<?php

namespace Modules\AppRssSchedules\Support;

use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Modules\AdminUser\Models\User;
use Modules\AppChannels\Models\SocialAccount;
use Modules\AppCredits\Models\CreditUsageLog;
use Modules\AppPublishing\Models\PublishingCampaign;
use Modules\AppPublishing\Models\PublishingLabel;
use Modules\AppPublishing\Models\PublishingPost;
use Modules\AppRssSchedules\Models\RssSchedule;
use Modules\AppRssSchedules\Models\RssScheduleHistory;
use Modules\AppRssSchedules\Services\RssScheduleService;
use Modules\AppTeams\Support\TeamWorkspaceAccess;

trait InteractsWithRssSchedules
{
    protected function accessibleAccounts()
    {
        return TeamWorkspaceAccess::accessibleAccountsQuery(auth()->user())
            ->where('is_active', true)
            ->orderBy('provider_key')
            ->orderBy('display_name')
            ->get();
    }

    protected function rssSchedulesEnabled(): bool
    {
        $user = auth()->user();
        $team = TeamWorkspaceAccess::activeTeam($user);
        $planOwner = $team?->owner ?: $user;

        return TeamWorkspaceAccess::teamHasModule($team, 'publishing')
            && TeamWorkspaceAccess::hasPermission($user, 'rss_schedules.view', $team)
            && (! $planOwner?->plan || $planOwner->hasPlanFeature('rss_schedules'));
    }

    protected function currentTeamId(): ?int
    {
        return TeamWorkspaceAccess::activeTeam(auth()->user())?->id;
    }

    protected function workspaceOwnerUserId(): int
    {
        return TeamWorkspaceAccess::workspaceOwnerUserId(auth()->user());
    }

    protected function currentTimezone(): string
    {
        return (string) (User::find($this->workspaceOwnerUserId())?->timezone ?: config('app.timezone', 'UTC'));
    }

    protected function canManage(RssSchedule $rssSchedule): bool
    {
        $currentTeamId = $this->currentTeamId();
        $scheduleTeamId = (int) ($rssSchedule->team_id ?? 0);

        return $this->rssSchedulesEnabled()
            && (int) $rssSchedule->user_id === $this->workspaceOwnerUserId()
            && (
                $currentTeamId === null
                || $scheduleTeamId === 0
                || $scheduleTeamId === (int) $currentTeamId
            );
    }

    protected function statusSummary(): array
    {
        $allSchedules = RssSchedule::query()
            ->where('user_id', $this->workspaceOwnerUserId())
            ->when($this->currentTeamId(), fn ($builder, $teamId) => $builder->where('team_id', $teamId))
            ->get(['id', 'status', 'settings', 'end_at']);

        return [
            'total' => $allSchedules->count(),
            'active' => $allSchedules->filter(fn ($s) => ! $s->isDemo() && $s->status && (! $s->end_at || $s->end_at >= now()->timestamp))->count(),
            'paused' => $allSchedules->filter(fn ($s) => ! $s->isDemo() && ! $s->status && (! $s->end_at || $s->end_at >= now()->timestamp))->count(),
            'samples' => $allSchedules->filter(fn ($s) => $s->isDemo())->count(),
            'expired' => $allSchedules->filter(fn ($s) => ! $s->isDemo() && $s->end_at && $s->end_at < now()->timestamp)->count(),
            'queued' => (int) RssScheduleHistory::query()
                ->whereIn('rss_schedule_histories.schedule_id', $allSchedules->pluck('id')->all())
                ->join('posts', 'posts.id', '=', 'rss_schedule_histories.publishing_post_id')
                ->whereIn('posts.status', [PublishingPost::STATUS_PROCESSING, PublishingPost::STATUS_SCHEDULED])
                ->count(),
        ];
    }

    protected function publishingCountsFor(array $scheduleIds)
    {
        return RssScheduleHistory::query()
            ->whereIn('rss_schedule_histories.schedule_id', $scheduleIds)
            ->join('posts', 'posts.id', '=', 'rss_schedule_histories.publishing_post_id')
            ->whereIn('posts.status', [PublishingPost::STATUS_DRAFT, PublishingPost::STATUS_PUBLISHED, PublishingPost::STATUS_FAILED])
            ->selectRaw('rss_schedule_histories.schedule_id, posts.status, COUNT(*) as total')
            ->groupBy('rss_schedule_histories.schedule_id', 'posts.status')
            ->get()
            ->groupBy('schedule_id')
            ->map(function ($rows) {
                return [
                    'drafts' => (int) optional($rows->firstWhere('status', PublishingPost::STATUS_DRAFT))->total,
                    'published' => (int) optional($rows->firstWhere('status', PublishingPost::STATUS_PUBLISHED))->total,
                    'failed' => (int) optional($rows->firstWhere('status', PublishingPost::STATUS_FAILED))->total,
                ];
            });
    }

    protected function queuedCountsFor(array $scheduleIds)
    {
        return RssScheduleHistory::query()
            ->whereIn('rss_schedule_histories.schedule_id', $scheduleIds)
            ->join('posts', 'posts.id', '=', 'rss_schedule_histories.publishing_post_id')
            ->whereIn('posts.status', [PublishingPost::STATUS_PROCESSING, PublishingPost::STATUS_SCHEDULED])
            ->selectRaw('rss_schedule_histories.schedule_id, COUNT(*) as total')
            ->groupBy('rss_schedule_histories.schedule_id')
            ->pluck('total', 'schedule_id');
    }

    protected function channelOptionsAndNetworks($accounts): array
    {
        $channelProviderRegistry = collect(channel_provider_cards())->keyBy('key');

        $channelOptions = collect($accounts)
            ->map(function (SocialAccount $account) use ($channelProviderRegistry) {
                $provider = $channelProviderRegistry->get((string) $account->provider_key, []);
                $providerLabel = (string) data_get($provider, 'label', str($account->provider_key)->headline());
                $capabilityLabel = (string) ($account->category ?: __('Channel'));
                $providerTone = publishing_provider_tone((string) $account->provider_key);

                return [
                    'key' => (string) $account->id,
                    'label' => (string) ($account->display_name ?: ($account->username ? '@'.$account->username : strtoupper((string) $account->provider_key))),
                    'subtitle' => trim($providerLabel.' '.str($capabilityLabel)->lower()),
                    'avatarUrl' => (string) ($account->avatar_url ?? ''),
                    'providerKey' => (string) $account->provider_key,
                    'providerLabel' => $providerLabel,
                    'providerIcon' => (string) data_get($provider, 'icon', ''),
                    'providerColor' => (string) data_get($provider, 'color', ''),
                    'providerToneSurface' => (string) ($providerTone['surface'] ?? ''),
                    'providerToneText' => (string) ($providerTone['text'] ?? ''),
                ];
            })
            ->values()
            ->all();

        $channelNetworks = $channelProviderRegistry
            ->only(collect($accounts)->pluck('provider_key')->filter()->unique()->values()->all())
            ->map(function ($provider) {
                $providerKey = (string) ($provider['key'] ?? '');
                $providerTone = publishing_provider_tone($providerKey);

                return [
                    'key' => $providerKey,
                    'label' => (string) ($provider['label'] ?? ''),
                    'icon' => (string) ($provider['icon'] ?? ''),
                    'color' => (string) ($provider['color'] ?? ''),
                    'toneSurface' => (string) ($providerTone['surface'] ?? ''),
                    'toneText' => (string) ($providerTone['text'] ?? ''),
                ];
            })
            ->filter(fn ($provider) => $provider['key'] !== '' && $provider['label'] !== '')
            ->values()
            ->all();

        return [$channelOptions, $channelNetworks];
    }

    protected function validatedPayload(array $validated, RssScheduleService $rss): array
    {
        $timePosts = collect($validated['time_posts'] ?? [])
            ->map(fn ($slot) => trim((string) $slot))
            ->filter()
            ->map(function (string $slot): ?string {
                if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $slot)) {
                    return $slot;
                }

                throw ValidationException::withMessages(['time_posts' => __('Use valid 24-hour times, such as 09:00 or 17:30.')]);
            })
            ->filter()
            ->unique()
            ->values();

        if ($timePosts->isEmpty()) {
            throw ValidationException::withMessages([
                'time_posts' => __('Enter at least one time in HH:MM format.'),
            ]);
        }

        $allowedAccountIds = $this->accessibleAccounts()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $selectedAccountIds = collect((array) ($validated['account_ids'] ?? []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => in_array($id, $allowedAccountIds, true))
            ->values();

        if ($selectedAccountIds->isEmpty()) {
            throw ValidationException::withMessages([
                'account_ids' => __('Select at least one active channel.'),
            ]);
        }

        $campaignId = filled($validated['campaign_id'] ?? null)
            ? PublishingCampaign::query()->ownedBy($this->workspaceOwnerUserId())->find((int) $validated['campaign_id'])?->id
            : null;

        $labelIds = PublishingLabel::query()
            ->ownedBy($this->workspaceOwnerUserId())
            ->whereIn('id', collect((array) ($validated['label_ids'] ?? []))->map(fn ($id) => (int) $id)->filter()->all())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $weekdays = collect(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'])
            ->mapWithKeys(fn ($day) => [$day => in_array($day, (array) ($validated['weekdays'] ?? []), true)])
            ->all();

        $timezone = $this->currentTimezone();
        $startInput = $validated['start_date'] ?? null;
        $endInput = $validated['end_date'] ?? null;

        $startAt = filled($startInput) ? Carbon::parse((string) $startInput, $timezone)->timestamp : null;
        $endAt = filled($endInput) ? $this->normalizeEndTimestamp((string) $endInput, $timezone) : null;

        if ($startAt && $endAt && $endAt < $startAt) {
            throw ValidationException::withMessages([
                'end_date' => __('The end date must be after or equal to the start date.'),
            ]);
        }

        $nextRun = $rss->calculateNextRun($timePosts->all(), $weekdays, $timezone, max(now()->timestamp, $startAt ?? 0));

        return [
            'name' => trim((string) ($validated['name'] ?? '')),
            'feed_url' => trim((string) ($validated['feed_url'] ?? '')),
            'description' => trim((string) ($validated['description'] ?? '')),
            'account_ids' => $selectedAccountIds->all(),
            'time_posts' => $timePosts->all(),
            'weekdays' => $weekdays,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'next_run_at' => $nextRun,
            'status' => (bool) ($validated['status'] ?? true),
            'settings' => [
                'delivery_mode' => (string) ($validated['delivery_mode'] ?? 'draft'),
                'accept_caption' => (bool) ($validated['accept_caption'] ?? false),
                'deny_link' => (bool) ($validated['deny_link'] ?? false),
                'url_shorten' => (bool) ($validated['url_shorten'] ?? false),
                'enable_ai_rewrite' => (bool) ($validated['enable_ai_rewrite'] ?? false),
                'ai_rewrite_tone' => trim((string) ($validated['ai_rewrite_tone'] ?? 'professional')),
                'ai_rewrite_language' => trim((string) ($validated['ai_rewrite_language'] ?? 'vi')),
                'ai_rewrite_prompt' => trim((string) ($validated['ai_rewrite_prompt'] ?? '')),
                'ai_rewrite_max_per_run' => max(0, (int) ($validated['ai_rewrite_max_per_run'] ?? 0)),
                'ai_rewrite_monthly_credit_limit' => max(0, (int) ($validated['ai_rewrite_monthly_credit_limit'] ?? 0)),
                'campaign_id' => $campaignId,
                'label_ids' => $labelIds,
                'referral_code' => trim((string) ($validated['referral_code'] ?? '')),
                'include_keywords' => trim((string) ($validated['include_keywords'] ?? '')),
                'ignore_keywords' => trim((string) ($validated['ignore_keywords'] ?? '')),
            ],
        ];
    }

    protected function normalizeEndTimestamp(string $value, string $timezone): int
    {
        $end = Carbon::parse($value, $timezone);

        if (! preg_match('/\d{1,2}:\d{2}/', $value)) {
            $end = $end->endOfDay();
        }

        return $end->timestamp;
    }

    protected function aiRewriteCreditPreview(): array
    {
        $owner = User::query()->find($this->workspaceOwnerUserId());
        $summary = function_exists('credit_summary')
            ? credit_summary($owner)
            : ['remaining' => null, 'unlimited' => true];

        $unitCost = function_exists('credit_service')
            ? max(0, (int) credit_service()->costFor($owner, RssScheduleService::AI_REWRITE_CREDIT_ACTION))
            : 0;

        return [
            'unit_cost' => $unitCost,
            'remaining' => $summary['remaining'] ?? null,
            'unlimited' => (bool) ($summary['unlimited'] ?? true),
            'enough' => (bool) ($summary['unlimited'] ?? true) || ($summary['remaining'] ?? null) === null || (int) ($summary['remaining'] ?? 0) >= $unitCost,
        ];
    }

    protected function aiRewriteUsageFor(array $scheduleIds): array
    {
        $scheduleIds = collect($scheduleIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($scheduleIds === []) {
            return [];
        }

        $startOfMonth = now()->startOfMonth();

        return CreditUsageLog::query()
            ->where('action_key', RssScheduleService::AI_REWRITE_CREDIT_ACTION)
            ->where('feature', 'rss-schedule.ai-rewrite')
            ->where('created_at', '>=', $startOfMonth)
            ->where(function ($query) use ($scheduleIds): void {
                foreach ($scheduleIds as $scheduleId) {
                    $query->orWhere('metadata->schedule_id', $scheduleId);
                }
            })
            ->get(['amount', 'quantity', 'metadata'])
            ->groupBy(fn ($log) => (int) data_get($log->metadata, 'schedule_id'))
            ->map(fn ($rows) => [
                'credits_used' => (int) $rows->sum('amount'),
                'rewrite_count' => (int) $rows->sum('quantity'),
            ])
            ->all();
    }
}
