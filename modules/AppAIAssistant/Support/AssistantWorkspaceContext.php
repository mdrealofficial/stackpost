<?php

namespace Modules\AppAIAssistant\Support;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\AdminUser\Models\User;
use Modules\AppAIAssistant\Models\AssistantIdea;
use Modules\AppAIStudio\Models\AIPromptHistory;
use Modules\AppChannels\Models\SocialAccount;
use Modules\AppFiles\Models\AppFile;
use Modules\AppPublishing\Models\PublishingCampaign;
use Modules\AppPublishing\Models\PublishingPost;
use Modules\AppTeams\Support\TeamWorkspaceAccess;

class AssistantWorkspaceContext
{
    public function snapshot(User $user): array
    {
        $team = TeamWorkspaceAccess::activeTeam($user);
        $ownerId = TeamWorkspaceAccess::workspaceOwnerUserId($user);
        $planOwner = $team?->owner ?: $user;
        $accounts = TeamWorkspaceAccess::accessibleAccountsQuery($user)
            ->where('is_active', true)
            ->get(['id', 'provider_key', 'display_name', 'username', 'last_synced_at']);
        $posts = PublishingPost::query()
            ->where('function', 'post')
            ->when(
                $team?->id,
                fn ($query, int $teamId) => $query->where('team_id', $teamId),
                fn ($query) => $query->where('user_id', $ownerId)
            );
        $failures = (clone $posts)
            ->with('account:id,provider_key,display_name,username')
            ->where('status', PublishingPost::STATUS_FAILED)
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn (PublishingPost $post) => $this->friendlyPublishingFailure($post))
            ->values()
            ->all();
        $analytics = [
            'available' => false,
            'latest_date' => null,
            'metric_points_30d' => 0,
        ];

        if (Schema::hasTable('social_analytics') && $accounts->isNotEmpty()) {
            $analyticsQuery = \Modules\AppAnalytics\Models\SocialAnalytics::query()
                ->whereIn('account_id', $accounts->pluck('id')->all())
                ->where('date', '>=', now()->subDays(30)->toDateString());
            $analytics = [
                'available' => (clone $analyticsQuery)->exists(),
                'latest_date' => (clone $analyticsQuery)->max('date'),
                'metric_points_30d' => (clone $analyticsQuery)->count(),
            ];
        }

        $creditSummary = credit_service()->summary($planOwner);

