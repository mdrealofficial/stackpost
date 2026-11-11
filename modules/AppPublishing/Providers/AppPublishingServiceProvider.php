<?php

namespace Modules\AppPublishing\Providers;

use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Modules\AdminCrons\Support\SystemCronRegistry;
use Modules\AppAiPublishing\Models\AiPublishingRun;
use Modules\AppPublishing\Console\Commands\DispatchDuePostsCommand;
use Modules\AppPublishing\Models\PublishingCampaign;
use Modules\AppPublishing\Models\PublishingLabel;
use Modules\AppPublishing\Models\PublishingPost;
use Modules\AppPublishing\Support\PublishingChannelRegistry;
use Modules\AppPublishing\Support\PublishingContentValidationRegistry;
use Modules\AppPublishing\Support\PublishingMediaValidationRegistry;
use Modules\AppPublishing\Support\PublishingNetworkConfigRegistry;
use Modules\AppPublishing\Support\PublishingNetworkOptionsRegistry;
use Modules\AppPublishing\Support\PublishingOptionsRegistry;
use Modules\AppPublishing\Support\PublishingPreviewRegistry;
use Modules\AppPublishing\Support\PublishingProviderPaletteRegistry;
use Modules\AppPublishing\Support\PublishingValidationFieldTargetRegistry;
use Modules\AppRssSchedules\Models\RssSchedule;
use Modules\AppRssSchedules\Models\RssScheduleHistory;
use Modules\AppTeams\Support\TeamPermissionRegistry;
use Modules\AppTeams\Support\TeamWorkspaceAccess;

class AppPublishingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.apppublishing');
        $this->app->singleton(PublishingChannelRegistry::class);
        $this->app->singleton(PublishingContentValidationRegistry::class);
        $this->app->singleton(PublishingMediaValidationRegistry::class);
        $this->app->singleton(PublishingNetworkOptionsRegistry::class);
        $this->app->singleton(PublishingProviderPaletteRegistry::class);
        $this->app->singleton(PublishingValidationFieldTargetRegistry::class);
        $this->app->singleton(PublishingNetworkConfigRegistry::class);
        $this->app->singleton(PublishingOptionsRegistry::class);
        $this->app->singleton(PublishingPreviewRegistry::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'apppublishing');

        TeamPermissionRegistry::registerModule('publishing', [
            'label' => __('Publishing'),
            'configurable' => true,
            'enabled_by_default' => true,
            'permissions' => [
                'post.create' => [
                    'label' => __('Create posts'),
                    'defaults' => [
                        'admin' => ['post.create'],
                        'editor' => ['post.create'],
                        'member' => ['post.create'],
                    ],
                ],
                'post.approve' => [
                    'label' => __('Approve queued posts'),
                    'defaults' => [
                        'admin' => ['post.approve'],
                    ],
                ],
                'post.publish' => [
                    'label' => __('Publish without approval'),
                    'defaults' => [
                        'admin' => ['post.publish'],
                    ],
                ],
                'bulk_posts.view' => [
                    'label' => __('Access bulk posts'),
                    'defaults' => [
                        'admin' => ['bulk_posts.view'],
                        'editor' => ['bulk_posts.view'],
                    ],
                ],
                'rss_schedules.view' => [
                    'label' => __('Access RSS schedules'),
                    'defaults' => [
                        'admin' => ['rss_schedules.view'],
                        'editor' => ['rss_schedules.view'],
                    ],
                ],
            ],
        ]);

        $this->commands([
            DispatchDuePostsCommand::class,
        ]);

        if ($this->app->runningInConsole()) {
            $this->app->booted(function (): void {
                $this->app->make(Schedule::class)
                    ->command('publishing:dispatch-due')
                    ->everyMinute()
                    ->withoutOverlapping();
            });
        }

        $this->app->afterResolving(SystemCronRegistry::class, function (SystemCronRegistry $registry): void {
            $registry->register([
                'key' => 'publishing-dispatch',
                'name' => __('Publishing Dispatch Due'),
                'icon' => 'fa-light fa-paper-plane-top',
                'description' => __('Dispatch scheduled publishing posts that are due for delivery.'),
                'command' => 'publishing:dispatch-due',
                'recommended' => false,
            ]);
        });

        $publishingVisible = function (): bool {
            $user = auth()->user();
            $team = TeamWorkspaceAccess::activeTeam($user);
            $planOwner = $team?->owner ?: $user;

            if (! TeamWorkspaceAccess::teamHasModule($team, 'publishing')) {
                return false;
            }

            if (! TeamWorkspaceAccess::hasPermission($user, 'post.create', $team)
                && ! TeamWorkspaceAccess::hasPermission($user, 'post.approve', $team)
                && ! TeamWorkspaceAccess::hasPermission($user, 'post.publish', $team)) {
                return false;
            }

            return $planOwner?->canUsePlanFeature('publishing') ?? false;
        };

        register_user_sidebar_item('workspace', [
            'label' => 'Publishing',
            'route_name' => 'portal.publishing.calendar',
            'active_when' => ['portal.publishing.*'],
            'icon' => 'fa-light fa-calendar-days',
            'order' => 11,
            'visible' => $publishingVisible,
        ]);

        register_user_dashboard_item('app-publishing.summary', [
            'title' => 'Publishing',
            'view' => 'apppublishing::dashboard.user-summary',
            'width' => 'full',
            'order' => 20,
            'route_name' => 'portal.publishing.calendar',
            'visible' => function ($user): bool {
                $team = TeamWorkspaceAccess::activeTeam($user);
                $planOwner = $team?->owner ?: $user;

                if (! $user || ! TeamWorkspaceAccess::teamHasModule($team, 'publishing')) {
                    return false;
                }

                if (! TeamWorkspaceAccess::hasPermission($user, 'post.create', $team)
                    && ! TeamWorkspaceAccess::hasPermission($user, 'post.approve', $team)
                    && ! TeamWorkspaceAccess::hasPermission($user, 'post.publish', $team)) {
                    return false;
                }

                return $planOwner?->canUsePlanFeature('publishing') ?? false;
            },
            'data' => function ($user): array {
                $team = TeamWorkspaceAccess::activeTeam($user);
                $planOwner = $team?->owner ?: $user;
                $hasAiPublishingRoute = Route::has('portal.ai-publishing');
                $canUseRssSchedules = TeamWorkspaceAccess::teamHasModule($team, 'publishing')
                    && ($planOwner?->canUsePlanFeature('rss_schedules') ?? false);
                $canUseAiPublishing = $hasAiPublishingRoute
                    && TeamWorkspaceAccess::teamHasModule($team, 'ai_publishing')
                    && ($planOwner?->canUsePlanFeature('ai_publishing') ?? false);
                $startOfMonth = now()->startOfMonth()->timestamp;
                $endOfMonth = now()->endOfMonth()->timestamp;
                $nextWeekEnd = now()->addDays(7)->endOfDay()->timestamp;
                $chartDays = collect(range(6, 0))->map(fn (int $offset) => now()->subDays($offset));
                $postsQuery = PublishingPost::query()->ownedBy((int) $user?->id);
                $postsThisMonth = (clone $postsQuery)->whereBetween('created', [$startOfMonth, $endOfMonth])->get(['id', 'status', 'created', 'campaign', 'labels', 'data']);
                $limit = (int) ($planOwner?->planLimit('max_posts_per_month', 0) ?? 0);
                $campaignMap = PublishingCampaign::query()
                    ->ownedBy((int) $user?->id)
                    ->get(['id', 'name', 'color'])
                    ->mapWithKeys(fn ($campaign) => [
                        (int) $campaign->id => [
                            'name' => (string) $campaign->name,
                            'color' => (string) ($campaign->color ?: '#c9802a'),
                        ],
                    ]);
                $labelMap = PublishingLabel::query()
                    ->ownedBy((int) $user?->id)
                    ->pluck('name', 'id');
                $recentPosts = (clone $postsQuery)
                    ->where('function', 'post')
                    ->with(['account:id,display_name,username,avatar_url,provider_key'])
                    ->latest('created')
                    ->limit(5)
                    ->get(['id', 'id_secure', 'account_id', 'status', 'created', 'changed', 'time_post', 'campaign', 'labels', 'social_network', 'data', 'result']);
                $providerDistribution = (clone $postsQuery)
                    ->where('function', 'post')
                    ->whereBetween('created', [$startOfMonth, $endOfMonth])
                    ->get(['social_network'])
                    ->groupBy(fn ($post) => strtolower(trim((string) ($post->social_network ?: __('Unknown')))))
                    ->map(fn ($group, $provider) => [
                        'name' => str($provider)->replace(['_', '-'], ' ')->title()->value(),
                        'y' => $group->count(),
                    ])
                    ->sortByDesc('y')
                    ->take(5)
                    ->values()
                    ->all();
                $topCampaigns = $postsThisMonth
                    ->filter(fn ($post) => filled($post->campaign))
                    ->groupBy(fn ($post) => (int) $post->campaign)
                    ->map(fn ($group, $campaignId) => [
                        'name' => (string) (($campaignMap[(int) $campaignId]['name'] ?? null) ?: __('Campaign #:id', ['id' => $campaignId])),
                        'count' => $group->count(),
                    ])
                    ->sortByDesc('count')
                    ->take(4)
                    ->values()
                    ->all();
                $topLabels = $postsThisMonth
                    ->flatMap(function ($post) use ($labelMap) {
                        $data = is_array($post->data) ? $post->data : [];
                        $options = is_array($data['options'] ?? null) ? $data['options'] : [];

                        return collect((array) ($options['label_names'] ?? []))
                            ->filter(fn ($name) => is_string($name) && trim($name) !== '')
                            ->map(fn (string $name) => trim($name))
                            ->merge(
                                collect((array) ($post->labels ?? []))
                                    ->map(fn ($id) => $labelMap[(int) $id] ?? null)
                                    ->filter()
                            );
                    })
                    ->countBy()
                    ->map(fn ($count, $name) => [
                        'name' => (string) $name,
                        'count' => (int) $count,
                    ])
                    ->sortByDesc('count')
                    ->take(6)
                    ->values()
                    ->all();
                $scheduledCount = $postsThisMonth->where('status', PublishingPost::STATUS_SCHEDULED)->count();
                $publishedCount = $postsThisMonth->where('status', PublishingPost::STATUS_PUBLISHED)->count();
                $draftCount = $postsThisMonth->where('status', PublishingPost::STATUS_DRAFT)->count();
                $failedCount = $postsThisMonth->where('status', PublishingPost::STATUS_FAILED)->count();
                $processingCount = $postsThisMonth->where('status', PublishingPost::STATUS_PROCESSING)->count();
                $rssSummary = null;
                $aiPublishingSummary = null;

                if ($canUseRssSchedules) {
                    $rssSchedules = RssSchedule::query()
                        ->where('user_id', (int) $user?->id)
                        ->get(['id', 'status', 'next_run_at', 'last_queued_at']);
                    $rssScheduleIds = $rssSchedules->pluck('id')->filter()->values();
                    $rssQueuedPosts = $rssScheduleIds->isEmpty()
                        ? 0
                        : (int) RssScheduleHistory::query()
                            ->join('posts', 'posts.id', '=', 'rss_schedule_histories.publishing_post_id')
                            ->whereIn('rss_schedule_histories.schedule_id', $rssScheduleIds)
                            ->whereIn('posts.status', [
                                PublishingPost::STATUS_PROCESSING,
                                PublishingPost::STATUS_SCHEDULED,
                            ])
                            ->count();
                    $nextRssRunAt = $rssSchedules
                        ->filter(fn ($schedule) => $schedule->status && filled($schedule->next_run_at))
                        ->sortBy('next_run_at')
                        ->first()?->next_run_at;

                    $rssSummary = [
                        'visible' => true,
                        'route' => route('portal.rss-schedules.index'),
                        'total' => $rssSchedules->count(),
                        'active' => $rssSchedules->where('status', true)->count(),
                        'paused' => $rssSchedules->where('status', false)->count(),
                        'queued' => $rssQueuedPosts,
                        'run_overdue' => $nextRssRunAt && (int) $nextRssRunAt < now()->timestamp,
                        'next_run_label' => $nextRssRunAt
                            ? Carbon::createFromTimestamp((int) $nextRssRunAt)->setTimezone(config('app.timezone'))->format('M d, Y · H:i T')
                            : __('No next run'),
                        'description' => $rssSchedules->isNotEmpty()
                            ? __('Feed-driven publishing lanes that keep your queue stocked.')
                            : __('Bring feed items into publishing without leaving this workflow.'),
                    ];
                }

                if ($canUseAiPublishing) {
                    $aiRuns = AiPublishingRun::query()
                        ->ownedBy((int) $user?->id)
                        ->get(['id', 'status', 'created_at', 'stats']);
                    $aiRunsThisMonth = $aiRuns->filter(function ($run) use ($startOfMonth, $endOfMonth): bool {
                        $createdAt = $run->created_at?->timestamp;

                        return $createdAt !== null && $createdAt >= $startOfMonth && $createdAt <= $endOfMonth;
                    });
                    $aiQueuedCount = $aiRuns->whereIn('status', ['queued', 'processing'])->count();
                    $aiCompletedCount = $aiRuns->where('status', 'completed')->count();
                    $aiFailedCount = $aiRuns->where('status', 'failed')->count();
                    $aiLimit = (int) ($planOwner?->planLimit('max_ai_publishing_posts_per_month', 0) ?? 0);

                    $aiPublishingSummary = [
                        'visible' => true,
                        'route' => route('portal.ai-publishing'),
                        'total' => $aiRuns->count(),
                        'queued' => $aiQueuedCount,
                        'completed' => $aiCompletedCount,
                        'failed' => $aiFailedCount,
                        'limit' => $aiLimit,
                        'usage_percent' => $aiLimit > 0
                            ? min(100, (int) round(($aiRunsThisMonth->count() / $aiLimit) * 100))
                            : null,
                        'month_total' => $aiRunsThisMonth->count(),
                        'description' => $aiRuns->isNotEmpty()
                            ? __('AI-assisted publishing runs that draft and queue posts for you.')
                            : __('Use AI-generated posting runs directly alongside your manual calendar.'),
                    ];
                }

                $reportQuery = (clone $postsQuery)->where('function', 'post');
                $reportRange = (int) request()->attributes->get('dashboard_report_days', 30);
                $reportRange = in_array($reportRange, [30,90,365], true) ? $reportRange : 30;
                $reportDays = collect(range($reportRange - 1, 0))->map(fn ($offset) => now()->subDays($offset)->startOfDay());
                $reportRows = (clone $reportQuery)->whereBetween('created', [$reportDays->first()->timestamp, now()->endOfDay()->timestamp])
                    ->get(['status', 'created', 'social_network', 'data', 'campaign']);
                $byDay = $reportRows->groupBy(fn ($post) => Carbon::createFromTimestamp((int) $post->created)->format('Y-m-d'));
                $allStatuses = (clone $reportQuery)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
                // Action cards use the same active-channel scope as Queue and Drafts.
                $publishableKeys = publishable_channel_capability_keys($user);
                $actionChannelIds = TeamWorkspaceAccess::accessibleAccountsQuery($user)
                    ->where('created_by_user_id', TeamWorkspaceAccess::workspaceOwnerUserId($user))
                    ->where('is_active', true)
                    ->where(function ($query) use ($publishableKeys): void {
                        $query->whereIn('capability_key', $publishableKeys)
                            ->orWhere(fn ($fallback) => $fallback->whereNull('capability_key')->whereIn('provider_key', $publishableKeys));
                    })->pluck('id');
                $actionPosts = PublishingPost::query()->where('function', 'post')
                    ->when($team?->id,
                        fn ($query, $teamId) => $query->where('team_id', $teamId),
                        fn ($query) => $query->ownedBy((int) $user?->id))
                    ->whereIn('account_id', $actionChannelIds);
                $actionStatuses = (clone $actionPosts)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
                $upcomingRows = (clone $reportQuery)->where('status', PublishingPost::STATUS_SCHEDULED)
                    ->whereBetween('time_post', [now()->timestamp, $nextWeekEnd])->orderBy('time_post')
                    ->with('account:id,display_name')->limit(6)->get(['id_secure','account_id','data','time_post']);
                $scheduleCounts = (clone $reportQuery)->where('status', PublishingPost::STATUS_SCHEDULED)
                    ->whereBetween('time_post', [now()->startOfDay()->timestamp, $nextWeekEnd])->get(['time_post'])
                    ->groupBy(fn ($post) => Carbon::createFromTimestamp((int)$post->time_post)->format('Y-m-d'));
                $scheduleDays = collect(range(0, 7))->map(fn ($offset) => now()->addDays($offset));
                $reportNetworks = $reportRows->groupBy(fn ($post) => (string)($post->social_network ?: __('Unknown')))
                    ->map(fn ($rows,$name) => ['name'=>str($name)->replace(['_', '-'], ' ')->title()->value(),
                        'total'=>$rows->count(), 'published'=>$rows->where('status', PublishingPost::STATUS_PUBLISHED)->count(),
                        'failed'=>$rows->where('status', PublishingPost::STATUS_FAILED)->count()])->sortByDesc('total')->take(8)->values();
                $reportFormats = $reportRows->groupBy(fn ($post) => strtoupper((string)data_get($post->data, 'media_type', 'text')))
                    ->map(fn ($rows,$name) => ['name'=>$name,'y'=>$rows->count()])->values()->all();
                $reports = [
                    'days'=>$reportRange,
                    'action_failed'=>(int) ($actionStatuses[PublishingPost::STATUS_FAILED] ?? 0),
                    'action_drafts'=>(int) ($actionStatuses[PublishingPost::STATUS_DRAFT] ?? 0),
                    'action_overdue'=>(clone $actionPosts)->where('status', PublishingPost::STATUS_SCHEDULED)->where('time_post', '>', 0)->where('time_post', '<', now()->timestamp)->count(),
                    'total'=>$reportRows->count(),
                    'categories'=>$reportDays->map(fn($day)=>$day->format('M d'))->all(),
                    'created'=>$reportDays->map(fn($day)=>($byDay->get($day->format('Y-m-d')) ?? collect())->count())->all(),
                    'published'=>$reportDays->map(fn($day)=>($byDay->get($day->format('Y-m-d')) ?? collect())->where('status',PublishingPost::STATUS_PUBLISHED)->count())->all(),
                    'statuses'=>collect([1=>__('Drafts'),2=>__('Processing'),3=>__('Scheduled'),4=>__('Published'),5=>__('Failed')])->map(fn($name,$status)=>['name'=>$name,'y'=>(int)($allStatuses[$status] ?? 0)])->values()->all(),
                    'backlog'=>(int)($allStatuses[PublishingPost::STATUS_DRAFT] ?? 0),
                    'failed'=>(int)($allStatuses[PublishingPost::STATUS_FAILED] ?? 0),
                    'overdue'=>(clone $reportQuery)->where('status',PublishingPost::STATUS_SCHEDULED)->where('time_post','<',now()->timestamp)->where('time_post','>',0)->count(),
                    'schedule_categories'=>$scheduleDays->map(fn($day)=>$day->format('D d'))->all(),
                    'schedule_counts'=>$scheduleDays->map(fn($day)=>($scheduleCounts->get($day->format('Y-m-d')) ?? collect())->count())->all(),
                    'networks'=>$reportNetworks->all(), 'formats'=>$reportFormats,
                    'upcoming'=>$upcomingRows->map(fn($post)=>['title'=>Str::limit((string)data_get($post->data,'caption',__('Untitled post')),70),'channel'=>$post->account?->display_name ?: __('Channel'),'date'=>Carbon::createFromTimestamp((int)$post->time_post)->format('M d, H:i'),'route'=>route('portal.publishing.calendar',['edit'=>$post->id_secure])])->all(),
                ];

                return [
                    'reports' => $reports,
                    'metrics' => [
                        'month_total' => $postsThisMonth->count(),
                        'scheduled' => $scheduledCount,
                        'published' => $publishedCount,
                        'drafts' => $draftCount,
                        'failed' => $failedCount,
                        'processing' => $processingCount,
                        'campaigns' => PublishingCampaign::query()->ownedBy((int) $user?->id)->count(),
                        'labels' => PublishingLabel::query()->ownedBy((int) $user?->id)->count(),
                        'accounts' => TeamWorkspaceAccess::accessibleAccountsQuery($user)
                            ->where('is_active', true)
                            ->count(),
                        'upcoming' => (clone $postsQuery)
                            ->where('function', 'post')
                            ->where('status', PublishingPost::STATUS_SCHEDULED)
                            ->whereBetween('time_post', [now()->timestamp, $nextWeekEnd])
                            ->count(),
                        'limit' => $limit,
                        'usage_percent' => $limit > 0 ? min(100, (int) round(($postsThisMonth->count() / $limit) * 100)) : null,
                        'publish_success_rate' => ($publishedCount + $failedCount) > 0
                            ? (int) round(($publishedCount / ($publishedCount + $failedCount)) * 100)
                            : null,
                    ],
                    'chart' => [
                        'categories' => $chartDays->map(fn ($day) => $day->format('M d'))->all(),
                        'series' => [[
                            'name' => __('Posts'),
                            'data' => $chartDays->map(function ($day) use ($postsQuery): int {
                                $start = $day->copy()->startOfDay()->timestamp;
                                $end = $day->copy()->endOfDay()->timestamp;

                                return (clone $postsQuery)->whereBetween('created', [$start, $end])->count();
                            })->all(),
                        ]],
                    ],
                    'statusChart' => [[
                        'name' => __('Posts'),
                        'data' => [
                            ['name' => __('Published'), 'y' => $publishedCount],
                            ['name' => __('Scheduled'), 'y' => $scheduledCount],
                            ['name' => __('Drafts'), 'y' => $draftCount],
                            ['name' => __('Failed'), 'y' => $failedCount],
                            ['name' => __('Processing'), 'y' => $processingCount],
                        ],
                    ]],
                    'providerChart' => [[
                        'name' => __('Networks'),
                        'data' => $providerDistribution,
                    ]],
                    'topCampaigns' => $topCampaigns,
                    'topLabels' => $topLabels,
                    'rssSummary' => $rssSummary,
                    'aiPublishingSummary' => $aiPublishingSummary,
                    'recentPosts' => $recentPosts->map(function ($post) use ($campaignMap): array {
                        $resultPayload = [];

                        if (is_array($post->result)) {
                            $resultPayload = $post->result;
                        } elseif (is_string($post->result) && trim($post->result) !== '') {
                            $decoded = json_decode($post->result, true);
                            $resultPayload = is_array($decoded) ? $decoded : ['raw' => trim($post->result)];
                        }

                        $postUrl = collect([
                            $resultPayload['url'] ?? null,
                            $resultPayload['link'] ?? null,
                            $resultPayload['post_url'] ?? null,
                            $resultPayload['permalink'] ?? null,
                            $resultPayload['permalink_url'] ?? null,
                            data_get($resultPayload, 'data.url'),
                            data_get($resultPayload, 'data.link'),
                            data_get($resultPayload, 'data.post_url'),
                            data_get($resultPayload, 'data.permalink'),
                            data_get($resultPayload, 'data.permalink_url'),
                            data_get($resultPayload, 'response.url'),
                            data_get($resultPayload, 'response.link'),
                            data_get($resultPayload, 'response.post_url'),
                            data_get($resultPayload, 'response.permalink'),
                            data_get($resultPayload, 'response.permalink_url'),
                        ])
                            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
                            ->map(fn ($value) => trim((string) $value))
                            ->first(fn ($value) => Str::startsWith($value, ['http://', 'https://']));

                        $publishError = collect([
                            $resultPayload['error'] ?? null,
                            $resultPayload['message'] ?? null,
                            $resultPayload['msg'] ?? null,
                            data_get($resultPayload, 'data.error'),
                            data_get($resultPayload, 'data.message'),
                            data_get($resultPayload, 'response.error'),
                            data_get($resultPayload, 'response.message'),
                            data_get($resultPayload, 'raw'),
                        ])
                            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
                            ->map(fn ($value) => trim(strip_tags((string) $value)))
                            ->first();

                        $statusLabel = match ((int) $post->status) {
                            PublishingPost::STATUS_DRAFT => __('Draft'),
                            PublishingPost::STATUS_PROCESSING => __('Processing'),
                            PublishingPost::STATUS_SCHEDULED => __('Scheduled'),
                            PublishingPost::STATUS_PUBLISHED => __('Published'),
                            PublishingPost::STATUS_FAILED => __('Failed'),
                            default => __('Pending'),
                        };
                        $statusTone = match ((int) $post->status) {
                            PublishingPost::STATUS_PUBLISHED => ['surface' => 'rgba(111,207,151,0.16)', 'text' => '#2f8f62'],
                            PublishingPost::STATUS_SCHEDULED => ['surface' => 'rgba(195,139,43,0.12)', 'text' => '#a76c15'],
                            PublishingPost::STATUS_FAILED => ['surface' => 'rgba(242,139,130,0.16)', 'text' => '#cf625b'],
                            PublishingPost::STATUS_PROCESSING => ['surface' => 'rgba(103,183,220,0.16)', 'text' => '#4b93b0'],
                            default => ['surface' => 'rgba(135,149,165,0.12)', 'text' => '#64748b'],
                        };
                        if ((int) $post->status === PublishingPost::STATUS_SCHEDULED
                            && (int) $post->time_post > 0 && (int) $post->time_post < now()->timestamp) {
                            $statusLabel = __('Overdue');
                        }
                        $postData = is_array($post->data) ? $post->data : [];
                        $mediaItems = collect((array) ($postData['medias'] ?? []))->values();
                        $primaryMedia = $mediaItems->first();
                        $primaryPreview = (string) data_get($primaryMedia, 'previewUrl', data_get($primaryMedia, 'url', ''));
                        $primaryMime = (string) data_get($primaryMedia, 'mimeType', '');
                        $mediaType = strtolower((string) ($postData['media_type'] ?? 'text'));
                        $channelName = (string) ($post->account?->display_name ?: str((string) ($post->social_network ?: __('Unknown')))->replace(['_', '-'], ' ')->title()->value());
                        $scheduledAt = $post->time_post ? now()->createFromTimestamp((int) $post->time_post) : null;
                        $createdAt = $post->created ? now()->createFromTimestamp((int) $post->created) : null;
                        $editable = in_array((int) $post->status, [
                            PublishingPost::STATUS_DRAFT,
                            PublishingPost::STATUS_SCHEDULED,
                            PublishingPost::STATUS_FAILED,
                        ], true);

                        return [
                            'id' => (string) ($post->id_secure ?: $post->id),
                            'status' => $statusLabel,
                            'status_tone' => $statusTone,
                            'network' => $channelName,
                            'handle' => $post->account?->username ? '@'.$post->account->username : null,
                            'campaign' => $campaignMap[(int) ($post->campaign ?? 0)]['name'] ?? null,
                            'campaign_color' => $campaignMap[(int) ($post->campaign ?? 0)]['color'] ?? null,
                            'title' => (string) ($postData['title'] ?? Str::limit((string) ($postData['caption'] ?? ''), 56, '...')),
                            'caption' => (string) ($postData['caption'] ?? ''),
                            'media_type' => strtoupper($mediaType !== '' ? $mediaType : 'TEXT'),
                            'media_count' => $mediaItems->count(),
                            'media_preview' => $primaryPreview,
                            'media_mime' => $primaryMime,
                            'is_video' => str_starts_with($primaryMime, 'video/') || in_array($mediaType, ['video', 'reel'], true),
                            'scheduled' => $scheduledAt?->format('Y-m-d H:i'),
                            'created' => $createdAt?->format('Y-m-d H:i'),
                            'route' => $editable
                                ? route('portal.publishing.calendar', ['edit' => (string) ($post->id_secure ?: $post->id)])
                                : route('portal.publishing.calendar'),
                            'editable' => $editable,
                            'post_url' => $postUrl,
                            'error' => $publishError ? Str::limit($publishError, 140) : null,
                        ];
                    })->all(),
                    'campaignRoute' => route('portal.publishing.campaigns'),
                    'labelRoute' => route('portal.publishing.labels'),
                ];
            },
            'visible' => function () {
                $user = auth()->user();
                $team = TeamWorkspaceAccess::activeTeam($user);
                $planOwner = $team?->owner ?: $user;

                if (! TeamWorkspaceAccess::teamHasModule($team, 'publishing')) {
                    return false;
                }

                return $planOwner?->canUsePlanFeature('publishing') ?? false;
            },
        ]);

        register_admin_dashboard_item('admin-publishing.snapshot', [
            'title' => 'Publishing',
            'view' => 'apppublishing::dashboard.admin-snapshot',
            'width' => 'full',
            'order' => 31,
            'data' => fn () => (function (): array {
                $query = PublishingPost::query()
                    ->where('function', 'post');
                $startOfMonth = now()->startOfMonth()->timestamp;
                $endOfMonth = now()->endOfMonth()->timestamp;
                $dailyPoints = collect(range(6, 0))->map(function (int $offset) use ($query): array {
                    $day = now()->subDays($offset);
                    $start = $day->copy()->startOfDay()->timestamp;
                    $end = $day->copy()->endOfDay()->timestamp;

                    return [
                        'label' => $day->format('d M'),
                        'value' => (clone $query)->whereBetween('created', [$start, $end])->count(),
                    ];
                })->values();

                return [
                    'metrics' => [
                        'total' => (clone $query)->count(),
                        'published' => (clone $query)->where('status', PublishingPost::STATUS_PUBLISHED)->count(),
                        'scheduled' => (clone $query)->where('status', PublishingPost::STATUS_SCHEDULED)->count(),
                        'failed' => (clone $query)->where('status', PublishingPost::STATUS_FAILED)->count(),
                        'this_month' => (clone $query)->whereBetween('created', [$startOfMonth, $endOfMonth])->count(),
                        'campaigns' => PublishingCampaign::query()->count(),
                        'labels' => PublishingLabel::query()->count(),
                        'week_total' => (int) $dailyPoints->sum('value'),
                    ],
                    'daily' => $dailyPoints,
                    'route' => route('portal.publishing.calendar'),
                ];
            })(),
        ]);

        $publishingHeaderVisible = function (): bool {
            $user = auth()->user();
            $team = TeamWorkspaceAccess::activeTeam($user);
            $planOwner = $team?->owner ?: $user;

            if (! request()->routeIs('portal.publishing.*', 'portal.ai-publishing*', 'portal.bulk-posts*', 'portal.rss-schedules.*')) {
                return false;
            }

            if (! TeamWorkspaceAccess::teamHasModule($team, 'publishing')) {
                return false;
            }

            return $planOwner?->canUsePlanFeature('publishing') ?? false;
        };

        register_header_item([
            'label' => 'Calendar',
            'icon' => 'fa-light fa-calendar-days',
            'route_name' => 'portal.publishing.calendar',
            'active_when' => ['portal.publishing.calendar'],
            'position' => 'center',
            'order' => 10,
            'visible' => $publishingHeaderVisible,
        ], 'user');

        register_header_item([
            'label' => 'Queue',
            'icon' => 'fa-light fa-list-check',
            'route_name' => 'portal.publishing.queue',
            'active_when' => ['portal.publishing.queue'],
            'position' => 'center',
            'order' => 20,
            'visible' => $publishingHeaderVisible,
        ], 'user');

        register_header_item([
            'label' => 'Drafts',
            'icon' => 'fa-light fa-file-pen',
            'route_name' => 'portal.publishing.drafts',
            'active_when' => ['portal.publishing.drafts'],
            'position' => 'center',
            'order' => 30,
            'visible' => $publishingHeaderVisible,
        ], 'user');

        register_header_item([
            'label' => 'Labels',
            'icon' => 'fa-light fa-tags',
            'route_name' => 'portal.publishing.labels',
            'active_when' => ['portal.publishing.labels'],
            'position' => 'center',
            'order' => 40,
            'visible' => $publishingHeaderVisible,
        ], 'user');

        register_header_item([
            'label' => 'Approvals',
            'icon' => 'fa-light fa-badge-check',
            'route_name' => 'portal.publishing.approvals',
            'active_when' => ['portal.publishing.approvals'],
            'position' => 'center',
            'order' => 35,
            'visible' => function () use ($publishingHeaderVisible): bool {
                if (! $publishingHeaderVisible()) {
                    return false;
                }

                $user = auth()->user();
                $team = TeamWorkspaceAccess::activeTeam($user);

                if (! $team || ! $user) {
                    return false;
                }

                if ((int) $team->owner_user_id === (int) $user->id) {
                    return true;
                }

                $member = $team->members()->where('users.id', $user->id)->first();

                if (! $member) {
                    return false;
                }

                return TeamWorkspaceAccess::hasPermission($user, 'post.approve', $team);
            },
        ], 'user');

        register_header_item([
            'label' => 'Campaigns',
            'icon' => 'fa-light fa-bullhorn',
            'route_name' => 'portal.publishing.campaigns',
            'active_when' => ['portal.publishing.campaigns'],
            'position' => 'center',
            'order' => 50,
            'visible' => $publishingHeaderVisible,
        ], 'user');

        register_plan_permission([
            'key' => 'publishing',
            'label' => __('Publishing'),
            'type' => 'config',
            'order' => 30,
            'fields' => [
                [
                    'key' => 'max_posts_per_month',
                    'label' => __('Maximum Posts per Month'),
                    'type' => 'number',
                    'default' => 100,
                    'description' => __('Enter the total number of posts permitted for this package; input -1 for unlimited posts'),
                ],
                [
                    'key' => 'allowed_publishing_channels',
                    'label' => __('Allow channels'),
                    'type' => 'checkbox_list',
                    'options' => collect(channel_capabilities())
                        ->filter(fn (array $capability): bool => (bool) ($capability['supports_publishing'] ?? true))
                        ->map(function (array $capability, string $key): array {
                            return [
                                'key' => $key,
                                'label' => $capability['title'] ?? $capability['label'] ?? $key,
                            ];
                        })
                        ->values()
                        ->all(),
                ],
                [
                    'key' => 'publishing_features',
                    'label' => __('Features'),
                    'type' => 'checkbox_list',
                    'options' => [
                        ['key' => 'campaign_publishing', 'label' => __('Campaign Publishing')],
                        ['key' => 'label_publishing', 'label' => __('Label Publishing')],
                    ],
                ],
            ],
        ]);

        $this->app->booted(function (): void {
            \Pricing::addSubFeatures([
                ['sort' => 10, 'parent' => 'features', 'tab_id' => 'publishing', 'tab_name' => __('Publishing'), 'key' => 'publishing', 'label' => __('Publishing'), 'check' => true, 'type' => 'boolean', 'raw' => 0],
                ['sort' => 20, 'parent' => 'features', 'tab_id' => 'publishing', 'tab_name' => __('Publishing'), 'key' => 'max_posts_per_month', 'label' => __('Posts / month'), 'check' => true, 'type' => 'number', 'raw' => 0],
                ['sort' => 30, 'parent' => 'features', 'tab_id' => 'publishing', 'tab_name' => __('Publishing'), 'key' => 'campaign_publishing', 'label' => __('Campaign Publishing'), 'check' => true, 'type' => 'boolean', 'raw' => 0],
                ['sort' => 40, 'parent' => 'features', 'tab_id' => 'publishing', 'tab_name' => __('Publishing'), 'key' => 'label_publishing', 'label' => __('Label Publishing'), 'check' => true, 'type' => 'boolean', 'raw' => 0],
            ]);
        });
    }
}
