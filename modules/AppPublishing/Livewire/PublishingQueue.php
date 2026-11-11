<?php

namespace Modules\AppPublishing\Livewire;

use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\AdminUser\Models\Team;
use Modules\AppPublishing\Models\PublishingPost;
use Modules\AppTeams\Models\TeamPostReview;
use Modules\AppTeams\Support\TeamWorkspaceAccess;

#[Title('Publishing Queue')]
class PublishingQueue extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'provider')]
    public string $providerFilter = 'all';

    #[Url(as: 'status')]
    public string $statusFilter = 'all';

    public array $selectedQueueIds = [];

    public function updatingSearch(): void
    {
        $this->clearSelectedQueueItems();
        $this->resetPage();
    }

    public function updatingProviderFilter(): void
    {
        $this->clearSelectedQueueItems();
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->clearSelectedQueueItems();
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->providerFilter = 'all';
        $this->statusFilter = 'all';
        $this->clearSelectedQueueItems();
        $this->resetPage();
    }

    public function selectVisibleQueueItems(array $postIds): void
    {
        $this->selectedQueueIds = collect($postIds)
            ->map(fn ($id): string => trim((string) $id))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function clearSelectedQueueItems(): void
    {
        $this->selectedQueueIds = [];
    }

    public function deleteQueueItem(string $postId): void
    {
        abort_unless($this->publishingEnabled(), 404);

        $post = $this->resolveManagedPost($postId);

        if (! $post || ! in_array((int) $post->status, [
            PublishingPost::STATUS_SCHEDULED,
            PublishingPost::STATUS_PROCESSING,
            PublishingPost::STATUS_FAILED,
        ], true)) {
            $this->dispatch('app-toast', type: 'error', title: __('Post not found'), message: __('This queue item is no longer available.'));
            return;
        }

        TeamPostReview::query()->where('post_id', $post->id)->delete();
        $post->delete();
        $this->invalidatePublishingCalendarCache();
        $this->selectedQueueIds = collect($this->selectedQueueIds)
            ->reject(fn ($id): bool => (string) $id === $postId)
            ->values()
            ->all();

        $this->dispatch('app-toast', type: 'success', title: __('Queue item deleted'), message: __('The publishing item has been removed from the queue.'));
        $this->dispatch('queue-selection-cleared');
        $this->resetPage();
    }

    public function deleteSelectedQueueItems(?array $selectedIds = null): void
    {
        abort_unless($this->publishingEnabled(), 404);

        $selectedIds = collect($selectedIds ?? $this->selectedQueueIds)
            ->map(fn ($id): string => trim((string) $id))
            ->filter()
            ->unique()
            ->values();

        if ($selectedIds->isEmpty()) {
            $this->dispatch('app-toast', type: 'warning', title: __('No items selected'), message: __('Choose one or more queue items to delete.'));
            return;
        }

        $channels = $this->publishableAccountsQuery()
            ->where('created_by_user_id', $this->workspaceOwnerUserId())
            ->where('is_active', true)
            ->get();

        $posts = $this->manageableQueuePostsQuery($channels)
            ->where(function ($query) use ($selectedIds): void {
                foreach ($selectedIds as $selectedId) {
                    $query->orWhere('id_secure', $selectedId);

                    if (is_numeric($selectedId)) {
                        $query->orWhere('id', (int) $selectedId);
                    }
                }
            })
            ->get();

        if ($posts->isEmpty()) {
            $this->clearSelectedQueueItems();
            $this->dispatch('app-toast', type: 'warning', title: __('Nothing to delete'), message: __('The selected queue items are no longer available.'));
            return;
        }

        $postIds = $posts->pluck('id')->map(fn ($id): int => (int) $id)->all();

        TeamPostReview::query()->whereIn('post_id', $postIds)->delete();
        PublishingPost::query()->whereIn('id', $postIds)->delete();

        $deleted = count($postIds);

        $this->invalidatePublishingCalendarCache();
        $this->clearSelectedQueueItems();
        $this->resetPage();

        $this->dispatch(
            'app-toast',
            type: 'success',
            title: __('Queue items deleted'),
            message: trans_choice(':count queue item removed.|:count queue items removed.', $deleted, ['count' => $deleted]),
        );
        $this->dispatch('queue-selection-cleared');
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

        $items = $this->queueQuery($channels)
            ->orderBy('time_post')
            ->orderByDesc('changed')
            ->paginate(12);

        $queueCards = $this->mapQueueCards(collect($items->items()), $channels, $providerRegistry);

        $providerFilters = $channels
            ->pluck('provider_key')
            ->filter()
            ->unique()
            ->map(fn (string $providerKey): array => [
                'key' => $providerKey,
                'label' => (string) data_get($providerRegistry, $providerKey.'.label', Str::headline($providerKey)),
            ])
            ->values();

        $allMatching = $this->queueQuery($channels)->get();

        return view('apppublishing::livewire.queue', [
            'items' => $items,
            'queueCards' => $queueCards,
            'providerFilters' => $providerFilters,
            'summary' => [
                'total' => $allMatching->count(),
                'scheduled' => $allMatching->where('status', PublishingPost::STATUS_SCHEDULED)->count(),
                'processing' => $allMatching->where('status', PublishingPost::STATUS_PROCESSING)->count(),
                'failed' => $allMatching->where('status', PublishingPost::STATUS_FAILED)->count(),
            ],
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Publishing Queue'),
        ]);
    }

    protected function queueQuery(Collection $channels)
    {
        return $this->manageableQueuePostsQuery($channels)
            ->when($this->providerFilter !== 'all', fn ($query) => $query->where('social_network', $this->providerFilter))
            ->when($this->statusFilter !== 'all', function ($query): void {
                $status = match ($this->statusFilter) {
                    'scheduled' => PublishingPost::STATUS_SCHEDULED,
                    'processing' => PublishingPost::STATUS_PROCESSING,
                    'failed' => PublishingPost::STATUS_FAILED,
                    default => null,
                };

                if ($status !== null) {
                    $query->where('status', $status);
                }
            })
            ->when($this->search !== '', function ($query): void {
                $needle = trim($this->search);

                $query->where(function ($nested) use ($needle): void {
                    $nested
                        ->where('id_secure', 'like', '%'.$needle.'%')
                        ->orWhere('data', 'like', '%'.$needle.'%');
                });
            });
    }

    protected function manageableQueuePostsQuery(Collection $channels)
    {
        return PublishingPost::query()
            ->where('function', 'post')
            ->whereIn('status', [
                PublishingPost::STATUS_SCHEDULED,
                PublishingPost::STATUS_PROCESSING,
                PublishingPost::STATUS_FAILED,
            ])
            ->when(
                $this->currentTeamId(),
                fn ($query, $teamId) => $query->where('team_id', $teamId),
                fn ($query) => $query->ownedBy((int) auth()->id())
            )
            ->whereIn('account_id', $channels->pluck('id')->all());
    }

    protected function mapQueueCards(Collection $posts, Collection $channels, Collection $providerRegistry): Collection
    {
        $pendingReviews = TeamPostReview::query()
            ->whereIn('post_id', $posts->pluck('id')->all())
            ->where('status', 'pending')
            ->pluck('post_id')
            ->flip();

        return $posts
            ->map(function (PublishingPost $post) use ($channels, $providerRegistry, $pendingReviews): ?array {
                $channel = $channels->firstWhere('id', (int) $post->account_id);

                if (! $channel) {
                    return null;
                }

                $providerKey = (string) ($channel->provider_key ?: $post->social_network);
                $provider = $providerRegistry->get($providerKey, []);
                $postData = is_array($post->data) ? $post->data : [];
                $publishResult = $this->normalizePublishResult($post->result);
                $status = $this->queueStatusMeta((int) $post->status, $publishResult, $pendingReviews->has($post->id));
                $mediaItems = collect((array) ($postData['medias'] ?? []))->values();
                $primaryMedia = is_array($mediaItems->first()) ? $mediaItems->first() : [];
                $resolvedMediaType = $this->resolveQueueCardMediaType($providerKey, $postData, $mediaItems);

                return [
                    'id' => (string) ($post->id_secure ?: $post->id),
                    'title' => (string) ($postData['title'] ?? Str::limit((string) ($postData['caption'] ?? ''), 60, '...')),
                    'caption' => (string) ($postData['caption'] ?? ''),
                    'channel' => (string) $channel->display_name,
                    'handle' => $channel->username ? '@'.$channel->username : null,
                    'provider_key' => $providerKey,
                    'provider_label' => (string) data_get($provider, 'label', Str::headline($providerKey)),
                    'provider_icon' => (string) data_get($provider, 'icon', 'fa-light fa-share-nodes'),
                    'media_preview' => (string) ($primaryMedia['previewUrl'] ?? $primaryMedia['preview_url'] ?? $primaryMedia['url'] ?? ''),
                    'media_mime' => (string) ($primaryMedia['mimeType'] ?? $primaryMedia['mime_type'] ?? ''),
                    'media_type' => $resolvedMediaType,
                    'media_count' => $mediaItems->count(),
                    'scheduled_at' => $post->time_post ? Carbon::createFromTimestamp((int) $post->time_post, $this->currentTimezone()) : null,
                    'updated_at' => $post->changed ? Carbon::createFromTimestamp((int) $post->changed, $this->currentTimezone()) : null,
                    'status_key' => $status['key'],
                    'status_label' => $status['label'],
                    'status_style' => match ($status['key']) {
                        'processing' => 'background-color: rgba(99, 102, 241, 0.12); color: #4f46e5;',
                        'failed' => 'background-color: rgba(239, 68, 68, 0.12); color: #dc2626;',
                        default => 'background-color: rgba(59, 130, 246, 0.12); color: #2563eb;',
                    },
                    'error' => (string) ($publishResult['error'] ?? ''),
                    'can_edit' => in_array((int) $post->status, [
                        PublishingPost::STATUS_DRAFT,
                        PublishingPost::STATUS_SCHEDULED,
                        PublishingPost::STATUS_FAILED,
                    ], true),
                ];
            })
            ->filter()
            ->values();
    }

    protected function queueStatusMeta(int $status, array $publishResult, bool $waitingApprove = false): array
    {
        $key = $waitingApprove
            ? 'waiting_approve'
            : match ($status) {
                PublishingPost::STATUS_PROCESSING => 'processing',
                PublishingPost::STATUS_FAILED => 'failed',
                PublishingPost::STATUS_SCHEDULED => $publishResult['state'] === 'failed' ? 'failed' : 'scheduled',
                default => 'scheduled',
            };

        return [
            'key' => $key,
            'label' => (string) match ($key) {
                'processing' => __('Processing'),
                'failed' => __('Failed'),
                'waiting_approve' => __('Waiting approve'),
                default => __('Queued'),
            },
        ];
    }

    protected function normalizePublishResult(mixed $result): array
    {
        $payload = [];

        if (is_array($result)) {
            $payload = $result;
        } elseif (is_string($result) && trim($result) !== '') {
            $decoded = json_decode($result, true);
            $payload = is_array($decoded) ? $decoded : ['raw' => trim($result)];
        }

        $url = trim((string) ($payload['url'] ?? data_get($payload, 'data.url', '')));
        $error = trim((string) ($payload['error'] ?? data_get($payload, 'data.error', '')));
        $state = strtolower(trim((string) ($payload['state'] ?? data_get($payload, 'data.state', ''))));

        return [
            'url' => $url,
            'error' => $error,
            'state' => $state === 'published'
                ? 'published'
                : ($state === 'failed' ? 'failed' : ($error ? 'failed' : 'pending')),
        ];
    }

    protected function resolveQueueCardMediaType(string $providerKey, array $postData, Collection $mediaItems): string
    {
        $primaryMedia = is_array($mediaItems->first()) ? $mediaItems->first() : [];
        $primaryMime = strtolower((string) ($primaryMedia['mimeType'] ?? $primaryMedia['mime_type'] ?? ''));
        $primaryCategory = strtolower((string) ($primaryMedia['category'] ?? $primaryMedia['type'] ?? ''));
        $storedMediaType = strtolower(trim((string) ($postData['media_type'] ?? '')));
        $providerOptions = is_array($postData['options'] ?? null) ? $postData['options'] : [];
        $linkedinType = strtolower(trim((string) ($providerOptions['linkedin_post_type'] ?? '')));

        return match (true) {
            str_starts_with($primaryMime, 'video/'),
            in_array($primaryCategory, ['video', 'reel'], true),
            in_array($storedMediaType, ['video', 'reel'], true),
            str_starts_with($providerKey, 'linkedin_') && $linkedinType === 'video' => 'VIDEO',
            str_starts_with($primaryMime, 'image/'),
            $primaryCategory === 'image',
            in_array($storedMediaType, ['image', 'carousel'], true),
            str_starts_with($providerKey, 'linkedin_') && in_array($linkedinType, ['image', 'media'], true) => 'IMAGE',
            $storedMediaType !== '' => Str::upper($storedMediaType),
            in_array($linkedinType, ['text', 'link'], true) => Str::upper($linkedinType),
            default => 'TEXT',
        };
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
