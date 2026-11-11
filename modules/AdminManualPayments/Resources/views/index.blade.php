@component(theme_view('layouts.app', 'app'), ['title' => __('Manual Payments')])
    @php
        $createManualPayment = new \Modules\AdminManualPayments\Models\ManualPayment();
        $createManualPayment->payment_id = old('_manual_payment_modal') === 'create' ? old('payment_id') : null;
        $createManualPayment->payment_info = old('_manual_payment_modal') === 'create' ? old('payment_info') : null;
        $createManualPayment->amount = old('_manual_payment_modal') === 'create' ? old('amount') : null;
        $createManualPayment->currency = old('_manual_payment_modal') === 'create' ? old('currency', 'USD') : 'USD';
        $createManualPayment->notes = old('_manual_payment_modal') === 'create' ? old('notes') : null;
        $openCreateModal = $errors->any() && old('_manual_payment_modal') === 'create';
        $metricCards = [
            ['label' => __('Total'), 'value' => number_format($summary['total']), 'description' => __('All manual payment requests currently stored.'), 'icon' => 'fa-light fa-wallet', 'tone' => 'var(--theme-accent)', 'progress' => $summary['total'] > 0 ? 100 : 0],
            ['label' => __('Pending'), 'value' => number_format($summary['pending']), 'description' => __('Requests waiting for finance review.'), 'icon' => 'fa-light fa-hourglass-half', 'tone' => 'rgb(217 119 6)', 'progress' => $summary['total'] > 0 ? (int) round(($summary['pending'] / max($summary['total'], 1)) * 100) : 0],
            ['label' => __('Approved Gross'), 'value' => number_format($summary['gross'], 2), 'description' => __('Approved manual payment amount total.'), 'icon' => 'fa-light fa-circle-check', 'tone' => 'rgb(5 150 105)', 'progress' => $summary['gross'] > 0 ? 100 : 0],
        ];
    @endphp

    <div class="space-y-6">
        

        <x-ui.sub-header
            :eyebrow="__('Finance')"
            :title="__('Manual Payments')"
            :description="__('Review submitted manual payments, approve valid requests, and activate plans from offline transfers.')"
            :count="$summary['total']"
        >
            <x-slot:actions>
                <x-ui.modal
                    width="lg"
                    :title="__('Create manual payment')"
                    :description="__('Create an offline payment entry and optionally approve it immediately.')"
                    :initially-open="$openCreateModal"
                >
                    <x-slot:trigger>
                        <x-ui.button type="button">{{ __('Create payment') }}</x-ui.button>
                    </x-slot:trigger>

                    <form id="create-manual-payment-modal-form" method="POST" action="{{ route('admin-manual-payments.store') }}" class="space-y-5">
                        @csrf
                        <input type="hidden" name="_manual_payment_modal" value="create">

                        @include('adminmanualpayments::partials.fields', [
                            'manualPayment' => $createManualPayment,
                            'plainSections' => true,
                            'planOptions' => $planOptions,
                            'selectedUserOption' => $selectedUserOption ?? [],
                        ])
                    </form>

                    <x-slot:footer>
                        <x-ui.button type="button" variant="outline" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                        <x-ui.button type="submit" form="create-manual-payment-modal-form">{{ __('Save changes') }}</x-ui.button>
                    </x-slot:footer>
                </x-ui.modal>
            </x-slot:actions>
        </x-ui.sub-header>

        <x-ui.metric-strip :items="$metricCards" :show-progress="false" />

        <x-ui.card>
            <form method="GET" action="{{ route('admin-manual-payments.index') }}" class="grid gap-4 md:grid-cols-[minmax(0,1fr)_220px_auto]">
                <x-ui.input name="q" :label="__('Search')" :value="$filters['q']" :placeholder="__('User, payment ID, notes...')" />
                <x-ui.select name="status" :label="__('Status')">
                    <option value="all" @selected($filters['status'] === 'all')>{{ __('All') }}</option>
                    <option value="0" @selected((string) $filters['status'] === '0')>{{ __('Pending') }}</option>
                    <option value="1" @selected((string) $filters['status'] === '1')>{{ __('Approved') }}</option>
                    <option value="2" @selected((string) $filters['status'] === '2')>{{ __('Cancelled') }}</option>
                </x-ui.select>
                <div class="flex items-end gap-3">
                    <x-ui.button type="submit">{{ __('Filter') }}</x-ui.button>
                    <x-ui.button href="{{ route('admin-manual-payments.index') }}" variant="outline" wire:navigate>{{ __('Reset') }}</x-ui.button>
                </div>
            </form>
        </x-ui.card>

        <x-ui.datatable-shell :title="__('Manual payment queue')" :info="__('Offline payments submitted by users, along with approval controls and assigned plans.')">
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
                        @php
                            $editModalOpen = $errors->any() && old('_manual_payment_modal') === 'edit' && (string) old('_manual_payment_id') === (string) $manualPayment->id;
                            $editManualPayment = clone $manualPayment;
                            if ($editModalOpen) {
                                $editManualPayment->payment_id = old('payment_id');
                                $editManualPayment->payment_info = old('payment_info');
                                $editManualPayment->amount = old('amount');
                                $editManualPayment->currency = old('currency');
                                $editManualPayment->notes = old('notes');
                                $editManualPayment->status = old('status');
                                $editManualPayment->plan_id = old('plan_id');
                                $editManualPayment->uid = old('uid');
                            }
                        @endphp
                        <x-ui.table-row>
                            <x-ui.table-cell><div class="space-y-1"><p class="font-medium" style="color: var(--theme-header-text-color);">{{ $manualPayment->user?->name ?: __('Unknown user') }}</p><p class="text-xs" style="color: var(--theme-muted-text-color);">{{ $manualPayment->user?->email ?: __('No email') }}</p></div></x-ui.table-cell>
                            <x-ui.table-cell><div class="space-y-1"><p class="text-sm font-medium" style="color: var(--theme-header-text-color);">{{ $manualPayment->plan?->name ?: __('N/A') }}</p><p class="text-xs" style="color: var(--theme-muted-text-color);">{{ match((int) ($manualPayment->plan?->type ?? 1)) { 2 => __('Yearly'), 3 => __('Lifetime'), default => __('Monthly') } }}</p></div></x-ui.table-cell>
                            <x-ui.table-cell><div class="space-y-1"><p class="max-w-[12rem] truncate text-sm font-medium" style="color: var(--theme-header-text-color);" title="{{ $manualPayment->payment_id }}">{{ $manualPayment->payment_id }}</p><p class="max-w-[12rem] truncate text-xs" style="color: var(--theme-muted-text-color);" title="{{ $manualPayment->payment_info }}">{{ $manualPayment->payment_info ?: __('No payment info') }}</p></div></x-ui.table-cell>
                            <x-ui.table-cell><div class="space-y-1"><p class="text-sm font-medium" style="color: var(--theme-header-text-color);">{{ $manualPayment->amountLabel() }}</p><p class="text-xs" style="color: var(--theme-muted-text-color);">{{ $manualPayment->notes ?: __('No notes') }}</p></div></x-ui.table-cell>
                            <x-ui.table-cell><p class="text-sm" style="color: var(--theme-muted-text-color);">{{ $manualPayment->createdAtFormatted() ?: __('N/A') }}</p></x-ui.table-cell>
                            <x-ui.table-cell><x-ui.badge :variant="$manualPayment->statusVariant()">{{ $manualPayment->statusLabel() }}</x-ui.badge></x-ui.table-cell>
                            <x-ui.table-cell>
                                <div class="flex flex-wrap items-center gap-2">
                                    @if ((int) $manualPayment->status !== 1)
                                        <form method="POST" action="{{ route('admin-manual-payments.status', ['manualPayment' => $manualPayment, 'status' => 'approve']) }}">@csrf <x-ui.button type="submit" size="sm">{{ __('Approve') }}</x-ui.button></form>
                                    @endif
                                    @if ((int) $manualPayment->status !== 2)
                                        <form method="POST" action="{{ route('admin-manual-payments.status', ['manualPayment' => $manualPayment, 'status' => 'cancel']) }}">@csrf <x-ui.button type="submit" variant="outline" size="sm">{{ __('Cancel') }}</x-ui.button></form>
                                    @endif
                                    <x-ui.button :href="route('admin-manual-payments.edit', $manualPayment)" variant="outline" size="sm" wire:navigate>{{ __('Edit') }}</x-ui.button>
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
@endcomponent