        return [
            'plan' => [
                'name' => (string) ($planOwner?->plan?->name ?: __('No active plan')),
                'active' => (bool) ($planOwner?->hasActivePlan() ?? false),
                'expires_at' => optional($planOwner?->plan_expires_at)->toDateString(),
            ],
            'credits' => [
                'remaining' => $creditSummary['remaining'] ?? null,
                'reserved' => (int) ($creditSummary['reserved'] ?? 0),
                'unlimited' => (bool) ($creditSummary['unlimited'] ?? false),
                'caption_cost' => credit_service()->costFor($planOwner, 'ai_studio_generate_captions', 1),
                'image_cost' => credit_service()->costFor($planOwner, 'ai_studio_generate_image', 3),
                'video_cost' => credit_service()->costFor($planOwner, 'ai_studio_generate_video', 8),
                'assistant_turn_cost' => credit_service()->costFor($planOwner, 'ai_assistant_turn', 1),
            ],
            'channels' => [
                'connected' => $accounts->count(),
                'providers' => $accounts->groupBy('provider_key')->map->count()->sortDesc()->all(),
                'last_synced_at' => optional($accounts->max('last_synced_at'))?->toDateTimeString(),
            ],
            'publishing' => [
                'drafts' => (clone $posts)->where('status', PublishingPost::STATUS_DRAFT)->count(),
                'queue' => (clone $posts)->where('status', PublishingPost::STATUS_SCHEDULED)->count(),
                'failed' => (clone $posts)->where('status', PublishingPost::STATUS_FAILED)->count(),
                'published_this_month' => (clone $posts)
                    ->where('status', PublishingPost::STATUS_PUBLISHED)
                    ->whereBetween('created', [now()->startOfMonth()->timestamp, now()->endOfMonth()->timestamp])
                    ->count(),
                'recent_failures' => $failures,
            ],
            'analytics' => $analytics,
            'workspace' => [
                'team_id' => $team?->id,
                'team_name' => $team?->name,
                'is_team_workspace' => (bool) $team,
            ],
        ];
    }

    public function pageGuide(?string $routeName): array
    {
        $routeName = trim((string) $routeName);

        return match (true) {
            str_starts_with($routeName, 'portal.publishing.calendar') => [
                'title' => __('Publishing calendar'),
                'description' => __('Plan dates, open a post to edit it, or move scheduled work while keeping account requirements visible.'),
                'tips' => [__('Use Draft when media or provider options are not final.'), __('Schedule only after every selected channel passes validation.')],
            ],
            str_starts_with($routeName, 'portal.publishing.drafts') => [
                'title' => __('Publishing drafts'),
                'description' => __('Review incomplete work before it reaches the queue.'),
                'tips' => [__('Open failed or media-required drafts in the full composer.'), __('Approve AI copy before assigning live accounts.')],
            ],
            str_starts_with($routeName, 'portal.publishing.queue') => [
                'title' => __('Publishing queue'),
                'description' => __('Monitor scheduled and processing posts before provider delivery.'),
                'tips' => [__('Check timezone and account state when a due post does not dispatch.')],
            ],
            str_starts_with($routeName, 'portal.channels') => [
                'title' => __('Connected channels'),
                'description' => __('Manage the social accounts available to this workspace.'),
                'tips' => [__('OAuth connections always stay under your direct control.'), __('Inactive accounts are excluded from assistant publishing actions.')],
            ],
            str_starts_with($routeName, 'portal.analytics') => [
                'title' => __('Analytics'),
                'description' => __('Compare supported performance data from connected accounts.'),
                'tips' => [__('Analytics coverage depends on provider permissions and successful sync history.')],
            ],
            str_starts_with($routeName, 'portal.ai-studio') => [
                'title' => __('AI Studio'),
                'description' => __('Create and refine content using the workspace brand settings.'),
                'tips' => [__('Tool calls use that tool credit only; there is no extra chat charge.'), __('Provider failures do not consume reserved credit.')],
            ],
            str_starts_with($routeName, 'portal.files') => [
                'title' => __('Files'),
                'description' => __('Find reusable media and organize assets before attaching them to posts.'),
                'tips' => [__('The assistant never deletes files automatically.')],
            ],
            default => [
                'title' => __('Workspace overview'),
                'description' => __('Ask about connected channels, drafts, the queue, recent failures, analytics readiness, or where to go next.'),
                'tips' => [__('Write actions require preview and confirmation; live publishing is never automatic.')],
            ],
        };
    }

    public function search(User $user, string $term, int $limit = 15): array
    {
        $term = trim($term);

        if ($term === '') {
            return [];
        }

        $ownerId = TeamWorkspaceAccess::workspaceOwnerUserId($user);
        $teamId = TeamWorkspaceAccess::activeTeam($user)?->id;
        $results = collect();

        $results->push(...AssistantIdea::query()
            ->forWorkspace($ownerId, $teamId)
            ->where(function ($query) use ($term): void {
                $query->where('title', 'like', "%{$term}%")
                    ->orWhere('brief', 'like', "%{$term}%");
            })
            ->latest('id')
            ->limit(6)
            ->get()
            ->map(fn (AssistantIdea $idea) => [
                'type' => 'idea',
                'title' => (string) $idea->title,
                'excerpt' => Str::limit((string) $idea->brief, 130),
                'route' => null,
            ]));

        $results->push(...AIPromptHistory::query()
            ->ownedBy($ownerId)
            ->where(function ($query) use ($term): void {
                $query->where('title', 'like', "%{$term}%")
                    ->orWhere('prompt', 'like', "%{$term}%");
            })
            ->latest('id')
            ->limit(6)
            ->get()
            ->map(fn ($item) => [
                'type' => 'prompt',
                'title' => (string) ($item->title ?: Str::limit($item->prompt, 80)),
                'excerpt' => Str::limit((string) $item->prompt, 130),
                'route' => $this->routeUrl('portal.ai-studio.prompt-history'),
            ]));

        $results->push(...AppFile::query()
            ->ownedBy($user)
            ->search($term)
            ->latest('id')
            ->limit(6)
            ->get()
            ->map(fn (AppFile $file) => [
                'type' => $file->is_folder ? 'folder' : 'media',
                'title' => (string) $file->name,
                'excerpt' => $file->is_folder ? __('Folder') : trim($file->typeLabel().' · '.$file->humanSize()),
                'route' => $this->routeUrl('portal.files.index'),
            ]));

        $posts = PublishingPost::query()
            ->where('function', 'post')
            ->when(
                $teamId,
                fn ($query, int $id) => $query->where('team_id', $id),
                fn ($query) => $query->where('user_id', $ownerId)
            )
            ->where(function ($query) use ($term): void {
                $query->where('data->title', 'like', "%{$term}%")
                    ->orWhere('data->caption', 'like', "%{$term}%");
            })
            ->latest('id')
            ->limit(8)
            ->get();

        $results->push(...$posts->map(fn (PublishingPost $post) => [
            'type' => 'post',
            'title' => (string) data_get($post->data, 'title', __('Publishing post')),
            'excerpt' => Str::limit((string) data_get($post->data, 'caption', ''), 130),
            'route' => $this->routeUrl($post->status === PublishingPost::STATUS_DRAFT ? 'portal.publishing.drafts' : 'portal.publishing.queue'),
        ]));

        $results->push(...PublishingCampaign::query()
            ->ownedBy($ownerId)
            ->where('name', 'like', "%{$term}%")
            ->latest('id')
            ->limit(4)
            ->get()
            ->map(fn (PublishingCampaign $campaign) => [
                'type' => 'campaign',
                'title' => (string) $campaign->name,
                'excerpt' => Str::limit((string) $campaign->description, 130),
                'route' => $this->routeUrl('portal.publishing.campaigns'),
            ]));

        return $results->filter(fn ($result) => filled($result['title'] ?? null))->take($limit)->values()->all();
    }

    public function navigation(): array
    {
        $definitions = [
            'channels' => ['label' => __('Connected channels'), 'route' => 'portal.channels', 'icon' => 'fa-share-nodes'],
            'calendar' => ['label' => __('Publishing calendar'), 'route' => 'portal.publishing.calendar', 'icon' => 'fa-calendar-days'],
            'drafts' => ['label' => __('Draft posts'), 'route' => 'portal.publishing.drafts', 'icon' => 'fa-file-pen'],
            'queue' => ['label' => __('Publishing queue'), 'route' => 'portal.publishing.queue', 'icon' => 'fa-list-check'],
            'campaigns' => ['label' => __('Campaigns'), 'route' => 'portal.publishing.campaigns', 'icon' => 'fa-bullhorn'],
            'labels' => ['label' => __('Labels'), 'route' => 'portal.publishing.labels', 'icon' => 'fa-tags'],
            'analytics' => ['label' => __('Analytics'), 'route' => 'portal.analytics', 'icon' => 'fa-chart-line'],
            'files' => ['label' => __('Files'), 'route' => 'portal.files.index', 'icon' => 'fa-folder-open'],
            'ai_studio' => ['label' => __('AI Studio'), 'route' => 'portal.ai-studio', 'icon' => 'fa-sparkles'],
            'ai_image' => ['label' => __('Create image'), 'route' => 'portal.ai-image', 'icon' => 'fa-image'],
            'ai_video' => ['label' => __('Create video'), 'route' => 'portal.ai-video', 'icon' => 'fa-film'],
            'link_bio' => ['label' => __('Link Bio draft'), 'route' => 'portal.link-bio.create', 'icon' => 'fa-link'],
        ];

        return collect($definitions)
            ->filter(fn ($definition) => Route::has($definition['route']))
            ->map(fn ($definition, $key) => [
                'key' => $key,
                'label' => $definition['label'],
                'icon' => $definition['icon'],
                'url' => route($definition['route']),
            ])
            ->values()
            ->all();
    }

    protected function routeUrl(string $name): ?string
    {
        return Route::has($name) ? route($name) : null;
    }

    protected function friendlyPublishingFailure(PublishingPost $post): array
    {
        $provider = strtolower(trim((string) ($post->social_network ?: $post->account?->provider_key)));
        $providerLabel = Str::headline(str_replace('_', ' ', $provider ?: 'social network'));
        $raw = trim((string) data_get($post->result, 'error', data_get($post->result, 'message', '')));
        $message = $this->extractProviderMessage($raw);
        $haystack = strtolower($message.' '.$raw);
        $code = null;

        if (preg_match('/["\'](?:error_)?code["\']\s*:\s*["\']?(\d+)/i', $raw, $matches)) {
            $code = (string) $matches[1];
        }

        [$title, $summary, $steps] = match (true) {
            str_contains($haystack, 'only photo or video') || str_contains($haystack, 'media type') => [
                __(':provider needs a photo or video', ['provider' => $providerLabel]),
                __('This post did not include media in a format accepted by :provider.', ['provider' => $providerLabel]),
                [
                    __('Attach a supported JPG, PNG, or MP4 file.'),
                    __('Preview the post again before returning it to the queue.'),
                ],
            ],
            str_contains($haystack, 'expired') || str_contains($haystack, 'access token') || str_contains($haystack, 'oauth') => [
                __('The :provider connection needs attention', ['provider' => $providerLabel]),
                __('The connected account authorization may have expired or lost a required permission.'),
                [
                    __('Open Connected channels and review this account.'),
                    __('Reconnect it yourself if the account is marked expired, then retry the post.'),
                ],
            ],
            str_contains($haystack, 'permission') || str_contains($haystack, 'not authorized') => [
                __(':provider denied this publishing action', ['provider' => $providerLabel]),
                __('The connected account does not currently grant the permission required for this post.'),
                [
                    __('Check the account role and provider permissions.'),
                    __('Reconnect the channel only after verifying the correct account access.'),
                ],
            ],
            str_contains($haystack, 'rate limit') || str_contains($haystack, 'too many') => [
                __(':provider temporarily limited requests', ['provider' => $providerLabel]),
                __('The provider received too many requests in a short period.'),
                [
                    __('Wait before retrying instead of creating duplicate posts.'),
                    __('Keep the post in Draft until the provider limit clears.'),
                ],
            ],
            str_contains($haystack, 'size') || str_contains($haystack, 'dimension') || str_contains($haystack, 'aspect ratio') || str_contains($haystack, 'format') => [
                __('The media does not match :provider requirements', ['provider' => $providerLabel]),
                __('The file format, size, dimensions, or aspect ratio was rejected by the provider.'),
                [
                    __('Replace or resize the media in Files.'),
                    __('Run Preview again before scheduling.'),
                ],
            ],
            default => [
                __(':provider rejected this post', ['provider' => $providerLabel]),
                $message !== ''
                    ? Str::limit($message, 220)
                    : __('The provider did not accept the publishing request. The draft is still available for review.'),
                [
                    __('Open the failed post and review its content, media, and account state.'),
                    __('Preview the corrected post before retrying.'),
                ],
            ],
        };

        $account = (string) ($post->account?->display_name ?: $post->account?->username ?: $providerLabel);

        return [
            'post_id' => (int) $post->id,
            'account' => $account,
            'provider' => $provider,
            'provider_label' => $providerLabel,
            'title' => $title,
            'summary' => $summary,
            'steps' => $steps,
            'code' => $code,
            'edit_url' => Route::has('portal.publishing.calendar')
                ? route('portal.publishing.calendar', ['edit' => (string) ($post->id_secure ?: $post->id)])
                : null,
            'assistant_question' => __('Explain why post #:post for :account failed and give me the safest next steps.', [
                'post' => (int) $post->id,
                'account' => $account,
            ]),
        ];
    }

    protected function extractProviderMessage(string $raw): string
    {
        if ($raw === '') {
            return '';
        }

        if (preg_match('/["\']message["\']\s*:\s*["\']((?:\\\\.|[^"\'])*)["\']/i', $raw, $matches)) {
            $decoded = json_decode('"'.str_replace('"', '\\"', $matches[1]).'"', true);

            return trim(is_string($decoded) ? $decoded : stripcslashes($matches[1]));
        }

        $plain = preg_replace('/^HTTP request returned status code\s+\d+\s*:\s*/i', '', $raw) ?: $raw;
        $plain = preg_replace('/\s*\(truncated.*$/i', '', $plain) ?: $plain;

        return trim(Str::limit(strip_tags($plain), 220));
    }
}
