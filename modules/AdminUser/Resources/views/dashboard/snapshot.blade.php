@php
    $usersPerTeam = (int) ($metrics['teams'] ?? 0) > 0
        ? number_format(((int) $metrics['users']) / max(1, (int) $metrics['teams']), 1)
        : number_format((int) $metrics['users']);
    $rolesPerTeam = (int) ($metrics['teams'] ?? 0) > 0
        ? number_format(((int) $metrics['roles']) / max(1, (int) $metrics['teams']), 1)
        : number_format((int) $metrics['roles']);
    $signupShare = (int) ($metrics['users'] ?? 0) > 0
        ? round((((int) $metrics['new_users']) / max(1, (int) $metrics['users'])) * 100)
        : 0;
    $signupReport = $signupReport ?? [
        'today' => 0,
        'week' => 0,
        'month' => 0,
        'total' => (int) $metrics['users'],
        'daily' => collect(range(6, 0))->map(fn () => ['label' => '--', 'value' => 0])->values(),
    ];
    $signupTrendCategories = collect($signupReport['daily'] ?? [])->map(fn (array $day) => $day['label'] ?? '--')->all();
    $signupTrendSeries = [[
        'name' => __('Signups'),
        'data' => collect($signupReport['daily'] ?? [])->map(fn (array $day) => (int) ($day['value'] ?? 0))->all(),
    ]];
    $signupFooterStats = [
        ['label' => __('Today'), 'value' => (int) $signupReport['today']],
        ['label' => __('Last 7 days'), 'value' => (int) $signupReport['week']],
        ['label' => __('Month'), 'value' => (int) $signupReport['month']],
        ['label' => __('Total accounts'), 'value' => (int) $signupReport['total']],
    ];
@endphp
<x-ui.dashboard-module :title="__('Users and access')" :description="__('Account growth and workspace membership.')">
    <x-slot:actions><x-ui.button :href="$reportRoute" variant="outline" size="sm" wire:navigate>{{ __('View report') }}<i class="fa-light fa-arrow-up-right" aria-hidden="true"></i></x-ui.button></x-slot:actions>
    <div class="sp-user-metrics">
        <x-ui.stat-card :label="__('Total users')" :value="number_format((int)$metrics['users'])" icon="fa-light fa-users" />
        <x-ui.stat-card :label="__('Teams')" :value="number_format((int)$metrics['teams'])" icon="fa-light fa-user-group" />
        <x-ui.stat-card :label="__('Roles')" :value="number_format((int)$metrics['roles'])" icon="fa-light fa-shield-check" />
        <x-ui.stat-card :label="__('New users')" :value="number_format((int)$metrics['new_users'])" :description="__('Last 7 days')" icon="fa-light fa-user-plus" />
    </div>
    <x-ui.chart :title="__('Registrations')" :description="__('New accounts over the last 7 days.')" type="line" :categories="$signupTrendCategories" :series="$signupTrendSeries" :height="160" />
</x-ui.dashboard-module>
