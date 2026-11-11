@php
    $metricCards = [
        ['label' => __('Entries'), 'value' => number_format($metrics['entries']), 'description' => __('Usage rows after current filters.'), 'tone' => 'var(--theme-accent)', 'progress' => 100],
        ['label' => __('Credits spent'), 'value' => number_format($metrics['credits']), 'description' => __('Credits consumed by filtered actions.'), 'tone' => '#10b981', 'progress' => $metrics['credits'] > 0 ? 100 : 8],
        ['label' => __('Users'), 'value' => number_format($metrics['users']), 'description' => __('Distinct users consuming credits.'), 'tone' => '#f59e0b', 'progress' => $metrics['users'] > 0 ? 100 : 8],
        ['label' => __('Actions'), 'value' => number_format($metrics['actions']), 'description' => __('Distinct action keys in the filtered set.'), 'tone' => '#64748b', 'progress' => $metrics['actions'] > 0 ? 100 : 8],
    ];
@endphp

<div class="mx-auto max-w-[88rem] space-y-6">
    <x-ui.page-hero
        :eyebrow="__('Credit operations')"
        :title="__('Credit Usage')"
        :description="__('Inspect where credits are consumed, who is spending them, and which actions drive the most demand.')"
        :count="$metrics['entries']"
        icon="fa-light fa-bolt"
    >
        <x-slot:actions>
            <x-ui.button href="{{ route('admin-credits.ledger') }}" variant="outline" wire:navigate>{{ __('Open ledger') }}</x-ui.button>
            <x-ui.button href="{{ route('admin-credits.packs') }}" variant="outline" wire:navigate>{{ __('Open packs') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-hero>

    <x-ui.metric-strip :items="$metricCards" :show-icons="false" columns="md:grid-cols-2 xl:grid-cols-4" />

    <x-ui.datatable-shell :title="__('Usage directory')" :info="__('Filter by action or user to inspect where credits are being spent across the product.')" header-class="py-4" eyebrow-class="text-[10px] tracking-[0.2em]" title-class="mt-1 text-[1.15rem] tracking-[-0.025em]" description-class="mt-1 leading-6">
        <x-slot:toolbar>
            <div class="grid w-full gap-4 md:grid-cols-[minmax(0,1fr)_260px_auto]">
                <x-ui.input wire:model.live.debounce.300ms="search" type="text" :label="__('Search')" :placeholder="__('User, email, action, or feature...')" />
                <x-ui.select wire:model.live="action" :label="__('Action')">
                    @foreach ($actionOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </x-ui.select>
            </div>
        </x-slot:toolbar>

        <x-ui.table class="rounded-none border-0 shadow-none">
            <x-ui.table-head>
                <x-ui.table-cell head>{{ __('User') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Action') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Credits') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Quantity') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Plan') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Created') }}</x-ui.table-cell>
            </x-ui.table-head>
            <x-ui.table-body>
                @forelse ($logs as $log)
                    <x-ui.table-row>
                        <x-ui.table-cell>
                            <div class="space-y-1">
                                <p class="font-semibold" style="color: var(--theme-header-text-color);">{{ $log->user?->name ?? __('Unknown user') }}</p>
                                <p class="text-xs" style="color: var(--theme-muted-text-color);">{{ $log->user?->email ?? '—' }}</p>
                            </div>
                        </x-ui.table-cell>
                        <x-ui.table-cell>
                            <div class="space-y-1">
                                <p class="font-semibold" style="color: var(--theme-header-text-color);">{{ $log->action_key }}</p>
                                <p class="text-xs" style="color: var(--theme-muted-text-color);">{{ $log->feature ?: '—' }}</p>
                            </div>
                        </x-ui.table-cell>
                        <x-ui.table-cell><span class="font-semibold" style="color: var(--theme-header-text-color);">{{ number_format((int) $log->amount) }}</span></x-ui.table-cell>
                        <x-ui.table-cell>{{ number_format((int) $log->quantity) }}</x-ui.table-cell>
                        <x-ui.table-cell>{{ $log->plan?->name ?: __('No plan') }}</x-ui.table-cell>
                        <x-ui.table-cell>{{ $log->created_at?->format('Y-m-d H:i') }}</x-ui.table-cell>
                    </x-ui.table-row>
                @empty
                    <x-ui.table-row>
                        <x-ui.table-cell colspan="6" class="py-10"><x-ui.empty icon="fa-light fa-bolt" :title="__('No usage records found.')" :description="__('Credit consumption rows will appear here after AI or other metered actions are executed.')" /></x-ui.table-cell>
                    </x-ui.table-row>
                @endforelse
            </x-ui.table-body>
        </x-ui.table>

        @if ($logs->hasPages())
            <div class="px-5 py-4">
                {{ $logs->links() }}
            </div>
        @endif
    </x-ui.datatable-shell>
</div>
