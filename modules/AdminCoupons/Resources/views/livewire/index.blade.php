@php
    $couponMetricCards = [
        [
            'label' => __('Total'),
            'value' => number_format($summary['total']),
            'description' => __('All discount codes currently stored.'),
            'tone' => 'var(--theme-accent)',
            'progress' => 100,
        ],
        [
            'label' => __('Enabled'),
            'value' => number_format($summary['enabled']),
            'description' => __('Coupons currently active for redemption.'),
            'tone' => '#10b981',
            'progress' => max(8, $summary['total'] > 0 ? (int) round(($summary['enabled'] / $summary['total']) * 100) : 8),
        ],
        [
            'label' => __('Unlimited'),
            'value' => number_format($summary['unlimited']),
            'description' => __('Coupons without a redemption cap.'),
            'tone' => '#f59e0b',
            'progress' => max(8, $summary['total'] > 0 ? (int) round(($summary['unlimited'] / $summary['total']) * 100) : 8),
        ],
    ];

    $statusLabels = [
        'all' => __('All'),
        '1' => __('Enabled'),
        '0' => __('Disabled'),
    ];

    $typeLabels = [
        'all' => __('All types'),
        '1' => __('Percent'),
        '2' => __('Price'),
    ];
@endphp

<div class="space-y-6">
    @if ($statusMessage)
        <x-ui.alert variant="success" :title="__('Saved')" :description="$statusMessage" dismissible />
    @endif

    <x-ui.page-hero
        :eyebrow="__('Finance')"
        :title="__('Coupons')"
        :description="__('Manage discount codes, validity windows, usage limits, and eligible plans from one workspace.')"
        :count="$summary['total']"
        icon="fa-light fa-ticket"
    >
        <x-slot:actions>
            <x-ui.button type="button" wire:click="openCreateModal">{{ __('Create coupon') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-hero>

    <x-ui.metric-strip :items="$couponMetricCards" :show-icons="false" />

    <x-ui.datatable-shell
        :title="__('Coupon directory')"
        :info="__('Discount codes, active windows, usage metrics, and plan targeting.')"
        header-class="py-4"
        eyebrow-class="text-[10px] tracking-[0.2em]"
        title-class="mt-1 text-[1.15rem] tracking-[-0.025em]"
        description-class="mt-1 leading-6"
    >
        <div class="space-y-4 border-b px-6 py-5" style="border-color: var(--theme-border-color);">
            <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_220px_220px_auto]">
                <x-ui.input wire:model.live.debounce.300ms="search" :label="__('Search')" :placeholder="__('Name, code, discount...')" />
                <x-ui.select wire:model.live="statusFilter" :label="__('Status')">
                    @foreach ($statusLabels as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.select wire:model.live="typeFilter" :label="__('Coupon by')">
                    @foreach ($typeLabels as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </x-ui.select>
                <div class="flex items-end gap-2">
                    <x-ui.button type="button" wire:click="$refresh">{{ __('Filter') }}</x-ui.button>
                    <x-ui.button type="button" variant="outline" wire:click="resetFilters">{{ __('Reset') }}</x-ui.button>
                </div>
            </div>

            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-wrap gap-2">
                    @if ($search !== '')
                        <span class="inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-medium" style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color);">{{ $search }}</span>
                    @endif
                    <span class="inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-medium" style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color);">
                        {{ __('Status') }}: {{ $statusLabels[$statusFilter] ?? __('All') }}
                    </span>
                    <span class="inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-medium" style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color);">
                        {{ __('Type') }}: {{ $typeLabels[$typeFilter] ?? __('All types') }}
                    </span>
                </div>

                <div class="flex min-w-0 items-center gap-3">
                    @if (count($selectedCouponIds) > 0)
                        <span class="text-sm font-medium" style="color: var(--theme-header-text-color);">
                            {{ trans_choice('{1} :count selected|[2,*] :count selected', count($selectedCouponIds), ['count' => count($selectedCouponIds)]) }}
                        </span>
                    @else
                        <span class="text-sm font-medium" style="color: var(--theme-header-text-color);">{{ trans_choice('{1} :count coupon|[2,*] :count coupons', $summary['total'], ['count' => $summary['total']]) }}</span>
                        <span class="text-sm" style="color: var(--theme-muted-text-color);">{{ __('Active') }}: {{ number_format($summary['enabled']) }}</span>
                    @endif

                    @if (count($selectedCouponIds) > 0)
                        <x-ui.dialog :title="__('Delete selected coupons?')" :description="__('This permanently removes all selected coupons from the directory.')" width="sm" dismissible>
                            <x-slot:trigger>
                                <x-ui.button type="button" variant="danger" size="sm">{{ __('Delete selected') }}</x-ui.button>
                            </x-slot:trigger>
                            <x-slot:footer>
                                <div class="flex justify-end gap-3">
                                    <x-ui.button type="button" variant="outline" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                                    <x-ui.button type="button" variant="danger" wire:click="deleteSelected" x-on:click="open = false">{{ __('Delete') }}</x-ui.button>
                                </div>
                            </x-slot:footer>
                        </x-ui.dialog>
                    @endif
                </div>
            </div>
        </div>

        <x-ui.table class="rounded-none border-0 shadow-none">
            <x-ui.table-head>
                <x-ui.table-cell head class="w-12">
                    <x-ui.checkbox wire:model.live="selectPage" minimal />
                </x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Coupon') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Discount') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Usage') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Validity') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Status') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Actions') }}</x-ui.table-cell>
            </x-ui.table-head>
            <x-ui.table-body>
                @forelse ($coupons as $coupon)
                    <x-ui.table-row wire:key="coupon-row-{{ $coupon->id }}">
                        <x-ui.table-cell>
                            <x-ui.checkbox value="{{ $coupon->id }}" wire:model.live="selectedCouponIds" minimal />
                        </x-ui.table-cell>
                        <x-ui.table-cell>
                            <div class="space-y-1">
                                <p class="font-semibold uppercase" style="color: var(--theme-header-text-color);">{{ $coupon->code }}</p>
                                <p class="text-sm" style="color: var(--theme-muted-text-color);">{{ $coupon->name }}</p>
                            </div>
                        </x-ui.table-cell>
                        <x-ui.table-cell>
                            <div class="space-y-1">
                                <p class="font-medium" style="color: var(--theme-header-text-color);">{{ $coupon->discountLabel() }}</p>
                                <p class="text-xs" style="color: var(--theme-muted-text-color);">{{ $coupon->typeLabel() }}</p>
                            </div>
                        </x-ui.table-cell>
                        <x-ui.table-cell>
                            <div class="space-y-1">
                                <p class="text-sm" style="color: var(--theme-header-text-color);">{{ number_format((int) $coupon->usage_count) }}</p>
                                <p class="text-xs" style="color: var(--theme-muted-text-color);">{{ __('Limit') }}: {{ $coupon->usageLimitLabel() }}</p>
                            </div>
                        </x-ui.table-cell>
                        <x-ui.table-cell>
                            <div class="space-y-1">
                                <p class="text-sm" style="color: var(--theme-header-text-color);">{{ $coupon->startDateFormatted() ?: __('N/A') }}</p>
                                <p class="text-xs" style="color: var(--theme-muted-text-color);">{{ __('Until') }}: {{ $coupon->endDateFormatted() }}</p>
                            </div>
                        </x-ui.table-cell>
                        <x-ui.table-cell>
                            <x-ui.badge :variant="$coupon->statusVariant()">{{ $coupon->statusLabel() }}</x-ui.badge>
                        </x-ui.table-cell>
                        <x-ui.table-cell>
                            <div class="flex items-center gap-2">
                                <x-ui.button type="button" variant="outline" size="sm" wire:click="openEditModal({{ $coupon->id }})">{{ __('Edit') }}</x-ui.button>
                                <x-ui.dialog :title="__('Delete coupon?')" :description="__('This action permanently removes the coupon and its redemption configuration.')" width="sm" dismissible>
                                    <x-slot:trigger>
                                        <x-ui.button type="button" variant="danger" size="sm">{{ __('Delete') }}</x-ui.button>
                                    </x-slot:trigger>
                                    <x-slot:footer>
                                        <div class="flex justify-end gap-3">
                                            <x-ui.button type="button" variant="outline" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                                            <x-ui.button type="button" variant="danger" wire:click="delete({{ $coupon->id }})" x-on:click="open = false">{{ __('Delete') }}</x-ui.button>
                                        </div>
                                    </x-slot:footer>
                                </x-ui.dialog>
                            </div>
                        </x-ui.table-cell>
                    </x-ui.table-row>
                @empty
                    <x-ui.table-row>
                        <x-ui.table-cell colspan="7" class="py-10">
                            <x-ui.empty
                                icon="fa-light fa-ticket"
                                :title="__('No coupons found.')"
                                :description="__('Try broadening your filters or create your first coupon.')"
                            >
                                <x-ui.button type="button" wire:click="openCreateModal">{{ __('Create coupon') }}</x-ui.button>
                            </x-ui.empty>
                        </x-ui.table-cell>
                    </x-ui.table-row>
                @endforelse
            </x-ui.table-body>
        </x-ui.table>

        @if ($coupons->hasPages())
            <div class="border-t px-6 py-4" style="border-color: var(--theme-border-color);">
                {{ $coupons->links() }}
            </div>
        @endif
    </x-ui.datatable-shell>

    <x-ui.modal
        width="xl"
        open-event="coupon-modal-open"
        close-event="coupon-modal-close"
        :title="$isEditingCoupon ? __('Edit coupon') : __('Create coupon')"
        :description="$isEditingCoupon ? __('Modify discount rules, eligible plans, active dates, and redemption limits.') : __('Create a discount code for selected paid plans with start and end dates.')"
        body-class="space-y-6 overflow-visible max-h-none px-6 py-6"
    >
        <form id="coupon-modal-form" wire:submit="saveCoupon" class="space-y-6">
            <section class="space-y-5">
                <div class="space-y-1">
                    <h4 class="text-sm font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Coupon information') }}</h4>
                    <p class="text-sm" style="color: var(--theme-muted-text-color);">{{ __('Define status, discount type, redemption limit, and active period.') }}</p>
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <x-ui.field :label="__('Status')" :error="$errors->first('status')">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <x-ui.radio name="coupon-status" value="1" wire:model="status" :label="__('Enable')" />
                            <x-ui.radio name="coupon-status" value="0" wire:model="status" :label="__('Disable')" />
                        </div>
                    </x-ui.field>

                    <x-ui.field :label="__('Coupon by')" :error="$errors->first('type')">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <x-ui.radio name="coupon-type" value="1" wire:model="type" :label="__('Percent')" />
                            <x-ui.radio name="coupon-type" value="2" wire:model="type" :label="__('Price')" />
                        </div>
                    </x-ui.field>
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    <x-ui.input wire:model.defer="name" :label="__('Name')" :error="$errors->first('name')" required autofocus />
                    <x-ui.input wire:model.defer="code" :label="__('Code')" :error="$errors->first('code')" required />
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    <x-ui.input wire:model.defer="discount" type="number" step="0.01" min="0" :label="__('Discount value')" :error="$errors->first('discount')" required />
                    <x-ui.input wire:model.defer="usage_limit" type="number" min="-1" :label="__('Usage limit')" :error="$errors->first('usage_limit')" :help="__('Use -1 for unlimited usage.')" required />
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    <x-ui.date-picker wire:model.defer="start_date" name="start_date" :label="__('Start date')" :value="$start_date" :error="$errors->first('start_date')" :placeholder="__('Choose start date')" />
                    <x-ui.date-picker wire:model.defer="end_date" name="end_date" :label="__('End date')" :value="$end_date" :error="$errors->first('end_date')" :placeholder="__('Choose end date')" />
                </div>

                <p class="text-sm" style="color: var(--theme-muted-text-color);">{{ __('Leave empty if you want no expiry date.') }}</p>
            </section>

            <div class="border-t" style="border-color: var(--theme-border-color);"></div>

            <section class="space-y-5">
                @php
                    $planIds = collect($planOptions)->pluck('key')->all();
                    $selectedCount = count(array_intersect($plans, $planIds));
                    $allPlansSelected = ! empty($planIds) && $selectedCount === count($planIds);
                @endphp

                <div class="space-y-1">
                    <h4 class="text-sm font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Eligible plans') }}</h4>
                    <p class="text-sm" style="color: var(--theme-muted-text-color);">{{ __('Coupons only apply to the selected paid plans.') }}</p>
                </div>

                <div class="flex items-center gap-2">
                    <x-ui.button type="button" size="sm" variant="outline" wire:click="selectAllPlans" :disabled="empty($planIds) || $allPlansSelected">
                        {{ __('Select all') }}
                    </x-ui.button>
                    <x-ui.button type="button" size="sm" variant="outline" wire:click="clearPlans" :disabled="count($plans) === 0">
                        {{ __('Clear') }}
                    </x-ui.button>
                </div>

                <x-ui.field :label="__('Allow for plans')" :help="__('Coupons only apply to the selected paid plans.')" :error="$errors->first('plans')">
                    <div class="grid gap-x-6 gap-y-4 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($planOptions as $plan)
                            <x-ui.checkbox
                                wire:model.live="plans"
                                :value="$plan['key']"
                                :label="$plan['label']"
                            />
                        @endforeach
                    </div>
                </x-ui.field>
            </section>
        </form>

        <x-slot:footer>
            <x-ui.button type="button" variant="outline" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
            <x-ui.button type="submit" form="coupon-modal-form">{{ $isEditingCoupon ? __('Save changes') : __('Create coupon') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
