<?php

namespace Modules\AppPublishing\Livewire;

use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\AdminUser\Models\Team;
use Modules\AppPublishing\Models\PublishingPost;
use Modules\AppTeams\Models\TeamPostReview;
use Modules\AppTeams\Support\TeamWorkspaceAccess;

#[Title('Publishing Drafts')]
class PublishingDrafts extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'provider')]
    public string $providerFilter = 'all';

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

    public function deleteDraft(string $postId): void
    {
        abort_unless($this->publishingEnabled(), 404);

        $post = $this->resolveManagedPost($postId);

        if (! $post || (int) $post->status !== PublishingPost::STATUS_DRAFT) {
            session()->flash('status', __('This draft is no longer available.'));

            return;
        }

        TeamPostReview::query()->where('post_id', $post->id)->delete();
        $post->delete();

        session()->flash('status', __('Draft deleted successfully.'));
    }

    public function render(): View
    {
        abort_unless($this->publishingEnabled(), 404);

        $channels = $this->publishableAccountsQuery()
            ->where('created_by_user_id', $this->workspaceOwnerUserId())
            ->where('is_active', true)
            ->orderBy('display_name')
            ->get();

        $providerRegistry = collect(channel_provider_cards())
            ->mapWithKeys(fn (array $provider): array => [(string) $provider['key'] => $provider]);

        $drafts = $this->draftQuery($channels)
            ->orderByDesc('changed')
            ->orderByDesc('id')
            ->paginate(12);

        $draftCards = $this->mapDraftCards(collect($drafts->items()), $channels, $providerRegistry);

        $providerFilters = $channels
            ->pluck('provider_key')
            ->filter()
            ->unique()
            ->map(fn (string $providerKey): array => [
                'key' => $providerKey,
                'label' => (string) data_get($providerRegistry, $providerKey.'.label', Str::headline($providerKey)),
            ])
            ->values();

        return view('apppublishing::livewire.drafts', [
            'drafts' => $drafts,
            'draftCards' => $draftCards,
            'providerFilters' => $providerFilters,
            'summary' => [
                'total' => $drafts->total(),
                'providers' => $draftCards->pluck('provider_key')->filter()->unique()->count(),
                'with_media' => $draftCards->filter(fn (array $draft) => $draft['media_count'] > 0)->count(),
                'pending_review' => $draftCards->where('needs_review', true)->count(),
            ],
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Publishing Drafts'),
        ]);
    }

    protected function draftQuery(Collection $channels)
    {
        return PublishingPost::query()
            ->where('function', 'post')
            ->where('status', PublishingPost::STATUS_DRAFT)
            ->when(
                $this->currentTeamId(),
                fn ($query, $teamId) => $query->where('team_id', $teamId),
                fn ($query) => $query->ownedBy((int) auth()->id())
            )
            ->whereIn('account_id', $channels->pluck('id')->all())
            ->when($this->providerFilter !== 'all', fn ($query) => $query->where('social_network', $this->providerFilter))
            ->when($this->search !== '', function ($query) {
                $needle = trim($this->search);

                $query->where(function ($nested) use ($needle): void {
                    $nested
                        ->where('id_secure', 'like', '%'.$needle.'%')
                        ->orWhere('data', 'like', '%'.$needle.'%');
                });
            });
    }

    protected function mapDraftCards(Collection $drafts, Collection $channels, Collection $providerRegistry): Collection
    {
        $reviews = TeamPostReview::query()
            ->with(['submitter', 'decider'])
            ->whereIn('post_id', $drafts->pluck('id')->all())
            ->get()
            ->keyBy('post_id');

        return $drafts
            ->map(function (PublishingPost $post) use ($channels, $providerRegistry, $reviews): ?array {
                $channel = $channels->firstWhere('id', (int) $post->account_id);

                if (! $channel) {
                    return null;
                }

                $providerKey = (string) ($channel->provider_key ?: $post->social_network);
                $provider = $providerRegistry->get($providerKey, []);
                $postData = is_array($post->data) ? $post->data : [];
                $mediaItems = collect((array) ($postData['medias'] ?? []))->values();
                $scheduledAt = $post->time_post
                    ? Carbon::createFromTimestamp((int) $post->time_post, $this->currentTimezone())
                    : null;

                $review = $reviews->get($post->id);

                return [
                    'id' => (string) ($post->id_secure ?: $post->id),
                    'title' => (string) ($postData['title'] ?? Str::limit((string) ($postData['caption'] ?? ''), 56, '...')),
                    'caption' => (string) ($postData['caption'] ?? ''),
                    'channel' => (string) $channel->display_name,
                    'handle' => $channel->username ? '@'.$channel->username : null,
                    'provider_key' => $providerKey,
                    'provider_label' => (string) data_get($provider, 'label', Str::headline($providerKey)),
                    'provider_icon' => (string) data_get($provider, 'icon', 'fa-light fa-share-nodes'),
                    'provider_color' => (string) data_get($provider, 'color', '#2563eb'),
                    'media_type' => Str::upper((string) ($postData['media_type'] ?? 'TEXT')),
                    'media_count' => $mediaItems->count(),
                    'media_preview' => (string) data_get($mediaItems->first(), 'previewUrl', data_get($mediaItems->first(), 'url', '')),
                    'updated_at' => $post->changed ? Carbon::createFromTimestamp((int) $post->changed, $this->currentTimezone()) : null,
                    'scheduled_at' => $scheduledAt,
                    'needs_review' => (string) ($review?->status ?? '') === 'pending',
                    'review_status' => (string) ($review?->status ?? ''),
                    'review_badge' => match ((string) ($review?->status ?? '')) {
                        'pending' => (string) __('Waiting approval'),
                        'rejected' => (string) __('Rejected'),
                        'approved' => (string) __('Approved'),
                        default => '',
                    },
                    'review_note' => trim((string) ($review?->decision_note ?? '')),
                    'review_submitted_at' => $review?->submitted_at,
                    'review_submitted_by' => (string) ($review?->submitter?->fullname ?? $review?->submitter?->name ?? ''),
                    'review_decided_at' => $review?->decided_at,
                    'review_decided_by' => (string) ($review?->decider?->fullname ?? $review?->decider?->name ?? ''),
                ];
            })
            ->filter()
            ->values();
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

    protected function currentTeamId(): ?int
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        if (method_exists(TeamWorkspaceAccess::class, 'activeTeam')) {
            $team = TeamWorkspaceAccess::activeTeam($user);

            if ($team) {
                return $team->id;
            }
        }

        return Team::query()->where('owner_user_id', $user->id)->value('id')
            ?: $user->teams()->value('teams.id');
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

    protected function resolveManagedPost(string $postId): ?PublishingPost
    {
        $query = PublishingPost::query()->where('function', 'post');

        $query->when(
            $this->currentTeamId(),
            fn ($builder, $teamId) => $builder->where('team_id', $teamId),
            fn ($builder) => $builder->ownedBy((int) auth()->id())
        );

        return $query
            ->where(function ($builder) use ($postId) {
                $builder->where('id_secure', $postId);

                if (is_numeric($postId)) {
                    $builder->orWhere('id', (int) $postId);
                }
            })
            ->first();
    }
}
