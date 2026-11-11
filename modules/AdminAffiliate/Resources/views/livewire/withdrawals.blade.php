@php
    $metricCards = [
        ['label' => __('Total'), 'value' => number_format($summary['total']), 'description' => __('All withdrawal requests.'), 'icon' => 'fa-light fa-money-bill-transfer', 'tone' => 'var(--theme-accent)', 'progress' => $summary['total'] > 0 ? 100 : 0],
        ['label' => __('Pending'), 'value' => number_format($summary['pending']), 'description' => __('Waiting for payout processing.'), 'icon' => 'fa-light fa-hourglass-half', 'tone' => 'rgb(217 119 6)', 'progress' => $summary['pending'] > 0 ? 100 : 0],
        ['label' => __('Approved Value'), 'value' => number_format($summary['approved'], 2), 'description' => __('Total amount already approved for payout.'), 'icon' => 'fa-light fa-circle-check', 'tone' => 'rgb(5 150 105)', 'progress' => $summary['approved'] > 0 ? 100 : 0],
    ];
    $statusLabels = [
        'all' => __('All'),
        '0' => __('Pending'),
        '1' => __('Approved'),
        '2' => __('Rejected'),
    ];
@endphp

<div class="space-y-6">
    <x-ui.page-hero
        :eyebrow="__('Finance')"
        :title="__('Affiliate Withdrawals')"
        :description="__('Approve or reject payout requests submitted by affiliates.')"
        :count="$summary['total']"
        icon="fa-light fa-money-bill-transfer"
    />

    @if ($statusMessage)
        <x-ui.alert variant="success" :title="__('Saved')" :description="$statusMessage" dismissible />
    @endif

    <x-ui.metric-strip :items="$metricCards" :show-progress="false" columns="md:grid-cols-3" />

    <x-ui.filter-panel fields-class="md:grid-cols-[minmax(0,1fr)_220px_auto]">
        <x-slot:fields>
            <x-ui.input wire:model.live.debounce.300ms="search" :label="__('Search')" :placeholder="__('Affiliate, withdrawal id, payment method...')" />
            <x-ui.select wire:model.live="statusFilter" :label="__('Status')">
                <option value="all" @selected($filters['status'] === 'all')>{{ __('All') }}</option>
                <option value="0" @selected((string) $filters['status'] === '0')>{{ __('Pending') }}</option>
                <option value="1" @selected((string) $filters['status'] === '1')>{{ __('Approved') }}</option>
                <option value="2" @selected((string) $filters['status'] === '2')>{{ __('Rejected') }}</option>
            </x-ui.select>
        </x-slot:fields>

        <x-slot:actions>
            <x-ui.button type="button" wire:click="resetFilters">{{ __('Reset') }}</x-ui.button>
        </x-slot:actions>

        <x-slot:chips>
            @if ($filters['q'] !== '')
                <span class="inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-medium" style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color);">{{ $filters['q'] }}</span>
            @endif
            <span class="inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-medium" style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color);">
                {{ __('Status') }}: {{ $statusLabels[$filters['status']] ?? $filters['status'] }}
            </span>
        </x-slot:chips>
    </x-ui.filter-panel>

    <x-ui.datatable-shell :title="__('Withdrawal queue')" :info="__('Each request deducts balance immediately, so rejected payouts restore the amount back to the affiliate.')">
        <x-ui.table class="rounded-none border-0 shadow-none">
            <x-ui.table-head>
                <x-ui.table-cell head>{{ __('Affiliate') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Request') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Method') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Amount') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Status') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Actions') }}</x-ui.table-cell>
            </x-ui.table-head>
            <x-ui.table-body>
                @forelse ($withdrawals as $withdrawal)
                    <x-ui.table-row>
                        <x-ui.table-cell><div class="space-y-1"><p class="font-medium" style="color: var(--theme-header-text-color);">{{ $withdrawal->affiliateUser?->name ?: __('Unknown user') }}</p><p class="text-xs" style="color: var(--theme-muted-text-color);">{{ $withdrawal->affiliateUser?->email ?: __('No email') }}</p></div></x-ui.table-cell>
                        <x-ui.table-cell><div class="space-y-1"><p class="font-mono text-sm" style="color: var(--theme-header-text-color);">{{ $withdrawal->id_secure }}</p><p class="text-xs" style="color: var(--theme-muted-text-color);">{{ $withdrawal->createdAtFormatted() ?: __('N/A') }}</p></div></x-ui.table-cell>
                        <x-ui.table-cell><div class="space-y-1"><p class="text-sm" style="color: var(--theme-header-text-color);">{{ $withdrawal->payment_method }}</p><p class="line-clamp-2 text-xs" style="color: var(--theme-muted-text-color);">{{ $withdrawal->payment_details }}</p></div></x-ui.table-cell>
                        <x-ui.table-cell><span class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ number_format((float) $withdrawal->amount, 2) }}</span></x-ui.table-cell>
                        <x-ui.table-cell><x-ui.badge :variant="$withdrawal->statusVariant()">{{ $withdrawal->statusLabel() }}</x-ui.badge></x-ui.table-cell>
                        <x-ui.table-cell>
                            @if ((int) $withdrawal->status === 0)
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-ui.button type="button" size="sm" wire:click="updateStatus({{ $withdrawal->id }}, 'approve')">{{ __('Approve') }}</x-ui.button>
                                    <x-ui.button type="button" variant="outline" size="sm" wire:click="updateStatus({{ $withdrawal->id }}, 'reject')">{{ __('Reject') }}</x-ui.button>
                                </div>
                            @else
                                <span class="text-xs" style="color: var(--theme-muted-text-color);">{{ __('Processed') }}</span>
                            @endif
                        </x-ui.table-cell>
                    </x-ui.table-row>
                @empty
                    <x-ui.table-row>
                        <x-ui.table-cell colspan="6" class="py-10">
                            <x-ui.empty icon="fa-light fa-money-bill-transfer" :title="__('No withdrawal requests found.')" :description="__('Submitted payout requests will appear here for finance review.')" />
                        </x-ui.table-cell>
                    </x-ui.table-row>
                @endforelse
            </x-ui.table-body>
        </x-ui.table>

        @if ($withdrawals->hasPages())
            <div class="border-t px-6 py-4" style="border-color: var(--theme-border-color);">{{ $withdrawals->links() }}</div>
        @endif
    </x-ui.datatable-shell>
</div>
