@php
    $metricCards = [
        ['label' => __('Subscriptions'), 'value' => number_format($summary['total']), 'description' => __('Total recurring subscription records in the system.'), 'tone' => 'var(--theme-accent)', 'progress' => 100],
        ['label' => __('Active'), 'value' => number_format($summary['active']), 'description' => __('Subscriptions currently marked active.'), 'tone' => '#10b981', 'progress' => max(8, $summary['total'] > 0 ? (int) round(($summary['active'] / max($summary['total'], 1)) * 100) : 8)],
        ['label' => __('Cancelled'), 'value' => number_format($summary['cancelled']), 'description' => __('Subscriptions cancelled or ended.'), 'tone' => '#f43f5e', 'progress' => max(8, $summary['total'] > 0 ? (int) round(($summary['cancelled'] / max($summary['total'], 1)) * 100) : 8)],
        ['label' => __('Active MRR'), 'value' => number_format($summary['monthly_value'], 2), 'description' => __('Sum of active subscription amounts.'), 'tone' => '#64748b', 'progress' => $summary['monthly_value'] > 0 ? 100 : 8],
    ];

    $statusLabels = [
        'all' => __('All'),
        '1' => __('Active'),
        '2' => __('Cancelled'),
        '0' => __('Inactive'),
    ];
@endphp

