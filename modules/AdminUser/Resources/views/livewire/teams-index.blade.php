@php
    $teamCount = $teams->count();
    $memberAssignments = (int) $teams->sum('members_count');
    $ownerAssignedCount = (int) $teams->whereNotNull('owner_user_id')->count();
    $ownerlessCount = max(0, $teamCount - $ownerAssignedCount);
    $averageTeamSize = $teamCount > 0 ? round($memberAssignments / $teamCount, 1) : 0;
    $metricCards = [
        ['label' => __('Teams'), 'value' => number_format($teamCount), 'description' => __('Current workspace teams.'), 'tone' => 'var(--theme-accent)', 'progress' => 100],
        ['label' => __('Owned'), 'value' => number_format($ownerAssignedCount), 'description' => __('Teams with owner assigned.'), 'tone' => '#10b981', 'progress' => $teamCount > 0 ? max(8, (int) round(($ownerAssignedCount / $teamCount) * 100)) : 8],
        ['label' => __('Need owner'), 'value' => number_format($ownerlessCount), 'description' => __('Teams missing ownership.'), 'tone' => '#f59e0b', 'progress' => $teamCount > 0 ? max(8, (int) round(($ownerlessCount / $teamCount) * 100)) : 8],
        ['label' => __('Avg size'), 'value' => number_format($averageTeamSize, 1), 'description' => __('Members per team.'), 'tone' => '#64748b', 'progress' => $averageTeamSize > 0 ? 100 : 8],
    ];
@endphp

<div class="mx-auto max-w-[88rem] space-y-6">
    <x-ui.page-hero
        :eyebrow="__('Team workspace')"
        :title="__('Teams')"
        :description="__('Personal teams are provisioned automatically for each user, with ownership and member allocation managed from this directory.')"
        :count="$teamCount"
        icon="fa-light fa-people-group"
    >
        <x-slot:actions>
            <x-ui.button href="{{ route('admin-users.index') }}" variant="outline" wire:navigate>{{ __('Open users') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-hero>

    <x-ui.metric-strip :items="$metricCards" :show-icons="false" columns="md:grid-cols-2 xl:grid-cols-4" />

    <x-ui.datatable-shell :title="__('Team directory')" :info="__('Ownership, member counts, and team identity in a cleaner operational table.')" header-class="py-4" eyebrow-class="text-[10px] tracking-[0.2em]" title-class="mt-1 text-[1.15rem] tracking-[-0.025em]" description-class="mt-1 leading-6">
        <x-slot:toolbar>
            <div class="flex flex-wrap items-center gap-3">
                <x-ui.badge variant="primary">{{ number_format($teamCount) }} {{ __('teams') }}</x-ui.badge>
                <x-ui.badge variant="neutral">{{ number_format($ownerlessCount) }} {{ __('need owner') }}</x-ui.badge>
                <x-ui.badge variant="success">{{ __('Auto-created per user') }}</x-ui.badge>
            </div>
        </x-slot:toolbar>

        <x-ui.table class="rounded-none border-0 shadow-none">
            <x-ui.table-head>
                <x-ui.table-cell head>{{ __('Team') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Owner') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Members') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Slug') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Actions') }}</x-ui.table-cell>
            </x-ui.table-head>
            <x-ui.table-body>
                @forelse ($teams as $team)
                    <x-ui.table-row>
                        <x-ui.table-cell><div class="space-y-1"><div class="flex flex-wrap items-center gap-2"><p class="font-semibold" style="color: var(--theme-header-text-color);">{{ $team->name }}</p><x-ui.badge :variant="$team->owner_user_id ? 'success' : 'danger'">{{ $team->owner_user_id ? __('Owned') : __('Needs owner') }}</x-ui.badge></div><p class="text-xs" style="color: var(--theme-muted-text-color);">{{ $team->description ?: __('No description yet') }}</p></div></x-ui.table-cell>
                        <x-ui.table-cell><div class="space-y-1"><p class="font-medium" style="color: var(--theme-header-text-color);">{{ $team->owner?->name ?? '-' }}</p><p class="text-xs" style="color: var(--theme-muted-text-color);">{{ $team->owner?->email ?? __('No owner assigned') }}</p></div></x-ui.table-cell>
                        <x-ui.table-cell><x-ui.badge variant="success">{{ number_format($team->members_count) }}</x-ui.badge></x-ui.table-cell>
                        <x-ui.table-cell><span class="font-mono text-xs" style="color: var(--theme-muted-text-color);">{{ $team->slug }}</span></x-ui.table-cell>
                        <x-ui.table-cell>
                            <div class="flex items-center justify-end pr-4">
                                <x-ui.button href="{{ route('admin-user-teams.edit', $team) }}" variant="outline" size="sm" wire:navigate>{{ __('Manage') }}</x-ui.button>
                            </div>
                        </x-ui.table-cell>
                    </x-ui.table-row>
                @empty
                    <x-ui.table-row>
                        <x-ui.table-cell colspan="5" class="py-10"><x-ui.empty icon="fa-light fa-people-group" :title="__('No teams found.')" :description="__('Teams will appear here once collaborative workspaces are created.')" /></x-ui.table-cell>
                    </x-ui.table-row>
                @endforelse
            </x-ui.table-body>
        </x-ui.table>
    </x-ui.datatable-shell>
</div>
