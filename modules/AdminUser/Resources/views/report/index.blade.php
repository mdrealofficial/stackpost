@component(theme_view('layouts.app', 'app'), ['title' => __('User Report')])
    @php
        $reportMetricCards = [
            ['label' => __('Total users'), 'value' => number_format($metrics['total_users']), 'description' => __('All identities currently stored in the workspace.'), 'tone' => 'var(--theme-accent)', 'progress' => 100],
            ['label' => __('30-day growth'), 'value' => ($metrics['growth_rate'] >= 0 ? '+' : '').$metrics['growth_rate'].'%', 'description' => __('New users in the last 30 days: :count', ['count' => number_format($metrics['new_users_last_30_days'])]), 'tone' => '#0ea5e9', 'progress' => min(100, max(8, abs((int) $metrics['growth_rate'])))],
            ['label' => __('Verified email'), 'value' => $metrics['verification_rate'].'%', 'description' => __(':count verified accounts', ['count' => number_format($metrics['verified_users'])]), 'tone' => '#10b981', 'progress' => max(8, (int) $metrics['verification_rate'])],
            ['label' => __('Two-factor'), 'value' => $metrics['two_factor_rate'].'%', 'description' => __(':count secured accounts', ['count' => number_format($metrics['two_factor_users'])]), 'tone' => '#f59e0b', 'progress' => max(8, (int) $metrics['two_factor_rate'])],
            ['label' => __('Users with plan'), 'value' => $metrics['active_plan_rate'].'%', 'description' => __(':count attached to an active or assigned plan', ['count' => number_format($metrics['users_with_plan'])]), 'tone' => '#8b5cf6', 'progress' => max(8, (int) $metrics['active_plan_rate'])],
            ['label' => __('Teams'), 'value' => number_format($metrics['team_count']), 'description' => __('Average team size: :size', ['size' => $metrics['avg_team_size']]), 'tone' => '#64748b', 'progress' => $metrics['team_count'] > 0 ? 100 : 8],
        ];

        $monthlySignupOptions = [
            'chart' => ['type' => 'areaspline'],
            'legend' => ['enabled' => false],
            'xAxis' => ['categories' => collect($monthlySignups)->pluck('label')->all()],
            'series' => [[
                'name' => __('Users'),
                'data' => collect($monthlySignups)->pluck('value')->map(fn ($value) => (int) $value)->all(),
            ]],
            'tooltip' => ['pointFormat' => '<b>{point.y}</b>'],
        ];

        $dailySignupOptions = [
            'chart' => ['type' => 'column'],
            'legend' => ['enabled' => false],
            'xAxis' => ['categories' => collect($recentDailySignups)->pluck('label')->all()],
            'series' => [[
                'name' => __('Daily signups'),
                'data' => collect($recentDailySignups)->pluck('value')->map(fn ($value) => (int) $value)->all(),
            ]],
            'tooltip' => ['pointFormat' => '<b>{point.y}</b>'],
        ];

        $roleMixOptions = [
            'chart' => ['type' => 'bar'],
            'legend' => ['enabled' => false],
            'xAxis' => ['categories' => collect($roleBreakdown)->take(7)->pluck('label')->all()],
            'series' => [[
                'name' => __('Users'),
                'data' => collect($roleBreakdown)->take(7)->pluck('value')->map(fn ($value) => (int) $value)->all(),
            ]],
        ];

        $planMixOptions = [
            'chart' => ['type' => 'bar'],
            'legend' => ['enabled' => false],
            'xAxis' => ['categories' => collect($planBreakdown)->take(7)->pluck('label')->all()],
            'series' => [[
                'name' => __('Users'),
                'data' => collect($planBreakdown)->take(7)->pluck('value')->map(fn ($value) => (int) $value)->all(),
            ]],
        ];

        $localeOptions = [
            'chart' => ['type' => 'column'],
            'legend' => ['enabled' => false],
            'xAxis' => ['categories' => collect($localeBreakdown)->take(8)->pluck('label')->all()],
            'series' => [[
                'name' => __('Users'),
                'data' => collect($localeBreakdown)->take(8)->pluck('value')->map(fn ($value) => (int) $value)->all(),
            ]],
        ];

        $securitySeries = [[
            'name' => __('Accounts'),
            'data' => collect($securitySegments)->values()->all(),
        ]];

    @endphp

    <div class="mx-auto max-w-[92rem] space-y-6">
        <x-ui.page-hero
            :eyebrow="__('Reporting workspace')"
            :title="__('User Report')"
            :description="__('Professional admin overview for user growth, account posture, plan coverage, and follow-up risk.')"
            icon="fa-light fa-chart-mixed"
        >
            <x-slot:actions>
                <x-ui.button href="{{ route('admin-users.index') }}" variant="outline" wire:navigate>{{ __('Open users') }}</x-ui.button>
                <x-ui.button href="{{ route('admin-user-teams.index') }}" wire:navigate>{{ __('Open teams') }}</x-ui.button>
            </x-slot:actions>
        </x-ui.page-hero>

        <x-ui.metric-strip :items="$reportMetricCards" :show-icons="false" columns="md:grid-cols-2 xl:grid-cols-6" />

        <x-ui.card class="overflow-hidden" style="border-color: rgba(var(--theme-border-color-rgb),0.56); background: linear-gradient(180deg, color-mix(in srgb, var(--theme-surface-base) 94%, white 6%) 0%, color-mix(in srgb, var(--theme-surface-soft) 88%, var(--theme-surface-base) 12%) 100%);">
            <div class="grid gap-4 p-5 xl:grid-cols-[1.12fr_0.88fr]">
                <div class="rounded-[1.35rem] border px-5 py-5" style="border-color: rgba(var(--theme-border-color-rgb),0.48); background: radial-gradient(circle at top right, rgba(var(--theme-accent-rgb),0.12), transparent 36%), linear-gradient(145deg, color-mix(in srgb, var(--theme-surface-base) 96%, white 4%) 0%, color-mix(in srgb, var(--theme-surface-soft) 90%, var(--theme-surface-base) 10%) 100%);">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.2em]" style="color: var(--theme-muted-text-color);">{{ __('Executive summary') }}</p>
                            <h2 class="mt-4 text-[3rem] font-semibold tracking-[-0.07em]" style="color: var(--theme-header-text-color);">{{ number_format($metrics['total_users']) }}</h2>
                            <p class="mt-2 text-lg font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">{{ __('User identities under administration') }}</p>
                        </div>
                        <div class="rounded-full px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.16em]" style="background: rgba(var(--theme-accent-rgb),0.1); color: var(--theme-accent);">
                            {{ __('Admin pulse') }}
                        </div>
                    </div>

                    <p class="mt-5 max-w-[34rem] text-sm leading-7" style="color: var(--theme-muted-text-color);">{{ __('This workspace highlights acquisition momentum, security hygiene, plan attachment, and operational hotspots across the admin user base.') }}</p>

                    <div class="mt-6 grid gap-3 sm:grid-cols-3">
                        @foreach ([
                            ['label' => __('Admin access'), 'value' => number_format($metrics['admin_users'])],
                            ['label' => __('Roles'), 'value' => number_format($roleCount)],
                            ['label' => __('Plans'), 'value' => number_format($planCount)],
                        ] as $mini)
                                <div class="rounded-[1rem] border px-4 py-3.5" style="border-color: rgba(var(--theme-border-color-rgb),0.42); background: color-mix(in srgb, var(--theme-surface-base) 84%, transparent);">
                                    <p class="text-[10px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ $mini['label'] }}</p>
                                    <p class="mt-2 text-[1.9rem] font-semibold tracking-[-0.05em]" style="color: var(--theme-header-text-color);">{{ $mini['value'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ([
                        ['label' => __('New this month'), 'value' => number_format($metrics['new_users_this_month']), 'meta' => __('Accounts created during the current calendar month')],
                        ['label' => __('Previous 30 days'), 'value' => number_format($metrics['new_users_previous_30_days']), 'meta' => __('Baseline to compare current acquisition pace')],
                        ['label' => __('Without plan'), 'value' => number_format($metrics['users_without_plan']), 'meta' => __('Accounts not currently attached to a plan')],
                        ['label' => __('Expired plans'), 'value' => number_format($metrics['expired_users']), 'meta' => __('Accounts whose assigned plan already expired')],
                    ] as $stat)
                        <div class="rounded-[1.1rem] border px-4 py-4" style="border-color: rgba(var(--theme-border-color-rgb),0.46); background: linear-gradient(180deg, color-mix(in srgb, var(--theme-surface-base) 94%, white 6%) 0%, color-mix(in srgb, var(--theme-surface-soft) 86%, var(--theme-surface-base) 14%) 100%);">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.16em]" style="color: var(--theme-muted-text-color);">{{ $stat['label'] }}</p>
                            <p class="mt-3 text-[2rem] font-semibold tracking-[-0.05em]" style="color: var(--theme-header-text-color);">{{ $stat['value'] }}</p>
                            <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ $stat['meta'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </x-ui.card>

        <x-ui.card class="space-y-5" style="border-color: rgba(var(--theme-border-color-rgb),0.56); background-color: rgba(var(--theme-surface-base-rgb,255,255,255), 0.98);">
                <div>
                    <p class="text-[15px] font-semibold tracking-[-0.02em]" style="color: var(--theme-header-text-color);">{{ __('Security posture') }}</p>
                    <p class="mt-1 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Account protection mix across verification and two-factor coverage.') }}</p>
                </div>

                <x-ui.chart
                    :title="null"
                    :description="null"
                    type="donut"
                    :series="$securitySeries"
                    :height="420"
                    :legend="true"
                    class="border-0 bg-transparent p-0 shadow-none"
                />

                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ([
                        ['label' => __('Missing verification'), 'value' => $metrics['users_missing_verification']],
                        ['label' => __('Missing 2FA'), 'value' => $metrics['users_missing_two_factor']],
                        ['label' => __('Without role'), 'value' => $metrics['users_without_role']],
                        ['label' => __('Expiring in 7d'), 'value' => $metrics['expiring_soon_users']],
                    ] as $item)
                        <div class="rounded-[1rem] border px-5 py-4" style="border-color: rgba(var(--theme-border-color-rgb),0.42); background: rgba(var(--theme-surface-base-rgb,255,255,255),0.72);">
                            <p class="text-sm" style="color: var(--theme-muted-text-color);">{{ $item['label'] }}</p>
                            <p class="mt-2 text-[2rem] font-semibold tracking-[-0.05em]" style="color: var(--theme-header-text-color);">{{ number_format((int) $item['value']) }}</p>
                        </div>
                    @endforeach
                </div>
        </x-ui.card>

        <div class="grid gap-5 xl:grid-cols-2">
            <x-ui.chart
                :title="__('Monthly signup trend')"
                :description="__('Rolling 12-month account creation trend.')"
                type="areaspline"
                :options="$monthlySignupOptions"
                :height="340"
            />

            <x-ui.chart
                :title="__('Daily signup velocity')"
                :description="__('Last 30 days of signup activity for operational monitoring.')"
                type="column"
                :options="$dailySignupOptions"
                :height="340"
            />
        </div>

        <div class="grid gap-5 xl:grid-cols-3">
            <x-ui.chart
                :title="__('Role distribution')"
                :description="__('How users are split across access profiles.')"
                type="bar"
                :options="$roleMixOptions"
                :height="320"
            />

            <x-ui.chart
                :title="__('Plan distribution')"
                :description="__('Plan attachment mix across the current user base.')"
                type="bar"
                :options="$planMixOptions"
                :height="320"
            />

            <x-ui.chart
                :title="__('Locale mix')"
                :description="__('Top account locales currently stored on users.')"
                type="column"
                :options="$localeOptions"
                :height="320"
            />
        </div>

        <div class="grid gap-5 xl:grid-cols-[1.18fr_0.82fr]">
            <x-ui.section-card
                :title="__('Users needing attention')"
                :description="__('Accounts missing verification, missing 2FA, missing plan, or approaching plan expiry.')"
                header-class="py-4"
                title-class="mt-1 text-[1.15rem] tracking-[-0.025em]"
                description-class="mt-1 leading-6"
            >
                <div class="border-b px-6 py-4" style="border-color: rgba(var(--theme-border-color-rgb),0.56);">
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        @foreach ([
                            ['label' => __('Missing verification'), 'value' => $metrics['users_missing_verification']],
                            ['label' => __('Missing 2FA'), 'value' => $metrics['users_missing_two_factor']],
                            ['label' => __('Without plan'), 'value' => $metrics['users_without_plan']],
                            ['label' => __('Expiring soon'), 'value' => $metrics['expiring_soon_users']],
                        ] as $summary)
                            <div class="rounded-[1rem] border px-4 py-3.5" style="border-color: rgba(var(--theme-border-color-rgb),0.42); background: rgba(var(--theme-surface-base-rgb,255,255,255),0.72);">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.16em]" style="color: var(--theme-muted-text-color);">{{ $summary['label'] }}</p>
                                <p class="mt-2 text-[1.55rem] font-semibold tracking-[-0.04em]" style="color: var(--theme-header-text-color);">{{ number_format((int) $summary['value']) }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="grid gap-3 px-6 py-6 md:grid-cols-2">
                    @forelse ($attentionUsers as $user)
                        <x-ui.surface-card padding="sm" accent="none" class="rounded-[1.2rem]">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $user->name }}</p>
                                    <p class="truncate text-xs" style="color: var(--theme-muted-text-color);">{{ '@'.$user->username }} · {{ $user->email }}</p>
                                </div>
                                <x-ui.badge :variant="$user->plan_id ? 'neutral' : 'warning'">{{ $user->plan?->name ?: __('No plan') }}</x-ui.badge>
                            </div>

                            <div class="mt-3 flex flex-wrap gap-2">
                                <x-ui.badge :variant="$user->email_verified_at ? 'success' : 'warning'">{{ $user->email_verified_at ? __('Verified') : __('Unverified') }}</x-ui.badge>
                                <x-ui.badge :variant="$user->two_factor_confirmed_at ? 'primary' : 'warning'">{{ $user->two_factor_confirmed_at ? __('2FA on') : __('2FA off') }}</x-ui.badge>
                                <x-ui.badge variant="neutral">{{ $user->isSuperAdmin() ? __('Super admin') : ($user->role?->name ?: __('No role')) }}</x-ui.badge>
                            </div>

                            <div class="mt-4 grid gap-2 text-xs" style="color: var(--theme-muted-text-color);">
                                <p>{{ __('Created') }}: {{ $user->created_at?->format('Y-m-d') ?: __('N/A') }}</p>
                                <p>{{ __('Teams') }}: {{ number_format((int) $user->teams_count) }}</p>
                                <p>{{ __('Plan expiry') }}: {{ $user->plan_expires_at?->format('Y-m-d') ?: __('Not scheduled') }}</p>
                            </div>
                        </x-ui.surface-card>
                    @empty
                        <div class="md:col-span-2">
                            <x-ui.empty icon="fa-light fa-shield-check" :title="__('No urgent user risks found.')" :description="__('High-priority user follow-up items will appear here when gaps are detected.')" />
                        </div>
                    @endforelse
                </div>
            </x-ui.section-card>

            <div class="space-y-5">
                <x-ui.section-card
                    :title="__('Top teams by ownership load')"
                    :description="__('Operational view of teams with the largest member footprint.')"
                    header-class="py-4"
                    title-class="mt-1 text-[1.15rem] tracking-[-0.025em]"
                    description-class="mt-1 leading-6"
                >
                    <div class="space-y-3 px-6 py-6">
                        @forelse ($largestTeams as $team)
                            <x-ui.surface-card padding="sm" accent="none" class="rounded-[1.2rem]">
                                <div class="flex items-center justify-between gap-4">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $team->name }}</p>
                                        <p class="mt-1 text-xs" style="color: var(--theme-muted-text-color);">{{ __('Owner') }}: {{ $team->owner?->name ?: __('Unknown') }}</p>
                                    </div>
                                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border text-sm font-semibold" style="border-color: rgba(var(--theme-accent-rgb),0.22); background: rgba(var(--theme-accent-rgb),0.08); color: var(--theme-accent);">
                                        {{ number_format((int) $team->members_count) }}
                                    </div>
                                </div>
                            </x-ui.surface-card>
                        @empty
                            <x-ui.empty icon="fa-light fa-people-group" :title="__('No team data available.')" :description="__('Largest teams will appear here once workspaces have members.')" />
                        @endforelse
                    </div>
                </x-ui.section-card>

            </div>
        </div>

        <x-ui.datatable-shell :title="__('Latest users')" :info="__('Most recent user records entering the system.')" header-class="py-4" eyebrow-class="text-[10px] tracking-[0.2em]" title-class="mt-1 text-[1.15rem] tracking-[-0.025em]" description-class="mt-1 leading-6">
            <x-ui.table class="rounded-none border-0 shadow-none">
                <x-ui.table-head>
                    <x-ui.table-cell head>{{ __('User') }}</x-ui.table-cell>
                    <x-ui.table-cell head>{{ __('Role') }}</x-ui.table-cell>
                    <x-ui.table-cell head>{{ __('Plan') }}</x-ui.table-cell>
                    <x-ui.table-cell head>{{ __('Created') }}</x-ui.table-cell>
                </x-ui.table-head>
                <x-ui.table-body>
                    @forelse ($latestUsers as $user)
                        <x-ui.table-row>
                            <x-ui.table-cell>
                                <div class="space-y-1">
                                    <p class="font-semibold" style="color: var(--theme-header-text-color);">{{ $user->name }}</p>
                                    <p class="text-xs" style="color: var(--theme-muted-text-color);">{{ '@'.$user->username }} · {{ strtoupper($user->locale ?: 'unset') }}</p>
                                </div>
                            </x-ui.table-cell>
                            <x-ui.table-cell>{{ $user->isSuperAdmin() ? __('Super admin') : ($user->role?->name ?: __('No role')) }}</x-ui.table-cell>
                            <x-ui.table-cell>{{ $user->plan?->name ?: __('No plan') }}</x-ui.table-cell>
                            <x-ui.table-cell>{{ $user->created_at?->format('Y-m-d H:i') }}</x-ui.table-cell>
                        </x-ui.table-row>
                    @empty
                        <x-ui.table-row>
                            <x-ui.table-cell colspan="4" class="py-10"><x-ui.empty icon="fa-light fa-user" :title="__('No users found.')" :description="__('Recent user entries will appear here once accounts are created.')" /></x-ui.table-cell>
                        </x-ui.table-row>
                    @endforelse
                </x-ui.table-body>
            </x-ui.table>
        </x-ui.datatable-shell>
    </div>
@endcomponent