<div class="space-y-6">
    @if ($statusMessage)
        <x-ui.alert variant="success" :title="__('Saved')" :description="$statusMessage" dismissible />
    @endif

    <x-ui.page-hero
        :eyebrow="__('Finance')"
        :title="__('Payment Subscriptions')"
        :description="__('Review recurring billing agreements, assigned plans, gateway sources, and customer references.')"
        :count="$summary['total']"
        icon="fa-light fa-arrows-rotate"
    />

    <x-ui.metric-strip :items="$metricCards" :show-icons="false" columns="md:grid-cols-2 xl:grid-cols-4" />

    <x-ui.filter-panel fields-class="xl:grid-cols-[minmax(0,1.35fr)_220px_220px_auto]">
        <x-slot:fields>
            <x-ui.input wire:model.live.debounce.300ms="search" :label="__('Search')" :placeholder="__('Subscription, customer, user, plan...')" />
            <x-ui.select wire:model.live="statusFilter" :label="__('Status')">
                @foreach ($statusLabels as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </x-ui.select>
            <x-ui.select wire:model.live="gatewayFilter" :label="__('Gateway')">
                <option value="">{{ __('All gateways') }}</option>
                @foreach ($gateways as $gateway)
                    <option value="{{ $gateway }}">{{ $gateway }}</option>
                @endforeach
            </x-ui.select>
        </x-slot:fields>

        <x-slot:actions>
            <x-ui.button type="button" wire:click="$refresh">{{ __('Filter') }}</x-ui.button>
            <x-ui.button type="button" variant="outline" wire:click="resetFilters">{{ __('Reset') }}</x-ui.button>
        </x-slot:actions>

        <x-slot:chips>
            @if ($search !== '')
                <span class="inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-medium" style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color);">{{ $search }}</span>
            @endif
            <span class="inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-medium" style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color);">
                {{ __('Status') }}: {{ $statusLabels[$statusFilter] ?? __('All') }}
            </span>
            <span class="inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-medium" style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color);">
                {{ __('Gateway') }}: {{ $gatewayFilter !== '' ? $gatewayFilter : __('All gateways') }}
            </span>
        </x-slot:chips>
    </x-ui.filter-panel>

    <x-ui.datatable-shell
        :title="__('Recurring subscriptions')"
        :info="__('Administrative view of billing agreements connected to user plans and gateway customer records.')"
        header-class="py-4"
        eyebrow-class="text-[10px] tracking-[0.2em]"
        title-class="mt-1 text-[1.15rem] tracking-[-0.025em]"
        description-class="mt-1 leading-6"
    >
        <x-ui.table class="rounded-none border-0 shadow-none">
            <x-ui.table-head>
                <x-ui.table-cell head>{{ __('Subscription') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('User') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Plan') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Gateway') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Customer') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Amount') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Created') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Status') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Actions') }}</x-ui.table-cell>
            </x-ui.table-head>
            <x-ui.table-body>
                @forelse ($subscriptions as $subscription)
                    <x-ui.table-row wire:key="payment-subscription-row-{{ $subscription->id }}">
                        <x-ui.table-cell><div class="space-y-1"><p class="max-w-[15rem] truncate font-semibold uppercase" style="color: var(--theme-header-text-color);" title="{{ $subscription->subscription_id }}">{{ $subscription->subscription_id ?: __('N/A') }}</p><p class="text-xs" style="color: var(--theme-muted-text-color);">{{ $subscription->id_secure ?: '#'.$subscription->id }}</p></div></x-ui.table-cell>
                        <x-ui.table-cell><div class="space-y-1"><p class="font-medium" style="color: var(--theme-header-text-color);">{{ $subscription->user?->name ?: __('Unknown user') }}</p><p class="text-xs" style="color: var(--theme-muted-text-color);">{{ $subscription->user?->email ?: __('No email') }}</p></div></x-ui.table-cell>
                        <x-ui.table-cell><div class="space-y-1"><p class="font-medium" style="color: var(--theme-header-text-color);">{{ $subscription->plan?->name ?: __('No plan') }}</p><p class="text-xs" style="color: var(--theme-muted-text-color);">{{ $subscription->service ?: __('Unknown service') }}</p></div></x-ui.table-cell>
                        <x-ui.table-cell>{{ $subscription->source ?: __('N/A') }}</x-ui.table-cell>
                        <x-ui.table-cell><p class="max-w-[12rem] truncate text-sm" style="color: var(--theme-header-text-color);" title="{{ $subscription->customer_id }}">{{ $subscription->customer_id ?: __('N/A') }}</p></x-ui.table-cell>
                        <x-ui.table-cell><p class="font-medium" style="color: var(--theme-header-text-color);">{{ $subscription->currency ?: 'USD' }} {{ number_format((float) $subscription->amount, 2) }}</p></x-ui.table-cell>
                        <x-ui.table-cell><div class="space-y-1"><p class="text-sm" style="color: var(--theme-muted-text-color);">{{ $subscription->createdAtFormatted() ?: __('N/A') }}</p><p class="text-xs" style="color: var(--theme-muted-text-color);">{{ __('Updated') }}: {{ $subscription->changedAtFormatted() ?: __('N/A') }}</p></div></x-ui.table-cell>
                        <x-ui.table-cell><x-ui.badge :variant="$subscription->statusVariant()">{{ $subscription->statusLabel() }}</x-ui.badge></x-ui.table-cell>
                        <x-ui.table-cell>
                            <x-ui.dialog wire:key="payment-subscription-delete-dialog-{{ $subscription->id }}" :title="__('Delete this payment subscription?')" :description="__('This permanently removes the selected payment subscription record.')" width="sm" dismissible>
                                <x-slot:trigger><x-ui.button type="button" variant="danger" size="sm">{{ __('Delete') }}</x-ui.button></x-slot:trigger>
                                <x-slot:footer>
                                    <div class="flex justify-end gap-3">
                                        <x-ui.button type="button" variant="outline" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                                        <x-ui.button type="button" variant="danger" wire:click="delete({{ $subscription->id }})" x-on:click="open = false">{{ __('Delete') }}</x-ui.button>
                                    </div>
                                </x-slot:footer>
                            </x-ui.dialog>
                        </x-ui.table-cell>
                    </x-ui.table-row>
                @empty
                    <x-ui.table-row>
                        <x-ui.table-cell colspan="9" class="py-10">
                            <x-ui.empty icon="fa-light fa-arrows-rotate" :title="__('No payment subscriptions found.')" :description="__('Recurring billing records will appear here once subscription-capable gateways start syncing data.')" />
                        </x-ui.table-cell>
                    </x-ui.table-row>
                @endforelse
            </x-ui.table-body>
        </x-ui.table>

        @if ($subscriptions->hasPages())
            <div class="border-t px-6 py-4" style="border-color: var(--theme-border-color);">{{ $subscriptions->links() }}</div>
        @endif
    </x-ui.datatable-shell>
</div>
