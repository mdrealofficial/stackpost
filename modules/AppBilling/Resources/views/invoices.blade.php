@component(theme_view('layouts.app', 'app'), ['title' => __('Invoices')])
    @php
        $invoiceRows = $invoices->getCollection();
        $paidRows = $invoiceRows->where('status', 1);
        $pageTotal = (float) $paidRows->sum('amount');
        $latestInvoice = $invoiceRows->first();
        $currencySymbol = $latestInvoice?->currency ?: 'USD';
    @endphp

    <div class="space-y-6">
        <x-ui.sub-header :compact="true" :title="__('Invoices')" :description="__('Find your payments and download invoice PDFs.')"><x-slot:actions>
                        <x-ui.button href="{{ route('portal.billing') }}" variant="outline" wire:navigate>
                            <i class="fa-light fa-arrow-left"></i>
                            {{ __('Back to billing') }}
                        </x-ui.button>
                    </x-slot:actions></x-ui.sub-header>

        <x-ui.datatable-shell :title="__('Invoice history')" :info="__('All recorded payment history rows belonging to the current account.')">
            <x-slot:toolbar>
                <x-ui.button href="{{ route('portal.billing') }}" variant="outline" wire:navigate>
                    <i class="fa-light fa-arrow-left"></i>
                    {{ __('Back to billing') }}
                </x-ui.button>
            </x-slot:toolbar>
            <x-ui.table class="rounded-none border-0 shadow-none">
                <x-ui.table-head>
                    <x-ui.table-cell head>{{ __('Invoice') }}</x-ui.table-cell>
                    <x-ui.table-cell head>{{ __('Transaction') }}</x-ui.table-cell>
                    <x-ui.table-cell head>{{ __('Plan') }}</x-ui.table-cell>
                    <x-ui.table-cell head>{{ __('Gateway') }}</x-ui.table-cell>
                    <x-ui.table-cell head>{{ __('Amount') }}</x-ui.table-cell>
                    <x-ui.table-cell head>{{ __('Status') }}</x-ui.table-cell>
                    <x-ui.table-cell head>{{ __('Created') }}</x-ui.table-cell>
                    <x-ui.table-cell head>{{ __('Actions') }}</x-ui.table-cell>
                </x-ui.table-head>
                <x-ui.table-body>
                    @forelse ($invoices as $invoice)
                        <x-ui.table-row>
                            <x-ui.table-cell>
                                <div class="flex items-start gap-3">
                                    <span class="mt-0.5 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl" style="background: rgba(var(--theme-accent-rgb), 0.10); color: var(--theme-accent-color);">
                                        <i class="fa-light fa-receipt"></i>
                                    </span>
                                    <div class="min-w-0 space-y-1">
                                        <p class="truncate font-semibold uppercase" style="color: var(--theme-header-text-color);">{{ $invoice->id_secure ?: __('N/A') }}</p>
                                        <p class="text-xs" style="color: var(--theme-muted-text-color);">#{{ $invoice->id }}</p>
                                    </div>
                                </div>
                            </x-ui.table-cell>
                            <x-ui.table-cell><span class="font-mono text-xs" style="color: var(--theme-muted-text-color);">{{ $invoice->transaction_id ?: __('N/A') }}</span></x-ui.table-cell>
                            <x-ui.table-cell>{{ $invoice->plan?->name ?: __('N/A') }}</x-ui.table-cell>
                            <x-ui.table-cell>{{ $invoice->from ?: __('N/A') }}</x-ui.table-cell>
                            <x-ui.table-cell>{{ ($invoice->currency ?: 'USD').' '.number_format((float) $invoice->amount, 2) }}</x-ui.table-cell>
                            <x-ui.table-cell><x-ui.badge :variant="$invoice->statusVariant()">{{ $invoice->statusLabel() }}</x-ui.badge></x-ui.table-cell>
                            <x-ui.table-cell>{{ $invoice->createdAtFormatted('Y-m-d H:i') ?: __('N/A') }}</x-ui.table-cell>
                            <x-ui.table-cell>
                                <x-ui.button href="{{ route('portal.invoices.download', $invoice) }}" variant="outline" size="sm">
                                    <i class="fa-light fa-download"></i>
                                    {{ __('Download PDF') }}
                                </x-ui.button>
                            </x-ui.table-cell>
                        </x-ui.table-row>
                    @empty
                        <x-ui.table-row>
                            <x-ui.table-cell colspan="8" class="py-10">
                                <x-ui.empty icon="fa-light fa-file-invoice" :title="__('No invoices recorded yet.')" :description="__('Payment records and downloadable invoices will appear here after checkout activity.')" />
                            </x-ui.table-cell>
                        </x-ui.table-row>
                    @endforelse
                </x-ui.table-body>
            </x-ui.table>

            @if ($invoices->hasPages())
                <div class="border-t px-6 py-4" style="border-color: var(--theme-border-color);">{{ $invoices->links() }}</div>
            @endif
        </x-ui.datatable-shell>
    </div>
@endcomponent
