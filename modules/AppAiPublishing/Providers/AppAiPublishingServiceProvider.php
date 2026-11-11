<?php

namespace Modules\AppAiPublishing\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\AdminCrons\Support\SystemCronRegistry;
use Modules\AppAiPublishing\Console\Commands\DispatchQueuedAiPublishingRunsCommand;
use Modules\AppAiPublishing\Models\AiPublishingRun;
use Modules\AppTeams\Support\TeamPermissionRegistry;
use Modules\AppTeams\Support\TeamWorkspaceAccess;

class AppAiPublishingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.appaipublishing');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'appaipublishing');

        TeamPermissionRegistry::registerModule('ai_publishing', [
            'label' => __('AI publishing'),
            'configurable' => true,
            'enabled_by_default' => true,
        ]);

        $this->commands([
            DispatchQueuedAiPublishingRunsCommand::class,
        ]);

        if ($this->app->runningInConsole()) {
            $this->app->booted(function (): void {
                $this->app->make(Schedule::class)
                    ->command('ai-publishing:dispatch-queued')
                    ->everyMinute()
                    ->withoutOverlapping();
            });
        }

        $this->app->afterResolving(SystemCronRegistry::class, function (SystemCronRegistry $registry): void {
            $registry->register([
                'key' => 'ai-publishing-dispatch',
                'name' => __('AI Publishing Dispatch Queued'),
                'icon' => 'fa-light fa-sparkles',
                'description' => __('Generate queued AI publishing schedules and push them into the publishing posts table.'),
                'command' => 'ai-publishing:dispatch-queued',
                'recommended' => false,
                'cron_expression' => '* * * * *',
            ]);
        });

        register_credit_action([
            'key' => 'ai_publishing_generate_content',
            'label' => __('AI Publishing Caption Generation'),
            'default_cost' => 1,
            'order' => 20,
            'description' => __('Credits deducted each time AI generates a caption for AI Publishing.'),
        ]);

        register_credit_action([
            'key' => 'ai_publishing_generate_image',
            'label' => __('AI Publishing Image Generation'),
            'default_cost' => 3,
            'order' => 21,
            'description' => __('Credits deducted each time AI generates an image for AI Publishing.'),
        ]);

        register_plan_permission([
            'key' => 'ai_publishing',
            'label' => __('AI Publishing'),
            'type' => 'config',
            'order' => 80,
            'fields' => [
                [
                    'key' => 'max_ai_publishing_posts_per_month',
                    'label' => __('Maximum AI Publishing Posts per Month'),
                    'type' => 'number',
                    'default' => 50,
                    'description' => __('Allow AI to generate captions and images, then schedule posts automatically. Enter -1 for unlimited AI publishing runs per month.'),
                ],
                [
                    'key' => 'ai_publishing_generate_images',
                    'label' => __('Generate images with AI'),
                    'type' => 'boolean',
                    'default' => '1',
                ],
                [
                    'key' => 'ai_publishing_user_schedule_time',
                    'label' => __('User can set schedule time'),
                    'type' => 'boolean',
                    'default' => '1',
                ],
            ],
        ]);

        register_user_sidebar_item('workspace', [
            'label' => 'AI Schedules',
            'route_name' => 'portal.ai-publishing',
            'active_when' => ['portal.ai-publishing', 'portal.ai-publishing.*'],
            'icon' => 'fa-light fa-sparkles',
            'order' => 13,
            'visible' => function (): bool {
                $user = auth()->user();
                $team = TeamWorkspaceAccess::activeTeam($user);
                $planOwner = $team?->owner ?: $user;

                if (! TeamWorkspaceAccess::teamHasModule($team, 'ai_publishing')) {
                    return false;
                }

                return $planOwner?->canUsePlanFeature('ai_publishing') ?? false;
            },
        ]);

        register_admin_dashboard_item('admin-ai-publishing.snapshot', [
            'title' => 'AI Publishing',
            'view' => 'appaipublishing::dashboard.admin-snapshot',
            'width' => 'full',
            'order' => 33,
            'data' => fn () => (function (): array {
                $query = AiPublishingRun::query();
                $startOfMonth = now()->startOfMonth();
                $dailyPoints = collect(range(6, 0))->map(function (int $offset) use ($query): array {
                    $day = now()->subDays($offset);

                    return [
                        'label' => $day->format('d M'),
                        'value' => (clone $query)
                            ->whereBetween('created_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
                            ->count(),
                    ];
                })->values();

                return [
                    'metrics' => [
                        'total' => (clone $query)->count(),
                        'queued' => (clone $query)->whereIn('status', ['queued', 'processing'])->count(),
                        'completed' => (clone $query)->where('status', 'completed')->count(),
                        'failed' => (clone $query)->where('status', 'failed')->count(),
                        'this_month' => (clone $query)->where('created_at', '>=', $startOfMonth)->count(),
                        'week_total' => (int) $dailyPoints->sum('value'),
                    ],
                    'daily' => $dailyPoints,
                    'route' => route('portal.ai-publishing'),
                ];
            })(),
        ]);

        $this->app->booted(function (): void {
            \Pricing::addSubFeatures([
                ['sort' => 80, 'parent' => 'features', 'tab_id' => 'content', 'tab_name' => __('Content'), 'key' => 'ai_publishing', 'label' => __('AI Publishing'), 'check' => true, 'type' => 'boolean', 'raw' => 0],
                ['sort' => 90, 'parent' => 'features', 'tab_id' => 'content', 'tab_name' => __('Content'), 'key' => 'max_ai_publishing_posts_per_month', 'label' => __('AI posts / month'), 'check' => true, 'type' => 'number', 'raw' => 0],
            ]);
        });
    }
}
