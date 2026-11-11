@php
    $metricCards = [
        ['label' => __('Entries'), 'value' => number_format($metrics['entries']), 'description' => __('Ledger rows after current filters.'), 'tone' => 'var(--theme-accent)', 'progress' => 100],
        ['label' => __('Purchases'), 'value' => number_format($metrics['purchases']), 'description' => __('Pack purchase rows.'), 'tone' => '#10b981', 'progress' => $metrics['purchases'] > 0 ? 100 : 8],
        ['label' => __('Granted'), 'value' => number_format($metrics['granted']), 'description' => __('Credits added through purchase or manual adjustment.'), 'tone' => '#f59e0b', 'progress' => $metrics['granted'] > 0 ? 100 : 8],
        ['label' => __('Remaining'), 'value' => number_format($metrics['remaining']), 'description' => __('Open top-up balance currently stored in lots.'), 'tone' => '#64748b', 'progress' => $metrics['remaining'] > 0 ? 100 : 8],
    ];
@endphp

<div class="mx-auto max-w-[88rem] space-y-6">
    <x-ui.page-hero
        :eyebrow="__('Credit operations')"
        :title="__('Credit Ledger')"
        :description="__('Audit top-up purchases, lot consumption, and remaining balance across all users.')"
        :count="$metrics['entries']"
        icon="fa-light fa-receipt"
    >
        <x-slot:actions>
            <x-ui.button href="{{ route('admin-credits.packs') }}" variant="outline" wire:navigate>{{ __('Open packs') }}</x-ui.button>
            <x-ui.button href="{{ route('admin-credits.usage') }}" variant="outline" wire:navigate>{{ __('Open usage') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-hero>

    <x-ui.metric-strip :items="$metricCards" :show-icons="false" columns="md:grid-cols-2 xl:grid-cols-4" />

    <x-ui.datatable-shell :title="__('Ledger directory')" :info="__('Filter by user, pack, or lot type to review how top-up balances move through the system.')" header-class="py-4" eyebrow-class="text-[10px] tracking-[0.2em]" title-class="mt-1 text-[1.15rem] tracking-[-0.025em]" description-class="mt-1 leading-6">
        <x-slot:toolbar>
            <div class="grid w-full gap-4 md:grid-cols-[minmax(0,1fr)_220px_auto]">
                <x-ui.input wire:model.live.debounce.300ms="search" type="text" :label="__('Search')" :placeholder="__('User, email, pack, or type...')" />
                <x-ui.select wire:model.live="type" :label="__('Type')">
                    @foreach ($typeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </x-ui.select>
            </div>
        </x-slot:toolbar>

        <x-ui.table class="rounded-none border-0 shadow-none">
            <x-ui.table-head>
                <x-ui.table-cell head>{{ __('User') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Type') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Pack') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Amount') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Remaining') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Payment') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Created') }}</x-ui.table-cell>
            </x-ui.table-head>
            <x-ui.table-body>
                @forelse ($entries as $entry)
                    <x-ui.table-row>
                        <x-ui.table-cell>
                            <div class="space-y-1">
                                <p class="font-semibold" style="color: var(--theme-header-text-color);">{{ $entry->user?->name ?? __('Unknown user') }}</p>
                                <p class="text-xs" style="color: var(--theme-muted-text-color);">{{ $entry->user?->email ?? '—' }}</p>
                            </div>
                        </x-ui.table-cell>
                        <x-ui.table-cell><x-ui.badge :variant="in_array($entry->type, ['purchase', 'adjustment'], true) ? 'success' : 'neutral'">{{ __(ucfirst(str_replace('_', ' ', $entry->type))) }}</x-ui.badge></x-ui.table-cell>
                        <x-ui.table-cell>{{ $entry->creditPack?->name ?: __('Manual adjustment') }}</x-ui.table-cell>
                        <x-ui.table-cell><span class="font-semibold" style="color: var(--theme-header-text-color);">{{ $entry->amount > 0 ? '+' : '' }}{{ number_format((int) $entry->amount) }}</span></x-ui.table-cell>
                        <x-ui.table-cell>{{ number_format((int) $entry->remaining) }}</x-ui.table-cell>
                        <x-ui.table-cell><span class="font-mono text-xs" style="color: var(--theme-muted-text-color);">{{ $entry->paymentHistory?->transaction_id ?: '—' }}</span></x-ui.table-cell>
                        <x-ui.table-cell>{{ $entry->created_at?->format('Y-m-d H:i') }}</x-ui.table-cell>
                    </x-ui.table-row>
                @empty
                    <x-ui.table-row>
                        <x-ui.table-cell colspan="7" class="py-10"><x-ui.empty icon="fa-light fa-receipt" :title="__('No ledger entries found.')" :description="__('Top-up purchases and consumption lots will appear here once credit packs are used.')" /></x-ui.table-cell>
                    </x-ui.table-row>
                @endforelse
            </x-ui.table-body>
        </x-ui.table>

        @if ($entries->hasPages())
            <div class="px-5 py-4">
                {{ $entries->links() }}
            </div>
        @endif
    </x-ui.datatable-shell>
</div>
