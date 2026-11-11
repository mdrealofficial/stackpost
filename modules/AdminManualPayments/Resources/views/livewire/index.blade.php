@php
    $metricCards = [
        ['label' => __('Total'), 'value' => number_format($summary['total']), 'description' => __('All manual payment requests currently stored.'), 'tone' => 'var(--theme-accent)', 'progress' => 100],
        ['label' => __('Pending'), 'value' => number_format($summary['pending']), 'description' => __('Requests waiting for finance review.'), 'tone' => '#f59e0b', 'progress' => max(8, $summary['total'] > 0 ? (int) round(($summary['pending'] / max($summary['total'], 1)) * 100) : 8)],
        ['label' => __('Approved'), 'value' => number_format($summary['approved']), 'description' => __('Requests already approved and applied.'), 'tone' => '#10b981', 'progress' => max(8, $summary['total'] > 0 ? (int) round(($summary['approved'] / max($summary['total'], 1)) * 100) : 8)],
        ['label' => __('Approved Gross'), 'value' => number_format($summary['gross'], 2), 'description' => __('Approved manual payment amount total.'), 'tone' => '#64748b', 'progress' => $summary['gross'] > 0 ? 100 : 8],
    ];

    $statusLabels = [
        'all' => __('All'),
        '0' => __('Pending'),
        '1' => __('Approved'),
        '2' => __('Cancelled'),
    ];
@endphp

