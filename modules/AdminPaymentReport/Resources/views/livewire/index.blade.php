@php
    $hasLatestPayments = $latestPayments->isNotEmpty();

    $dailyCategories = collect($incomeByDay)->map(fn ($day) => $day['label'])->all();
    $dailyRevenueSeries = collect($incomeByDay)->map(fn ($day) => (float) $day['total'])->all();
    $dailyTxSeries = collect($incomeByDay)->map(fn ($day) => (int) $day['transactions'])->all();

    $hourlyCategories = collect($hourlyHeatmap)->map(fn ($hour) => $hour['label'])->all();
    $hourlyRevenueSeries = collect($hourlyHeatmap)->map(fn ($hour) => (float) $hour['total'])->all();

    $weekdayCategories = collect($weekdayPerformance)->map(fn ($day) => $day['label'])->all();
    $weekdayRevenueSeries = collect($weekdayPerformance)->map(fn ($day) => (float) $day['total'])->all();
    $weekdayTxSeries = collect($weekdayPerformance)->map(fn ($day) => (int) $day['transactions'])->all();

    $gatewayCategories = $gatewayBreakdown->map(fn ($gateway) => $gateway['name'])->all();
    $gatewayRevenueSeries = $gatewayBreakdown->map(fn ($gateway) => (float) $gateway['total'])->all();
    $gatewayTxSeries = $gatewayBreakdown->map(fn ($gateway) => (int) $gateway['transactions'])->all();

    $planCategories = collect($incomeByPlan)->map(fn ($plan) => $plan['name'])->all();
    $planRevenueSeries = collect($incomeByPlan)->map(fn ($plan) => (float) $plan['total'])->all();

    $statusDonutSeries = [[
        'name' => __('Transactions'),
        'data' => $statusMix->map(fn ($status) => [
            'name' => $status['label'],
            'y' => (int) $status['transactions'],
            'color' => $status['tone'],
        ])->values()->all(),
    ]];

    $averageDailyIncome = collect($incomeByDay)->avg('total') ?: 0;
    $peakDay = collect($incomeByDay)->sortByDesc('total')->first();
    $peakHour = collect($hourlyHeatmap)->sortByDesc('total')->first();
    $bestWeekday = collect($weekdayPerformance)->sortByDesc('total')->first();

    $reportMetricCards = [
        [
            'label' => __('Total income'),
            'value' => '$'.number_format($info['total_income'], 2),
            'description' => __('Gross successful payment value in the selected range.'),
            'tone' => 'var(--theme-accent)',
            'progress' => 100,
        ],
        [
            'label' => __('Net income'),
            'value' => '$'.number_format($info['net_income'], 2),
            'description' => __('Successful income minus refunded amount.'),
            'tone' => '#0ea5e9',
            'progress' => 100,
        ],
        [
            'label' => __('Transactions'),
            'value' => number_format($info['total_transactions']),
            'description' => __('All successful, refunded, and pending payment records.'),
            'tone' => '#6366f1',
            'progress' => 100,
        ],
        [
            'label' => __('AOV'),
            'value' => '$'.number_format($info['avg_order_value'], 2),
            'description' => __('Average order value for successful payments.'),
            'tone' => '#10b981',
            'progress' => 100,
        ],
        [
            'label' => __('Refund rate'),
            'value' => number_format($info['refund_rate'], 2).'%',
            'description' => __('Refunded transaction share in current period.'),
            'tone' => '#f43f5e',
            'progress' => min(100, max(8, (int) round($info['refund_rate']))),
        ],
        [
            'label' => __('Top gateway'),
            'value' => $info['top_gateway'],
            'description' => __('Gateway with highest successful revenue in this range.'),
            'tone' => '#64748b',
            'progress' => 100,
        ],
    ];

    $dailyRevenueOptions = [
        'chart' => ['type' => 'areaspline'],
        'legend' => ['enabled' => false],
        'xAxis' => ['categories' => $dailyCategories],
        'series' => [[
            'name' => __('Revenue'),
            'data' => $dailyRevenueSeries,
        ]],
        'tooltip' => ['pointFormat' => '<b>${point.y:.2f}</b>'],
    ];

    $dailyTxOptions = [
        'chart' => ['type' => 'column'],
        'legend' => ['enabled' => false],
        'xAxis' => ['categories' => $dailyCategories],
        'series' => [[
            'name' => __('Transactions'),
            'data' => $dailyTxSeries,
        ]],
        'tooltip' => ['pointFormat' => '<b>{point.y}</b>'],
    ];

    $hourlyRevenueOptions = [
        'chart' => ['type' => 'column'],
        'legend' => ['enabled' => false],
        'xAxis' => ['categories' => $hourlyCategories],
        'series' => [[
            'name' => __('Revenue'),
            'data' => $hourlyRevenueSeries,
        ]],
        'tooltip' => ['pointFormat' => '<b>${point.y:.2f}</b>'],
    ];

    $weekdayOptions = [
        'chart' => ['type' => 'bar'],
        'xAxis' => ['categories' => $weekdayCategories],
        'series' => [
            ['name' => __('Revenue'), 'data' => $weekdayRevenueSeries],
            ['name' => __('Transactions'), 'data' => $weekdayTxSeries],
        ],
    ];

    $gatewayOptions = [
        'chart' => ['type' => 'bar'],
        'xAxis' => ['categories' => $gatewayCategories],
        'series' => [
            ['name' => __('Revenue'), 'data' => $gatewayRevenueSeries],
            ['name' => __('Transactions'), 'data' => $gatewayTxSeries],
        ],
    ];

    $planRevenueOptions = [
        'chart' => ['type' => 'column'],
        'legend' => ['enabled' => false],
        'xAxis' => ['categories' => $planCategories],
        'series' => [[
            'name' => __('Revenue'),
            'data' => $planRevenueSeries,
        ]],
        'tooltip' => ['pointFormat' => '<b>${point.y:.2f}</b>'],
    ];
