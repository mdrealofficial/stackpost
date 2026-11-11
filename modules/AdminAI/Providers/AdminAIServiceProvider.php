<?php

namespace Modules\AdminAI\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AdminAI\Support\AiOptionCatalog;

class AdminAIServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.adminai');
        $this->app->singleton(AiOptionCatalog::class);

        $helper = __DIR__.'/../Support/helpers.php';

        if (is_file($helper)) {
            require_once $helper;
        }
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'adminai');

        register_sidebar_item('ai-settings', [
            'label' => 'AI Report',
            'route_name' => 'admin-ai-report.index',
            'active_when' => ['admin-ai-report.*'],
            'icon' => 'fa-light fa-chart-network',
            'order' => 10,
        ]);

        register_sidebar_item('ai-settings', [
            'label' => 'AI Usage Logs',
            'route_name' => 'admin-ai-usage-logs.index',
            'active_when' => ['admin-ai-usage-logs.*'],
            'icon' => 'fa-light fa-microchip-ai',
            'order' => 20,
        ]);

        register_setting_item('general', [
            'label' => 'AI Settings',
            'description' => 'Choose default AI providers, baseline models, credentials, and system-wide behavior.',
            'route_name' => 'settings.ai',
            'active_when' => ['settings.ai', 'settings.ai.*'],
            'order' => 13,
        ]);

        register_admin_dashboard_item('admin-ai.snapshot', [
            'title' => 'AI',
            'view' => 'adminai::dashboard.snapshot',
            'width' => 'full',
            'order' => 25,
            'data' => fn () => (function () {
                $query = \Modules\AdminAI\Models\AiUsageLog::query();
                $logs = $query->get(['created_at', 'status', 'total_tokens', 'estimated_cost', 'latency_ms']);

                $daily = collect(range(6, 0))->map(function (int $offset) use ($logs) {
                    $date = now()->subDays($offset);

                    return [
                        'label' => $date->format('d M'),
                        'value' => $logs->filter(fn ($log) => $log->created_at?->isSameDay($date))->count(),
                    ];
                })->values();

                $totalRequests = $logs->count();
                $successfulRequests = $logs->where('status', 'success')->count();
                $failedRequests = $logs->where('status', 'failed')->count();
                $latencyLogs = $logs->whereNotNull('latency_ms');

                return [
                    'metrics' => [
                        'total_requests' => $totalRequests,
                        'successful_requests' => $successfulRequests,
                        'failed_requests' => $failedRequests,
                        'success_rate' => $totalRequests > 0 ? (int) round(($successfulRequests / $totalRequests) * 100) : 0,
                        'total_tokens' => (int) $logs->sum('total_tokens'),
                        'estimated_cost' => (float) $logs->sum('estimated_cost'),
                        'avg_latency' => $latencyLogs->count() > 0 ? (int) round($latencyLogs->avg('latency_ms')) : 0,
                        'requests_7d' => (int) $daily->sum('value'),
                    ],
                    'daily' => $daily,
                    'reportRoute' => route('admin-ai-report.index'),
                    'logsRoute' => route('admin-ai-usage-logs.index'),
                ];
            })(),
        ]);

        $this->app->booted(function (): void {
            \Pricing::addSubFeatures([
                'sort' => 200,
                'parent' => 'features',
                'tab_id' => 'ops',
                'tab_name' => __('Operations'),
                'key' => 'credits_usage_limit',
                'label' => __('Credits'),
                'check' => true,
                'type' => 'number',
                'raw' => 0,
            ]);
        });
    }
}
