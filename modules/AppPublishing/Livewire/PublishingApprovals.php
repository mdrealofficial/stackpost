<?php

namespace Modules\AppPublishing\Livewire;

use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\AdminUser\Models\Team;
use Modules\AppChannels\Models\SocialAccount;
use Modules\AppPublishing\Jobs\PublishScheduledPostJob;
use Modules\AppPublishing\Models\PublishingPost;
use Modules\AppTeams\Models\TeamPostReview;
use Modules\AppTeams\Support\TeamWorkspaceAccess;

#[Title('Publishing Approvals')]
class PublishingApprovals extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'provider')]
    public string $providerFilter = 'all';

    public array $reviewNotes = [];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingProviderFilter(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->providerFilter = 'all';
        $this->resetPage();
    }

    public function approveReview(int $reviewId): void
    {
        abort_unless($this->publishingEnabled(), 404);

        $team = $this->currentTeam();
        abort_unless($this->canCurrentUserApprovePosts($team), 403);

        $reviewForAccess = TeamPostReview::query()
            ->with('post')
            ->where('team_id', $team?->id)
            ->where('status', 'pending')
            ->findOrFail($reviewId);
        $this->authorizeReviewDecision($reviewForAccess, $team);
        $this->authorizeReviewChannelAccess($reviewForAccess, $team);

        $publishImmediatelyPostId = DB::transaction(function () use ($reviewId, $team): ?int {
            $review = TeamPostReview::query()
                ->with('post')
                ->where('team_id', $team?->id)
            ->where('status', 'pending')
            ->lockForUpdate()
            ->findOrFail($reviewId);
            $this->authorizeReviewDecision($review, $team);
            $this->authorizeReviewChannelAccess($review, $team);

            $requestedStatus = (int) ($review->metadata['requested_status'] ?? PublishingPost::STATUS_SCHEDULED);
            $review->post?->update([
                'status' => $requestedStatus,
                'changed' => now()->timestamp,
            ]);
            $review->update([
                'status' => 'approved',
                'decided_by_user_id' => (int) auth()->id(),
                'decision_note' => trim((string) ($this->reviewNotes[$reviewId] ?? '')) ?: null,
                'decided_at' => now(),
            ]);

            return $review->post
                && $requestedStatus === PublishingPost::STATUS_SCHEDULED
                && (string) ($review->metadata['schedule_mode'] ?? '') === 'immediately'
                    ? (int) $review->post->id
                    : null;
        });

        if ($publishImmediatelyPostId) {
            PublishScheduledPostJob::dispatchSync($publishImmediatelyPostId);
        }

        unset($this->reviewNotes[$reviewId]);
        $this->invalidatePublishingCalendarCache();
        $this->dispatch('app-toast', type: 'success', title: __('Post approved'), message: __('The publishing item has been approved and returned to the queue.'));
        $this->resetPage();
    }

    public function rejectReview(int $reviewId): void
    {
        abort_unless($this->publishingEnabled(), 404);

        $team = $this->currentTeam();
        abort_unless($this->canCurrentUserApprovePosts($team), 403);

        $reviewForAccess = TeamPostReview::query()
            ->with('post')
            ->where('team_id', $team?->id)
            ->where('status', 'pending')
            ->findOrFail($reviewId);
        $this->authorizeReviewDecision($reviewForAccess, $team);
        $this->authorizeReviewChannelAccess($reviewForAccess, $team);

        DB::transaction(function () use ($reviewId, $team): void {
            $review = TeamPostReview::query()
                ->with('post')
                ->where('team_id', $team?->id)
            ->where('status', 'pending')
            ->lockForUpdate()
            ->findOrFail($reviewId);
            $this->authorizeReviewDecision($review, $team);
            $this->authorizeReviewChannelAccess($review, $team);

            $review->post?->update([
                'status' => PublishingPost::STATUS_DRAFT,
                'changed' => now()->timestamp,
            ]);
            $review->update([
                'status' => 'rejected',
                'decided_by_user_id' => (int) auth()->id(),
                'decision_note' => trim((string) ($this->reviewNotes[$reviewId] ?? '')) ?: null,
                'decided_at' => now(),
            ]);
        });

        unset($this->reviewNotes[$reviewId]);
        $this->invalidatePublishingCalendarCache();
        $this->dispatch('app-toast', type: 'success', title: __('Post rejected'), message: __('The publishing item was returned to drafts with your review note.'));
        $this->resetPage();
    }

    public function render(): View
    {
        abort_unless($this->publishingEnabled(), 404);

        $team = $this->currentTeam();
        abort_unless($this->canCurrentUserApprovePosts($team), 403);

        $channels = $this->publishableAccountsQuery()
            ->where('created_by_user_id', $this->workspaceOwnerUserId())
            ->where('is_active', true)
            ->orderBy('display_name')
            ->get(['id', 'provider_key', 'display_name', 'username']);

        $providerRegistry = collect(channel_provider_cards())
            ->mapWithKeys(fn (array $provider): array => [(string) $provider['key'] => $provider]);

        $reviews = $this->reviewQuery($channels)
            ->paginate(10);

        $reviewCards = $this->mapReviewCards(collect($reviews->items()), $channels, $providerRegistry);

        $providerFilters = $channels
            ->pluck('provider_key')
            ->filter()
            ->unique()
            ->map(fn (string $providerKey): array => [
                'key' => $providerKey,
                'label' => (string) data_get($providerRegistry, $providerKey.'.label', Str::headline($providerKey)),
            ])
            ->values();

        $allPendingReviews = $this->reviewQuery($channels)->get();

        return view('apppublishing::livewire.approvals', [
            'reviews' => $reviews,
            'reviewCards' => $reviewCards,
            'providerFilters' => $providerFilters,
            'summary' => [
                'pending' => $allPendingReviews->count(),
                'providers' => $allPendingReviews->pluck('post.social_network')->filter()->unique()->count(),
                'submitted_today' => $allPendingReviews->filter(fn (TeamPostReview $review) => optional($review->submitted_at)?->isToday())->count(),
                'oldest_pending' => optional($allPendingReviews->sortBy('submitted_at')->first()?->submitted_at)?->diffForHumans(),
            ],
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Publishing Approvals'),
        ]);
    }

    protected function reviewQuery(Collection $channels)
    {
        return TeamPostReview::query()
            ->with(['post', 'submitter', 'decider'])
            ->where('team_id', (int) ($this->currentTeamId() ?? 0))
            ->where('status', 'pending')
            ->whereHas('post', function ($query) use ($channels): void {
                $query->where('function', 'post')
                    ->whereIn('account_id', $channels->pluck('id')->all())
                    ->when($this->providerFilter !== 'all', fn ($builder) => $builder->where('social_network', $this->providerFilter))
                    ->when($this->search !== '', function ($builder): void {
                        $needle = trim($this->search);

                        $builder->where(function ($nested) use ($needle): void {
                            $nested
                                ->where('id_secure', 'like', '%'.$needle.'%')
                                ->orWhere('data', 'like', '%'.$needle.'%');
                        });
                    });
            })
            ->orderByDesc('submitted_at')
            ->orderByDesc('id');
    }

    protected function mapReviewCards(Collection $reviews, Collection $channels, Collection $providerRegistry): Collection
    {
        return $reviews
            ->map(function (TeamPostReview $review) use ($channels, $providerRegistry): ?array {
                $post = $review->post;

                if (! $post) {
                    return null;
                }

                $channel = $channels->firstWhere('id', (int) $post->account_id);

                if (! $channel) {
                    return null;
                }

                $providerKey = (string) ($channel->provider_key ?: $post->social_network);
                $provider = $providerRegistry->get($providerKey, []);
                $postData = is_array($post->data) ? $post->data : [];
                $postOptions = is_array($postData['options'] ?? null) ? $postData['options'] : [];
                $mediaItems = collect((array) ($postData['medias'] ?? []))->values();
                $firstMedia = $mediaItems->first();
                $scheduledAt = $post->time_post
                    ? Carbon::createFromTimestamp((int) $post->time_post, $this->currentTimezone())
                    : null;

                return [
                    'review_id' => (int) $review->id,
                    'post_id' => (string) ($post->id_secure ?: $post->id),
                    'title' => (string) ($postData['title'] ?? Str::limit((string) ($postData['caption'] ?? ''), 64, '...')),
                    'caption' => (string) ($postData['caption'] ?? ''),
                    'channel' => (string) $channel->display_name,
                    'handle' => $channel->username ? '@'.$channel->username : null,
                    'provider_key' => $providerKey,
                    'provider_label' => (string) data_get($provider, 'label', Str::headline($providerKey)),
                    'provider_icon' => (string) data_get($provider, 'icon', 'fa-light fa-share-nodes'),
                    'provider_color' => (string) data_get($provider, 'color', '#2563eb'),
                    'media_preview' => (string) data_get($firstMedia, 'previewUrl', data_get($firstMedia, 'url', '')),
                    'media_mime' => (string) data_get($firstMedia, 'mimeType', ''),
                    'internal_note' => trim((string) ($postOptions['notes'] ?? '')),
                    'submitted_by' => (string) ($review->submitter?->fullname ?? $review->submitter?->name ?? __('Unknown')),
                    'submitted_at' => $review->submitted_at,
                    'scheduled_at' => $scheduledAt,
                    'schedule_mode' => (string) ($review->metadata['schedule_mode'] ?? 'specific_days_times'),
                    'can_decide' => $this->canCurrentUserDecideReview($review),
                ];
            })
            ->filter()
            ->values();
    }

    protected function currentTeam(): ?Team
    {
        return TeamWorkspaceAccess::activeTeam(auth()->user());
    }

    protected function currentTeamId(): ?int
    {
        return $this->currentTeam()?->id;
    }

    protected function canCurrentUserApprovePosts(?Team $team): bool
    {
        $user = auth()->user();

        if (! $team || ! $user) {
            return false;
        }

        if (! TeamWorkspaceAccess::teamHasModule($team, 'publishing')) {
            return false;
        }

        return TeamWorkspaceAccess::hasPermission($user, 'post.approve', $team);
    }

    protected function authorizeReviewDecision(TeamPostReview $review, ?Team $team): void
    {
        abort_unless($this->canCurrentUserApprovePosts($team), 403);
        abort_unless($this->canCurrentUserDecideReview($review), 403, __('Workflow publishing reviews also require workflow approval permission.'));
    }

    protected function authorizeReviewChannelAccess(TeamPostReview $review, ?Team $team): void
    {
        abort_unless($team && $review->post, 404);

        abort_unless(
            in_array(
                (int) $review->post->account_id,
                TeamWorkspaceAccess::accessibleAccountIds(auth()->user(), $team),
                true
            ),
            403
        );
    }

    protected function canCurrentUserDecideReview(TeamPostReview $review): bool
    {
        if ((string) data_get($review->metadata, 'source') !== 'automation_workflow') {
            return true;
        }

        return TeamWorkspaceAccess::hasPermission(auth()->user(), 'workflow.approve', $this->currentTeam());
    }

    protected function invalidatePublishingCalendarCache(): void
    {
        $ownerId = $this->workspaceOwnerUserId();
        $teamId = $this->currentTeamId() ?? 0;
        $cacheKey = 'publishing:calendar-version:v1:'.$ownerId.':'.$teamId;

        Cache::forever($cacheKey, (int) Cache::get($cacheKey, 1) + 1);
    }

    protected function publishableAccountsQuery()
    {
        $publishableCapabilityKeys = publishable_channel_capability_keys(auth()->user());

        return TeamWorkspaceAccess::accessibleAccountsQuery(auth()->user())
            ->where(function ($query) use ($publishableCapabilityKeys): void {
                $query->whereIn('capability_key', $publishableCapabilityKeys)
                    ->orWhere(function ($fallbackQuery) use ($publishableCapabilityKeys): void {
                        $fallbackQuery->whereNull('capability_key')
                            ->whereIn('provider_key', $publishableCapabilityKeys);
                    });
            });
    }

    protected function currentTimezone(): string
    {
        $user = auth()->user();

        return (string) ($user?->timezone ?: config('app.timezone', 'UTC'));
    }

    protected function workspaceOwnerUserId(): int
    {
        return TeamWorkspaceAccess::workspaceOwnerUserId(auth()->user());
    }

    protected function publishingEnabled(): bool
    {
        return TeamWorkspaceAccess::teamHasModule(TeamWorkspaceAccess::activeTeam(auth()->user()), 'publishing');
    }
}