<div class="space-y-6">
    @if ($statusMessage)
        <x-ui.alert variant="success" :title="__('Saved')" :description="$statusMessage" dismissible />
    @endif

    <x-ui.page-hero
        :eyebrow="__('Finance')"
        :title="__('Manual Payments')"
        :description="__('Review submitted manual payments, approve valid requests, and activate plans from offline transfers.')"
        :count="$summary['total']"
        icon="fa-light fa-wallet"
    >
        <x-slot:actions>
            <x-ui.button href="{{ route('admin-manual-payments.create') }}" wire:navigate>{{ __('Create payment') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-hero>

    <x-ui.metric-strip :items="$metricCards" :show-icons="false" columns="md:grid-cols-2 xl:grid-cols-4" />

    <x-ui.filter-panel fields-class="xl:grid-cols-[minmax(0,1fr)_220px_auto]">
        <x-slot:fields>
            <x-ui.input wire:model.live.debounce.300ms="search" :label="__('Search')" :placeholder="__('User, payment ID, notes...')" />
            <x-ui.select wire:model.live="statusFilter" :label="__('Status')">
                @foreach ($statusLabels as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
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
        </x-slot:chips>
    </x-ui.filter-panel>

    <x-ui.datatable-shell
        :title="__('Manual payment queue')"
        :info="__('Offline payments submitted by users, along with approval controls and assigned plans.')"
        header-class="py-4"
        eyebrow-class="text-[10px] tracking-[0.2em]"
        title-class="mt-1 text-[1.15rem] tracking-[-0.025em]"
        description-class="mt-1 leading-6"
    >
        <x-ui.table class="rounded-none border-0 shadow-none">
            <x-ui.table-head>
                <x-ui.table-cell head>{{ __('User') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Plan') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Payment') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Amount') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Created') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Status') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Actions') }}</x-ui.table-cell>
            </x-ui.table-head>
            <x-ui.table-body>
                @forelse ($manualPayments as $manualPayment)
                    <x-ui.table-row wire:key="manual-payment-row-{{ $manualPayment->id }}">
                        <x-ui.table-cell><div class="space-y-1"><p class="font-medium" style="color: var(--theme-header-text-color);">{{ $manualPayment->user?->name ?: __('Unknown user') }}</p><p class="text-xs" style="color: var(--theme-muted-text-color);">{{ $manualPayment->user?->email ?: __('No email') }}</p></div></x-ui.table-cell>
                        <x-ui.table-cell><div class="space-y-1"><p class="text-sm font-medium" style="color: var(--theme-header-text-color);">{{ $manualPayment->plan?->name ?: __('N/A') }}</p><p class="text-xs" style="color: var(--theme-muted-text-color);">{{ match((int) ($manualPayment->plan?->type ?? 1)) { 2 => __('Yearly'), 3 => __('Lifetime'), default => __('Monthly') } }}</p></div></x-ui.table-cell>
                        <x-ui.table-cell><div class="space-y-1"><p class="max-w-[12rem] truncate text-sm font-medium" style="color: var(--theme-header-text-color);" title="{{ $manualPayment->payment_id }}">{{ $manualPayment->payment_id }}</p><p class="max-w-[12rem] truncate text-xs" style="color: var(--theme-muted-text-color);" title="{{ $manualPayment->payment_info }}">{{ $manualPayment->payment_info ?: __('No payment info') }}</p></div></x-ui.table-cell>
                        <x-ui.table-cell><div class="space-y-1"><p class="text-sm font-medium" style="color: var(--theme-header-text-color);">{{ $manualPayment->amountLabel() }}</p><p class="text-xs" style="color: var(--theme-muted-text-color);">{{ $manualPayment->notes ?: __('No notes') }}</p></div></x-ui.table-cell>
                        <x-ui.table-cell><p class="text-sm" style="color: var(--theme-muted-text-color);">{{ $manualPayment->createdAtFormatted() ?: __('N/A') }}</p></x-ui.table-cell>
                        <x-ui.table-cell><x-ui.badge :variant="$manualPayment->statusVariant()">{{ $manualPayment->statusLabel() }}</x-ui.badge></x-ui.table-cell>
                        <x-ui.table-cell>
                            <div class="flex flex-wrap items-center gap-2">
                                @if ((int) $manualPayment->status !== 1)
                                    <x-ui.button type="button" size="sm" wire:click="approve({{ $manualPayment->id }})">{{ __('Approve') }}</x-ui.button>
                                @endif
                                @if ((int) $manualPayment->status !== 2)
                                    <x-ui.button type="button" variant="outline" size="sm" wire:click="cancel({{ $manualPayment->id }})">{{ __('Cancel') }}</x-ui.button>
                                @endif
                                <x-ui.button href="{{ route('admin-manual-payments.edit', $manualPayment) }}" variant="outline" size="sm" wire:navigate>{{ __('Edit') }}</x-ui.button>
                                <x-ui.dialog wire:key="manual-payment-delete-dialog-{{ $manualPayment->id }}" :title="__('Delete manual payment?')" :description="__('This permanently removes the selected manual payment request.')" width="sm" dismissible>
                                    <x-slot:trigger><x-ui.button type="button" variant="danger" size="sm">{{ __('Delete') }}</x-ui.button></x-slot:trigger>
                                    <x-slot:footer>
                                        <div class="flex justify-end gap-3">
                                            <x-ui.button type="button" variant="outline" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                                            <x-ui.button type="button" variant="danger" wire:click="delete({{ $manualPayment->id }})" x-on:click="open = false">{{ __('Delete') }}</x-ui.button>
                                        </div>
                                    </x-slot:footer>
                                </x-ui.dialog>
                            </div>
                        </x-ui.table-cell>
                    </x-ui.table-row>
                @empty
                    <x-ui.table-row>
                        <x-ui.table-cell colspan="7" class="py-10">
                            <x-ui.empty icon="fa-light fa-money-check-dollar" :title="__('No manual payments found.')" :description="__('Create the first offline payment record or wait for users to submit one from the portal.')" />
                        </x-ui.table-cell>
                    </x-ui.table-row>
                @endforelse
            </x-ui.table-body>
        </x-ui.table>

        @if ($manualPayments->hasPages())
            <div class="border-t px-6 py-4" style="border-color: var(--theme-border-color);">{{ $manualPayments->links() }}</div>
        @endif
    </x-ui.datatable-shell>
</div>
