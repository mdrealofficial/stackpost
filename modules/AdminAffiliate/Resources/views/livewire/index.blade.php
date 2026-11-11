@php
    $metricCards = [
        ['label' => __('Affiliates'), 'value' => number_format($summary['affiliates']), 'description' => __('Users with affiliate profiles.'), 'icon' => 'fa-light fa-users', 'tone' => 'var(--theme-accent)', 'progress' => $summary['affiliates'] > 0 ? 100 : 0],
        ['label' => __('Clicks'), 'value' => number_format($summary['clicks']), 'description' => __('Tracked referral clicks.'), 'icon' => 'fa-light fa-arrow-pointer', 'tone' => 'rgb(71 85 105)', 'progress' => $summary['clicks'] > 0 ? 100 : 0],
        ['label' => __('Conversions'), 'value' => number_format($summary['conversions']), 'description' => __('Commission-generating events.'), 'icon' => 'fa-light fa-badge-check', 'tone' => 'rgb(5 150 105)', 'progress' => $summary['conversions'] > 0 ? 100 : 0],
        ['label' => __('Approved Earnings'), 'value' => number_format($summary['approved'], 2), 'description' => __('Lifetime approved affiliate earnings.'), 'icon' => 'fa-light fa-sack-dollar', 'tone' => 'rgb(217 119 6)', 'progress' => $summary['approved'] > 0 ? 100 : 0],
    ];
@endphp

<div class="space-y-6">
    <x-ui.page-hero
        :eyebrow="__('Finance')"
        :title="__('Affiliate')"
        :description="__('Monitor affiliates, referral performance, and balances.')"
        :count="$summary['affiliates']"
        icon="fa-light fa-hand-holding-dollar"
    />

    <x-ui.metric-strip :items="$metricCards" :show-progress="false" columns="md:grid-cols-2 xl:grid-cols-4" />

    <x-ui.filter-panel fields-class="md:grid-cols-[minmax(0,1fr)_auto]">
        <x-slot:fields>
            <x-ui.input wire:model.live.debounce.300ms="search" :label="__('Search')" :placeholder="__('Name, email, username, referral code...')" />
        </x-slot:fields>

        <x-slot:actions>
            <x-ui.button type="button" wire:click="resetFilters">{{ __('Reset') }}</x-ui.button>
        </x-slot:actions>

        <x-slot:chips>
            @if ($filters['q'] !== '')
                <span class="inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-medium" style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color);">{{ $filters['q'] }}</span>
            @endif
        </x-slot:chips>
    </x-ui.filter-panel>

    <x-ui.datatable-shell :title="__('Affiliate users')" :info="__('Each row shows referral identity, current balance, and top-level performance.')">
        <x-ui.table class="rounded-none border-0 shadow-none">
            <x-ui.table-head>
                <x-ui.table-cell head>{{ __('User') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Referral') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Clicks') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Conversions') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Balance') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Approved') }}</x-ui.table-cell>
            </x-ui.table-head>
            <x-ui.table-body>
                @forelse ($profiles as $profile)
                    <x-ui.table-row>
                        <x-ui.table-cell><div class="space-y-1"><p class="font-medium" style="color: var(--theme-header-text-color);">{{ $profile->user?->name ?: __('Unknown user') }}</p><p class="text-xs" style="color: var(--theme-muted-text-color);">{{ $profile->user?->email ?: __('No email') }}</p></div></x-ui.table-cell>
                        <x-ui.table-cell><div class="space-y-1"><p class="font-mono text-sm" style="color: var(--theme-header-text-color);">{{ $profile->user?->referral_code ?: __('N/A') }}</p><p class="text-xs" style="color: var(--theme-muted-text-color);">{{ $profile->user?->username ?: __('No username') }}</p></div></x-ui.table-cell>
                        <x-ui.table-cell><span class="text-sm" style="color: var(--theme-header-text-color);">{{ number_format($profile->clicks) }}</span></x-ui.table-cell>
                        <x-ui.table-cell><span class="text-sm" style="color: var(--theme-header-text-color);">{{ number_format($profile->conversions) }}</span></x-ui.table-cell>
                        <x-ui.table-cell><span class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ number_format((float) $profile->total_balance, 2) }}</span></x-ui.table-cell>
                        <x-ui.table-cell><span class="text-sm" style="color: var(--theme-header-text-color);">{{ number_format((float) $profile->total_approved, 2) }}</span></x-ui.table-cell>
                    </x-ui.table-row>
                @empty
                    <x-ui.table-row>
                        <x-ui.table-cell colspan="6" class="py-10">
                            <x-ui.empty icon="fa-light fa-hand-holding-dollar" :title="__('No affiliate profiles found.')" :description="__('Profiles appear after users register and affiliate tracking starts being used.')" />
                        </x-ui.table-cell>
                    </x-ui.table-row>
                @endforelse
            </x-ui.table-body>
        </x-ui.table>

        @if ($profiles->hasPages())
            <div class="border-t px-6 py-4" style="border-color: var(--theme-border-color);">{{ $profiles->links() }}</div>
        @endif
    </x-ui.datatable-shell>
</div>
