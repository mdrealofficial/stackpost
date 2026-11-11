<div class="space-y-6">
    <x-ui.page-hero
        :eyebrow="__('AI analytics')"
        :title="__('AI Report')"
        :description="__('Executive reporting workspace for traffic health, token throughput, provider concentration, model cost pressure, and recent AI runtime risk.')"
        :count="$metrics['total_requests']"
        icon="fa-light fa-chart-network"
    >
        <x-slot:actions>
            <x-ui.button href="{{ route('admin-ai-usage-logs.index') }}" variant="outline" wire:navigate>{{ __('Open usage logs') }}</x-ui.button>
            <x-ui.button href="{{ route('settings.ai') }}" wire:navigate>{{ __('Open AI settings') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-hero>

    <x-ui.metric-strip
        :items="[
            ['label' => __('Requests'), 'value' => number_format($metrics['total_requests']), 'description' => __('Provider count: :count', ['count' => number_format($metrics['provider_count'])]), 'progress' => 100, 'tone' => 'var(--theme-accent)'],
            ['label' => __('Success rate'), 'value' => $metrics['success_rate'].'%', 'description' => number_format($metrics['successful_requests']).' '.__('successful calls'), 'progress' => $metrics['success_rate'], 'tone' => 'var(--theme-success-color)'],
            ['label' => __('Tokens'), 'value' => number_format($metrics['total_tokens']), 'description' => __('Tracked model count: :count', ['count' => number_format($metrics['model_count'])]), 'progress' => $metrics['total_tokens'] > 0 ? 100 : 0, 'tone' => 'var(--theme-warning-color)'],
            ['label' => __('Cost'), 'value' => '$'.number_format($metrics['estimated_cost'], 4), 'description' => __('Failed requests: :count', ['count' => number_format($metrics['failed_requests'])]), 'progress' => $metrics['estimated_cost'] > 0 ? 100 : 0, 'tone' => 'var(--theme-danger-color)'],
            ['label' => __('Latency'), 'value' => number_format($metrics['avg_latency']).'ms', 'description' => __('Average latency for requests with timing data.'), 'progress' => $metrics['avg_latency'] > 0 ? 100 : 0, 'tone' => '#0ea5e9'],
            ['label' => __('Active users'), 'value' => number_format($metrics['active_users']), 'description' => __('Accounts with AI traffic in current range.'), 'progress' => $metrics['active_users'] > 0 ? 100 : 0, 'tone' => '#64748b'],
        ]"
        :show-icons="false"
        columns="md:grid-cols-2 xl:grid-cols-6"
    />

    <div class="relative">
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
                <x-ui.badge variant="neutral">{{ __('Range') }}: {{ $rangeLabel }}</x-ui.badge>
                <x-ui.badge variant="neutral">{{ __('Avg/day') }}: {{ number_format($metrics['avg_daily_requests'], 1) }}</x-ui.badge>
                <x-ui.badge variant="neutral">{{ __('Users') }}: {{ number_format($metrics['active_users']) }}</x-ui.badge>
            </x-slot:chips>
        </x-ui.filter-panel>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        @foreach ($summaryCards as $card)
            <x-ui.notice-card :variant="$card['variant']" :icon="$card['icon']" :title="$card['title']" :description="$card['description']" />
        @endforeach
    </div>

    <div class="grid gap-5 xl:grid-cols-2">
        <x-ui.chart
            :title="__('Request Momentum')"
            :description="__('Daily request volume and success rate across the selected reporting window.')"
            type="areaspline"
            :options="$requestTrendOptions"
            :height="330"
            :footer-stats="[
                ['label' => __('Requests'), 'value' => $metrics['total_requests']],
                ['label' => __('Success rate'), 'value' => $metrics['success_rate'], 'suffix' => '%'],
            ]"
        />

        <x-ui.chart
            :title="__('Token and Cost Throughput')"
            :description="__('Daily token burn paired with estimated cost to highlight AI spend pressure.')"
            type="column"
            :options="$throughputOptions"
            :height="330"
            :footer-stats="[
                ['label' => __('Tokens'), 'value' => $metrics['total_tokens']],
                ['label' => __('Cost'), 'value' => $metrics['estimated_cost'], 'decimals' => 4, 'suffix' => '$'],
            ]"
        />
    </div>

    <div class="grid gap-5 xl:grid-cols-[0.82fr_1.18fr]">
        <x-ui.chart
            :title="__('Provider Share')"
            :description="__('Traffic concentration across AI providers in the current reporting window.')"
            type="donut"
            :options="$providerOptions"
            :height="360"
            :legend="true"
        />

        <x-ui.section-card
            :title="__('Provider economics')"
            :description="__('Requests, token load, and cost by provider. Use this to spot concentration and expensive routing.')"
            header-class="py-4"
            title-class="mt-1 text-[1.15rem] tracking-[-0.025em]"
            description-class="mt-1 leading-6"
        >
            <div class="grid gap-3 px-6 py-6 sm:grid-cols-2 xl:grid-cols-3">
                @forelse ($providerBreakdown->take(6) as $provider)
                    <x-ui.surface-card padding="sm" accent="none">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ $provider['label'] }}</p>
                        <p class="mt-3 text-[1.9rem] font-semibold tracking-[-0.04em]" style="color: var(--theme-header-text-color);">{{ number_format($provider['requests']) }}</p>
                        <div class="mt-3 space-y-1 text-sm" style="color: var(--theme-muted-text-color);">
                            <p>{{ __('Tokens') }}: {{ number_format($provider['tokens']) }}</p>
                            <p>{{ __('Cost') }}: ${{ number_format($provider['cost'], 4) }}</p>
                        </div>
                    </x-ui.surface-card>
                @empty
                    <x-ui.empty icon="fa-light fa-network-wired" :title="__('No provider activity yet')" :description="__('Provider economics will appear once AI requests are recorded in the selected date range.')" />
                @endforelse
            </div>
        </x-ui.section-card>
    </div>

    <div class="grid gap-5 xl:grid-cols-2">
        <x-ui.chart
            :title="__('Capability Demand')"
            :description="__('Compare AI capability demand by requests and token load.')"
            type="bar"
            :options="$capabilityOptions"
            :height="360"
        />

        <x-ui.chart
            :title="__('Model Concentration')"
            :description="__('Top models ranked by request volume and estimated cost.')"
            type="bar"
            :options="$modelOptions"
            :height="360"
        />
    </div>

    <div class="grid gap-5 xl:grid-cols-2">
        <x-ui.chart
            :title="__('Hourly Request Distribution')"
            :description="__('Shows when AI traffic clusters during the day.')"
            type="column"
            :options="$hourlyOptions"
            :height="320"
        />

        <x-ui.chart
            :title="__('Weekday Traffic Pattern')"
            :description="__('Weekly operating rhythm by requests and tokens.')"
            type="bar"
            :options="$weekdayOptions"
            :height="320"
        />
    </div>

    <div class="grid gap-5 xl:grid-cols-[0.9fr_1.1fr]">
        <x-ui.section-card
            :title="__('Status mix and feature pressure')"
            :description="__('Operational status split plus the most active AI features/routes.')"
            header-class="py-4"
            title-class="mt-1 text-[1.15rem] tracking-[-0.025em]"
            description-class="mt-1 leading-6"
        >
            <div class="grid gap-4 px-6 py-6">
                <div class="flex flex-wrap gap-3">
                    @forelse ($statusMix as $status)
                        <div class="min-w-[9rem] rounded-[1rem] border px-4 py-4" style="border-color: rgba(var(--theme-border-color-rgb),0.58); background-color: rgba(var(--theme-surface-base-rgb,255,255,255),0.82);">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.16em]" style="color: {{ $status['color'] }};">{{ $status['label'] }}</p>
                            <p class="mt-2 text-2xl font-semibold" style="color: var(--theme-header-text-color);">{{ number_format($status['value']) }}</p>
                        </div>
                    @empty
                        <x-ui.empty icon="fa-light fa-circle-nodes" :title="__('No status activity')" :description="__('Status distribution appears once AI usage is logged.')" />
                    @endforelse
                </div>

                <div class="space-y-3">
                    @forelse ($featureBreakdown as $feature)
                        <div class="rounded-[1rem] border px-4 py-4" style="border-color: rgba(var(--theme-border-color-rgb),0.58); background-color: rgba(var(--theme-surface-base-rgb,255,255,255),0.82);">
                            <div class="flex items-center justify-between gap-3">
                                <p class="min-w-0 truncate text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $feature['label'] }}</p>
                                <x-ui.badge variant="neutral">{{ number_format($feature['value']) }}</x-ui.badge>
                            </div>
                        </div>
                    @empty
                        <x-ui.empty icon="fa-light fa-layer-group" :title="__('No feature pressure yet')" :description="__('Feature and route concentration will populate after AI workflows run.')" />
                    @endforelse
                </div>
            </div>
        </x-ui.section-card>

        <x-ui.datatable-shell :title="__('Top users')" :info="__('Accounts generating the most AI request volume in the selected range.')" header-class="py-4" title-class="mt-1 text-[1.15rem] tracking-[-0.025em]" description-class="mt-1 leading-6">
            <x-ui.table class="rounded-none border-0 shadow-none">
                <x-ui.table-head>
                    <x-ui.table-cell head>{{ __('User') }}</x-ui.table-cell>
                    <x-ui.table-cell head>{{ __('Requests') }}</x-ui.table-cell>
                    <x-ui.table-cell head>{{ __('Tokens') }}</x-ui.table-cell>
                    <x-ui.table-cell head>{{ __('Cost') }}</x-ui.table-cell>
                </x-ui.table-head>
                <x-ui.table-body>
                    @forelse ($topUsers as $row)
                        <x-ui.table-row>
                            <x-ui.table-cell><div class="space-y-1"><p class="font-semibold" style="color: var(--theme-header-text-color);">{{ $row['user']->name }}</p><p class="text-xs" style="color: var(--theme-muted-text-color);">{{ '@'.$row['user']->username }}</p></div></x-ui.table-cell>
                            <x-ui.table-cell><x-ui.badge variant="primary">{{ number_format($row['requests']) }}</x-ui.badge></x-ui.table-cell>
                            <x-ui.table-cell>{{ number_format($row['tokens']) }}</x-ui.table-cell>
                            <x-ui.table-cell>${{ number_format($row['cost'], 4) }}</x-ui.table-cell>
                        </x-ui.table-row>
                    @empty
                        <x-ui.table-row>
                            <x-ui.table-cell colspan="4" class="py-10"><x-ui.empty icon="fa-light fa-users" :title="__('No top users yet')" :description="__('User ranking will appear after accounts start generating AI traffic.')" /></x-ui.table-cell>
                        </x-ui.table-row>
                    @endforelse
                </x-ui.table-body>
            </x-ui.table>
        </x-ui.datatable-shell>
    </div>

    <div class="grid gap-5 xl:grid-cols-[0.92fr_1.08fr]">
        <x-ui.section-card
            :title="__('Latest failures')"
            :description="__('Most recent failed AI requests with surfaced error messages for operator review.')"
            header-class="py-4"
            title-class="mt-1 text-[1.15rem] tracking-[-0.025em]"
            description-class="mt-1 leading-6"
        >
            <div class="space-y-3 px-6 py-6">
                @forelse ($latestFailures as $log)
                    <div class="rounded-[1rem] border px-4 py-4" style="border-color: rgba(var(--theme-danger-color-rgb),0.18); background-color: rgba(var(--theme-danger-color-rgb),0.05);">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ strtoupper($log->provider) }} · {{ $log->model ?: __('Unknown model') }}</p>
                                <p class="mt-1 text-xs" style="color: var(--theme-muted-text-color);">{{ $log->created_at?->format('Y-m-d H:i:s') }} · {{ $log->user?->email ?: __('No user attached') }}</p>
                            </div>
                            <x-ui.badge variant="danger">{{ strtoupper($log->status) }}</x-ui.badge>
                        </div>
                        <p class="mt-3 text-sm leading-6" style="color: var(--theme-danger-color);">{{ $log->error_message }}</p>
                    </div>
                @empty
                    <x-ui.empty icon="fa-light fa-shield-check" :title="__('No recent failures')" :description="__('Failed AI calls with captured error messages will appear here.')" />
                @endforelse
            </div>
        </x-ui.section-card>

        <x-ui.datatable-shell :title="__('Latest AI requests')" :info="__('Most recent provider calls including model, capability, token usage, and health.')"
            header-class="py-4" title-class="mt-1 text-[1.15rem] tracking-[-0.025em]" description-class="mt-1 leading-6">
            <x-ui.table class="rounded-none border-0 shadow-none">
                <x-ui.table-head>
                    <x-ui.table-cell head>{{ __('User') }}</x-ui.table-cell>
                    <x-ui.table-cell head>{{ __('Provider') }}</x-ui.table-cell>
                    <x-ui.table-cell head>{{ __('Capability') }}</x-ui.table-cell>
                    <x-ui.table-cell head>{{ __('Usage') }}</x-ui.table-cell>
                    <x-ui.table-cell head>{{ __('Date') }}</x-ui.table-cell>
                </x-ui.table-head>
                <x-ui.table-body>
                    @forelse ($latestLogs as $log)
                        <x-ui.table-row>
                            <x-ui.table-cell><div class="space-y-1"><p class="font-semibold" style="color: var(--theme-header-text-color);">{{ $log->user?->name ?? __('System') }}</p><p class="text-xs" style="color: var(--theme-muted-text-color);">{{ $log->user ? '@'.$log->user->username : __('No user attached') }}</p></div></x-ui.table-cell>
                            <x-ui.table-cell><div class="space-y-1"><x-ui.badge variant="neutral">{{ strtoupper($log->provider) }}</x-ui.badge><p class="text-xs" style="color: var(--theme-muted-text-color);">{{ $log->model }}</p></div></x-ui.table-cell>
                            <x-ui.table-cell><div class="space-y-1"><p class="font-semibold" style="color: var(--theme-header-text-color);">{{ ucfirst($log->capability) }}</p><p class="text-xs" style="color: var(--theme-muted-text-color);">{{ collect([$log->feature, $log->route_name])->filter()->implode(' | ') ?: __('No feature metadata') }}</p></div></x-ui.table-cell>
                            <x-ui.table-cell><p class="font-medium" style="color: var(--theme-header-text-color);">{{ number_format((int) ($log->total_tokens ?? 0)) }} {{ __('tokens') }}</p><p class="text-xs" style="color: var(--theme-muted-text-color);">${{ number_format((float) ($log->estimated_cost ?? 0), 4) }} · {{ $log->latency_ms !== null ? number_format((int) $log->latency_ms).'ms' : __('No latency') }}</p></x-ui.table-cell>
                            <x-ui.table-cell><div class="space-y-1"><x-ui.badge :variant="$log->status === 'success' ? 'success' : ($log->status === 'failed' ? 'danger' : 'neutral')">{{ strtoupper($log->status) }}</x-ui.badge><p class="text-xs" style="color: var(--theme-muted-text-color);">{{ $log->created_at?->format('Y-m-d H:i') }}</p></div></x-ui.table-cell>
                        </x-ui.table-row>
                    @empty
                        <x-ui.table-row>
                            <x-ui.table-cell colspan="5" class="py-10"><x-ui.empty icon="fa-light fa-sparkles" :title="__('No AI usage records found')" :description="__('This table will populate once AI traffic is recorded in the selected date range.')" /></x-ui.table-cell>
                        </x-ui.table-row>
                    @endforelse
                </x-ui.table-body>
            </x-ui.table>
        </x-ui.datatable-shell>
    </div>
</div>
