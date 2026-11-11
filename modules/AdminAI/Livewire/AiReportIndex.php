<?php

namespace Modules\AdminAI\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\AdminAI\Models\AiUsageLog;

#[Title('AI Report')]
class AiReportIndex extends Component
{
    #[Url(as: 'from', except: '')]
    public string $fromDate = '';

    #[Url(as: 'to', except: '')]
    public string $toDate = '';

    public function mount(): void
    {
        [$startDate, $endDate] = $this->resolveRange();

        $this->fromDate = $startDate->toDateString();
        $this->toDate = $endDate->toDateString();
    }

    public function resetFilters(): void
    {
        $this->fromDate = '';
        $this->toDate = '';

        [$startDate, $endDate] = $this->resolveRange();

        $this->fromDate = $startDate->toDateString();
        $this->toDate = $endDate->toDateString();
    }

    public function render(): View
    {
        [$startDate, $endDate] = $this->resolveRange();

        $logs = AiUsageLog::query()
            ->with('user')
            ->whereBetween('created_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->get();

        $daily = collect(range(0, $startDate->diffInDays($endDate)))
            ->map(function (int $offset) use ($logs, $startDate) {
                $date = $startDate->copy()->addDays($offset);
                $dayLogs = $logs->filter(fn (AiUsageLog $log) => $log->created_at?->isSameDay($date));
                $dayTotal = $dayLogs->count();
                $daySuccess = $dayLogs->where('status', 'success')->count();
                $latencyLogs = $dayLogs->whereNotNull('latency_ms');

                return [
                    'label' => $date->format('d M'),
                    'requests' => $dayTotal,
                    'tokens' => (int) $dayLogs->sum('total_tokens'),
                    'cost' => (float) $dayLogs->sum('estimated_cost'),
                    'success_rate' => $dayTotal > 0 ? round(($daySuccess / $dayTotal) * 100, 1) : 0,
                    'avg_latency' => $latencyLogs->count() > 0 ? round((float) $latencyLogs->avg('latency_ms')) : 0,
                ];
            })
            ->values();

        $providerBreakdown = $logs
            ->groupBy(fn (AiUsageLog $log) => strtoupper((string) $log->provider))
            ->map(fn ($group, $provider) => [
                'label' => $provider ?: 'UNKNOWN',
                'requests' => $group->count(),
                'tokens' => (int) $group->sum('total_tokens'),
                'cost' => (float) $group->sum('estimated_cost'),
            ])
            ->sortByDesc('requests')
            ->values();

        $capabilityBreakdown = $logs
            ->groupBy(fn (AiUsageLog $log) => ucfirst((string) $log->capability))
            ->map(fn ($group, $capability) => [
                'label' => $capability ?: 'Unknown',
                'requests' => $group->count(),
                'tokens' => (int) $group->sum('total_tokens'),
            ])
            ->sortByDesc('requests')
            ->values();

        $modelBreakdown = $logs
            ->groupBy(fn (AiUsageLog $log) => (string) ($log->model ?: 'Unknown'))
            ->map(fn ($group, $model) => [
                'label' => $model,
                'requests' => $group->count(),
                'tokens' => (int) $group->sum('total_tokens'),
                'cost' => (float) $group->sum('estimated_cost'),
            ])
            ->sortByDesc('requests')
            ->take(8)
            ->values();

        $hourlyDistribution = collect(range(0, 23))->map(function (int $hour) use ($logs) {
            $hourLogs = $logs->filter(fn (AiUsageLog $log) => (int) $log->created_at?->format('G') === $hour);

            return [
                'label' => str_pad((string) $hour, 2, '0', STR_PAD_LEFT).':00',
                'requests' => $hourLogs->count(),
            ];
        });

        $weekdayPerformance = collect(range(1, 7))->map(function (int $isoDay) use ($logs) {
            $dayLogs = $logs->filter(fn (AiUsageLog $log) => (int) $log->created_at?->dayOfWeekIso === $isoDay);
            $sample = Carbon::now()->startOfWeek()->addDays($isoDay - 1);

            return [
                'label' => $sample->format('D'),
                'requests' => $dayLogs->count(),
                'tokens' => (int) $dayLogs->sum('total_tokens'),
            ];
        });

        $statusMix = collect(['success', 'failed', 'pending'])
            ->map(function (string $status) use ($logs) {
                $count = $logs->where('status', $status)->count();

                return [
                    'label' => ucfirst($status),
                    'value' => $count,
                    'color' => match ($status) {
                        'success' => '#0f766e',
                        'failed' => '#e11d48',
                        default => '#94a3b8',
                    },
                ];
            })
            ->filter(fn (array $status) => $status['value'] > 0)
            ->values();

        $featureBreakdown = $logs
            ->map(function (AiUsageLog $log) {
                $label = trim(collect([$log->feature, $log->route_name])->filter()->implode(' | '));

                return $label !== '' ? $label : 'Unclassified';
            })
            ->countBy()
            ->map(fn ($count, $label) => ['label' => $label, 'value' => $count])
            ->sortByDesc('value')
            ->take(8)
            ->values();

        $topUsers = $logs
            ->filter(fn (AiUsageLog $log) => $log->user)
            ->groupBy('user_id')
            ->map(function ($group) {
                $user = $group->first()->user;

                return [
                    'user' => $user,
                    'requests' => $group->count(),
                    'tokens' => (int) $group->sum('total_tokens'),
                    'cost' => (float) $group->sum('estimated_cost'),
                ];
            })
            ->sortByDesc('requests')
            ->take(8)
            ->values();

        $latestFailures = $logs
            ->where('status', 'failed')
            ->filter(fn (AiUsageLog $log) => filled($log->error_message))
            ->sortByDesc('created_at')
            ->take(6)
            ->values();

        $latestLogs = $logs->sortByDesc('created_at')->take(10)->values();

        $totalRequests = $logs->count();
        $successfulRequests = $logs->where('status', 'success')->count();
        $failedRequests = $logs->where('status', 'failed')->count();
        $latencyLogs = $logs->whereNotNull('latency_ms');
        $activeUsers = $logs->pluck('user_id')->filter()->unique()->count();
        $avgDailyRequests = $daily->avg('requests') ?: 0;
        $peakDay = $daily->sortByDesc('requests')->first();

        $trendCategories = $daily->pluck('label')->all();

        $requestTrendOptions = [
            'chart' => ['type' => 'areaspline'],
            'xAxis' => ['categories' => $trendCategories],
            'yAxis' => [
                ['title' => ['text' => null], 'min' => 0],
                ['title' => ['text' => null], 'min' => 0, 'max' => 100, 'opposite' => true],
            ],
            'tooltip' => ['shared' => true],
            'series' => [
                ['name' => __('Requests'), 'type' => 'areaspline', 'data' => $daily->pluck('requests')->all(), 'color' => '#0f766e'],
                ['name' => __('Success rate'), 'type' => 'spline', 'yAxis' => 1, 'data' => $daily->pluck('success_rate')->all(), 'color' => '#0ea5e9', 'tooltip' => ['valueSuffix' => '%']],
            ],
        ];

        $throughputOptions = [
            'chart' => ['type' => 'column'],
            'xAxis' => ['categories' => $trendCategories],
            'yAxis' => [
                ['title' => ['text' => null], 'min' => 0],
                ['title' => ['text' => null], 'min' => 0, 'opposite' => true],
            ],
            'tooltip' => ['shared' => true],
            'series' => [
                ['name' => __('Tokens'), 'type' => 'column', 'data' => $daily->pluck('tokens')->all(), 'color' => '#f59e0b'],
                ['name' => __('Cost'), 'type' => 'spline', 'yAxis' => 1, 'data' => $daily->pluck('cost')->map(fn ($value) => round($value, 4))->all(), 'color' => '#e11d48', 'tooltip' => ['valuePrefix' => '$']],
            ],
        ];

        $providerOptions = [
            'chart' => ['type' => 'pie'],
            'legend' => ['enabled' => true],
            'series' => [[
                'name' => __('Requests'),
                'data' => $providerBreakdown->take(6)->map(fn ($row) => ['name' => $row['label'], 'y' => $row['requests']])->values()->all(),
            ]],
        ];

        $capabilityOptions = [
            'chart' => ['type' => 'bar'],
            'xAxis' => ['categories' => $capabilityBreakdown->take(8)->pluck('label')->all()],
            'series' => [
                ['name' => __('Requests'), 'data' => $capabilityBreakdown->take(8)->pluck('requests')->all(), 'color' => '#14b8a6'],
                ['name' => __('Tokens'), 'data' => $capabilityBreakdown->take(8)->pluck('tokens')->all(), 'color' => '#38bdf8'],
            ],
        ];

        $modelOptions = [
            'chart' => ['type' => 'bar'],
            'xAxis' => ['categories' => $modelBreakdown->pluck('label')->all()],
            'series' => [
                ['name' => __('Requests'), 'data' => $modelBreakdown->pluck('requests')->all(), 'color' => '#6366f1'],
                ['name' => __('Cost'), 'data' => $modelBreakdown->pluck('cost')->map(fn ($value) => round($value, 4))->all(), 'color' => '#f97316', 'tooltip' => ['valuePrefix' => '$']],
            ],
        ];

        $hourlyOptions = [
            'chart' => ['type' => 'column'],
            'legend' => ['enabled' => false],
            'xAxis' => ['categories' => $hourlyDistribution->pluck('label')->all()],
            'series' => [[
                'name' => __('Requests'),
                'data' => $hourlyDistribution->pluck('requests')->all(),
                'color' => '#0f766e',
            ]],
        ];

        $weekdayOptions = [
            'chart' => ['type' => 'bar'],
            'xAxis' => ['categories' => $weekdayPerformance->pluck('label')->all()],
            'series' => [
                ['name' => __('Requests'), 'data' => $weekdayPerformance->pluck('requests')->all(), 'color' => '#0ea5e9'],
                ['name' => __('Tokens'), 'data' => $weekdayPerformance->pluck('tokens')->all(), 'color' => '#8b5cf6'],
            ],
        ];

        return view('adminai::livewire.report-index', [
            'rangeLabel' => $startDate->format('M d, Y').' - '.$endDate->format('M d, Y'),
            'metrics' => [
                'total_requests' => $totalRequests,
                'success_rate' => $totalRequests > 0 ? (int) round(($successfulRequests / $totalRequests) * 100) : 0,
                'successful_requests' => $successfulRequests,
                'failed_requests' => $failedRequests,
                'total_tokens' => (int) $logs->sum('total_tokens'),
                'estimated_cost' => (float) $logs->sum('estimated_cost'),
                'avg_latency' => $latencyLogs->count() > 0 ? (int) round($latencyLogs->avg('latency_ms')) : 0,
                'provider_count' => $providerBreakdown->count(),
                'model_count' => $logs->pluck('model')->filter()->unique()->count(),
                'active_users' => $activeUsers,
                'avg_daily_requests' => (float) $avgDailyRequests,
            ],
            'summaryCards' => [
                ['title' => __('Reporting range'), 'description' => $startDate->format('M d').' - '.$endDate->format('M d, Y'), 'variant' => 'info', 'icon' => 'fa-light fa-calendar-range'],
                ['title' => __('Active users'), 'description' => number_format($activeUsers).' '.__('accounts generated AI traffic'), 'variant' => 'success', 'icon' => 'fa-light fa-users-viewfinder'],
                ['title' => __('Average daily requests'), 'description' => number_format((float) $avgDailyRequests, 1).' '.__('requests per day'), 'variant' => 'warning', 'icon' => 'fa-light fa-chart-line-up'],
                ['title' => __('Peak day'), 'description' => (($peakDay['label'] ?? __('N/A')).' - '.number_format((int) ($peakDay['requests'] ?? 0)).' '.__('requests')), 'variant' => 'danger', 'icon' => 'fa-light fa-bolt'],
            ],
            'requestTrendOptions' => $requestTrendOptions,
            'throughputOptions' => $throughputOptions,
            'providerOptions' => $providerOptions,
            'capabilityOptions' => $capabilityOptions,
            'modelOptions' => $modelOptions,
            'hourlyOptions' => $hourlyOptions,
            'weekdayOptions' => $weekdayOptions,
            'providerBreakdown' => $providerBreakdown,
            'featureBreakdown' => $featureBreakdown,
            'statusMix' => $statusMix,
            'topUsers' => $topUsers,
            'latestFailures' => $latestFailures,
            'latestLogs' => $latestLogs,
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('AI Report'),
        ]);
    }

    protected function resolveRange(): array
    {
        $startDate = $this->parseDate($this->fromDate) ?? now()->subDays(29)->startOfDay();
        $endDate = $this->parseDate($this->toDate) ?? now()->endOfDay();

        if ($startDate->gt($endDate)) {
            [$startDate, $endDate] = [$endDate->copy()->startOfDay(), $startDate->copy()->endOfDay()];
        }

        return [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()];
    }

    protected function parseDate(?string $value): ?Carbon
    {
        if (! filled($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
