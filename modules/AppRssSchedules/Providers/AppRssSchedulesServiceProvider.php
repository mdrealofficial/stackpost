<?php

namespace Modules\AppRssSchedules\Providers;

use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Modules\AdminCrons\Support\SystemCronRegistry;
use Modules\AppRssSchedules\Console\Commands\DispatchQueuedRssSchedulesCommand;
use Modules\AppRssSchedules\Models\RssSchedule;
use Modules\AppRssSchedules\Models\RssScheduleHistory;
use Modules\AppTeams\Support\TeamPermissionRegistry;
use Modules\AppTeams\Support\TeamWorkspaceAccess;

class AppRssSchedulesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.apprssschedules');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'apprssschedules');

        TeamPermissionRegistry::registerPermission('rss_schedules.view', [
            'module' => 'publishing',
            'label' => __('Access RSS schedules'),
            'defaults' => [
                'admin' => ['rss_schedules.view'],
                'editor' => ['rss_schedules.view'],
            ],
        ]);

        $this->commands([
            DispatchQueuedRssSchedulesCommand::class,
        ]);

        if ($this->app->runningInConsole()) {
            $this->app->booted(function (): void {
                $this->app->make(Schedule::class)
                    ->command('rss-schedules:dispatch-queued')
                    ->everyMinute()
                    ->withoutOverlapping();
            });
        }

        $this->app->afterResolving(SystemCronRegistry::class, function (SystemCronRegistry $registry): void {
            $registry->register([
                'key' => 'rss-schedules-dispatch',
                'name' => __('RSS Schedules Dispatch'),
                'icon' => 'fa-light fa-rss',
                'description' => __('Fetch due RSS schedules and push unseen feed items into the publishing queue.'),
                'command' => 'rss-schedules:dispatch-queued',
                'recommended' => false,
                'cron_expression' => '* * * * *',
            ]);
        });

        register_plan_permission([
            'key' => 'rss_schedules',
            'label' => __('RSS Schedules'),
            'type' => 'toggle',
            'default' => true,
            'order' => 185,
        ]);

        register_user_sidebar_item('workspace', [
            'label' => 'RSS Schedules',
            'route_name' => 'portal.rss-schedules.index',
            'active_when' => ['portal.rss-schedules.*'],
            'icon' => 'fa-light fa-rss',
            'order' => 12,
            'visible' => function () {
                $user = auth()->user();
                $team = TeamWorkspaceAccess::activeTeam($user);
                $planOwner = $team?->owner ?: $user;

                if (! TeamWorkspaceAccess::teamHasModule($team, 'publishing')) {
                    return false;
                }

                if (! TeamWorkspaceAccess::hasPermission($user, 'rss_schedules.view', $team)) {
                    return false;
                }

                return $planOwner?->canUsePlanFeature('rss_schedules') ?? false;
            },
        ]);

        register_admin_dashboard_item('admin-rss-schedules.snapshot', [
            'title' => 'RSS Schedules',
            'view' => 'apprssschedules::dashboard.admin-snapshot',
            'width' => 'full',
            'order' => 32,
            'data' => fn () => (function (): array {
                $query = RssSchedule::query();
                $historyQuery = RssScheduleHistory::query();
                $dailyPoints = collect(range(6, 0))->map(function (int $offset) use ($historyQuery): array {
                    $day = now()->subDays($offset);
                    $start = $day->copy()->startOfDay()->timestamp;
                    $end = $day->copy()->endOfDay()->timestamp;

                    return [
                        'label' => $day->format('d M'),
                        'value' => (clone $historyQuery)->whereBetween('created', [$start, $end])->count(),
                    ];
                })->values();
                $nextRun = (clone $query)
                    ->where('status', true)
                    ->whereNotNull('next_run_at')
                    ->orderBy('next_run_at')
                    ->first(['next_run_at']);

                return [
                    'metrics' => [
                        'total' => (clone $query)->count(),
                        'active' => (clone $query)->where('status', true)->count(),
                        'paused' => (clone $query)->where('status', false)->count(),
                        'queued_week' => (int) $dailyPoints->sum('value'),
                        'history_total' => (clone $historyQuery)->count(),
                        'next_run_label' => $nextRun?->next_run_at
                            ? Carbon::createFromTimestamp((int) $nextRun->next_run_at)->format('d M, H:i')
                            : __('No next run'),
                    ],
                    'daily' => $dailyPoints,
                    'route' => route('portal.rss-schedules.index'),
                ];
            })(),
        ]);

        $this->app->booted(function (): void {
            \Pricing::addSubFeatures([
                'sort' => 130,
                'parent' => 'features',
                'tab_id' => 'workspace',
                'tab_name' => __('Workspace'),
                'key' => 'rss_schedules',
                'label' => __('RSS Schedules'),
                'check' => true,
                'type' => 'boolean',
                'raw' => 0,
            ]);
        });
    }
}