@endphp

<div class="space-y-6">
    <x-ui.page-hero
        :eyebrow="__('Finance')"
        :title="__('Payment Report')"
        :description="__('Advanced payment analytics with redesigned chart suite and richer finance reporting.')"
        icon="fa-light fa-chart-network"
    >
        <x-slot:actions>
            <x-ui.notice-card
                variant="info"
                icon="fa-light fa-calendar-range"
                :title="__('Reporting range')"
                :description="$rangeLabel"
                class="min-w-[18rem]"
            />
        </x-slot:actions>
    </x-ui.page-hero>

    <x-ui.metric-strip :items="$reportMetricCards" :show-icons="false" columns="md:grid-cols-2 xl:grid-cols-6" />

    <div class="relative z-[200]">
        <x-ui.filter-panel fields-class="xl:grid-cols-[220px_220px_auto]" :overflow-visible="true">
            <x-slot:fields>
                <x-ui.date-picker wire:model.live="fromDate" name="from_date" :label="__('From')" :value="$fromDate" :placeholder="__('Choose start date')" />
                <x-ui.date-picker wire:model.live="toDate" name="to_date" :label="__('To')" :value="$toDate" :placeholder="__('Choose end date')" />
            </x-slot:fields>

            <x-slot:actions>
                <x-ui.button type="button" wire:click="$refresh">{{ __('Apply') }}</x-ui.button>
                <x-ui.button type="button" variant="outline" wire:click="resetFilters">{{ __('Reset') }}</x-ui.button>
            </x-slot:actions>

            <x-slot:chips>
                <span class="inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-medium" style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color);">
                    {{ __('Range') }}: {{ $rangeLabel }}
                </span>
                <span class="inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-medium" style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color);">
                    {{ __('Income growth') }}: {{ $info['income_growth'] >= 0 ? '+' : '' }}{{ $info['income_growth'] }}%
                </span>
                <span class="inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-medium" style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color);">
                    {{ __('Active days') }}: {{ number_format($info['active_days']) }}
                </span>
            </x-slot:chips>
        </x-ui.filter-panel>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <x-ui.notice-card variant="info" icon="fa-light fa-chart-line" :title="__('Average daily income')" :description="'$'.number_format($averageDailyIncome, 2)" />
        <x-ui.notice-card variant="success" icon="fa-light fa-trophy-star" :title="__('Peak day')" :description="($peakDay['label'] ?? __('N/A')).' · $'.number_format((float) ($peakDay['total'] ?? 0), 2)" />
        <x-ui.notice-card variant="warning" icon="fa-light fa-clock" :title="__('Peak hour')" :description="($peakHour['label'] ?? __('N/A')).' · $'.number_format((float) ($peakHour['total'] ?? 0), 2)" />
        <x-ui.notice-card variant="success" icon="fa-light fa-calendar-star" :title="__('Best weekday')" :description="($bestWeekday['label'] ?? __('N/A')).' · $'.number_format((float) ($bestWeekday['total'] ?? 0), 2)" />
    </div>

    <div class="grid gap-5 xl:grid-cols-2">
        <x-ui.chart
            :title="__('Revenue Momentum')"
            :description="__('Daily successful revenue trend in selected range.')"
            type="areaspline"
            :options="$dailyRevenueOptions"
            :height="320"
        />

        <x-ui.chart
            :title="__('Daily Transaction Velocity')"
            :description="__('Transaction count trend by day.')"
            type="column"
            :options="$dailyTxOptions"
            :height="320"
        />
    </div>

    <div class="grid gap-5 xl:grid-cols-[0.72fr_1.28fr]">
        <x-ui.chart
            :title="__('Payment Status Mix')"
            :description="__('Distribution across success, refunded, pending.')"
            type="donut"
            :series="$statusDonutSeries"
            :height="330"
            :legend="true"
        />

        <x-ui.chart
            :title="__('Gateway Performance')"
            :description="__('Revenue and transaction volume by gateway.')"
            type="bar"
            :options="$gatewayOptions"
            :height="330"
        />
    </div>

    <div class="grid gap-5 xl:grid-cols-2">
        <x-ui.chart
            :title="__('Hourly Revenue Pattern')"
            :description="__('Successful revenue concentration by hour.')"
            type="column"
            :options="$hourlyRevenueOptions"
            :height="320"
        />

        <x-ui.chart
            :title="__('Weekday Revenue Pattern')"
            :description="__('Revenue and volume split by weekday.')"
            type="bar"
            :options="$weekdayOptions"
            :height="320"
        />
    </div>

    <x-ui.chart
        :title="__('Top Plans by Revenue')"
        :description="__('Revenue contribution across plans.')"
        type="column"
        :options="$planRevenueOptions"
        :height="340"
    />

    <x-ui.section-card
        :title="__('Latest Payments')"
        :description="__('Most recent successful transactions recorded in payment history.')"
        header-class="py-4"
        title-class="mt-1 text-[1.15rem] tracking-[-0.025em]"
        description-class="mt-1 leading-6"
    >
        @if ($hasLatestPayments)
            <div class="grid gap-3 px-6 py-6 lg:grid-cols-2">
                @foreach ($latestPayments as $payment)
                    <x-ui.surface-card padding="sm" accent="none">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $payment->user?->name ?: __('Unknown user') }}</p>
                                <p class="truncate text-xs" style="color: var(--theme-muted-text-color);">{{ $payment->user?->email ?: __('No email') }}</p>
                                <p class="mt-2 text-xs" style="color: var(--theme-muted-text-color);">{{ $payment->plan?->name ?: __('No plan') }} / {{ $payment->from ?: __('Unknown gateway') }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $payment->currency ?: 'USD' }} {{ number_format((float) $payment->amount, 2) }}</p>
                                <p class="mt-1 text-xs" style="color: var(--theme-muted-text-color);">{{ $payment->createdAtFormatted() ?: __('N/A') }}</p>
                            </div>
                        </div>
                    </x-ui.surface-card>
                @endforeach
            </div>
        @else
            <div class="px-6 py-8">
                <x-ui.empty icon="fa-light fa-receipt" :title="__('No recent payments yet')" :description="__('Latest successful transactions will show up here once payment history starts receiving data.')" />
            </div>
        @endif
    </x-ui.section-card>
</div>

