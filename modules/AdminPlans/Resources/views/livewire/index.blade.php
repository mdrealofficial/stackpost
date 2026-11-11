@php
    $totalPlans = $plans->count();
    $activePlans = $plans->where('status', true)->count();
    $featuredPlans = $plans->where('featured', true)->count();
    $totalAssignments = $plans->sum('users_count');

    $planMetricCards = [
        [
            'label' => __('Total'),
            'value' => number_format($totalPlans),
            'description' => __('Pricing tiers currently stored.'),
            'tone' => 'var(--theme-accent)',
            'progress' => 100,
        ],
        [
            'label' => __('Active'),
            'value' => number_format($activePlans),
            'description' => __('Plans currently assignable to users.'),
            'tone' => '#10b981',
            'progress' => max(8, $totalPlans > 0 ? (int) round(($activePlans / $totalPlans) * 100) : 8),
        ],
        [
            'label' => __('Featured'),
            'value' => number_format($featuredPlans),
            'description' => __('Plans highlighted as primary offers.'),
            'tone' => '#f59e0b',
            'progress' => max(8, $totalPlans > 0 ? (int) round(($featuredPlans / $totalPlans) * 100) : 8),
        ],
    ];

    $billingTypes = [
        'all' => __('All billing cycles'),
        '1' => __('Monthly'),
        '2' => __('Yearly'),
        '3' => __('Lifetime'),
    ];

    $statusLabels = [
        'all' => __('All'),
        '1' => __('Enabled'),
        '0' => __('Disabled'),
    ];

    $featuredLabels = [
        'all' => __('All plans'),
        '1' => __('Featured only'),
        '0' => __('Standard only'),
    ];
@endphp

<div class="space-y-6">
    

    <x-ui.page-hero
        :eyebrow="__('Finance')"
        :title="__('Plans')"
        :description="__('Manage subscription tiers, pricing rules, and feature access from one workspace.')"
        :count="$totalPlans"
        icon="fa-light fa-layer-group"
    >
        <x-slot:actions>
            <x-ui.button :href="route('admin-plans.create')" wire:navigate>{{ __('Create plan') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-hero>

    <x-ui.metric-strip :items="$planMetricCards" :show-icons="false" />

    <x-ui.filter-panel fields-class="xl:grid-cols-[minmax(0,1fr)_220px_220px_220px_auto]">
        <x-slot:fields>
            <x-ui.input wire:model.live.debounce.300ms="search" :label="__('Search')" :placeholder="__('Name, slug, description...')" />
            <x-ui.select wire:model.live="statusFilter" :label="__('Status')">
                @foreach ($statusLabels as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </x-ui.select>
            <x-ui.select wire:model.live="billingFilter" :label="__('Billing cycle')">
                @foreach ($billingTypes as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </x-ui.select>
            <x-ui.select wire:model.live="featuredFilter" :label="__('Featured')">
                @foreach ($featuredLabels as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </x-ui.select>
        </x-slot:fields>

        <x-slot:actions>
            <x-ui.button type="button" wire:click="$refresh">{{ __('Filter') }}</x-ui.button>
            <x-ui.button type="button" variant="outline" wire:click="resetFilters">{{ __('Reset') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.filter-panel>

    <x-ui.bulk-toolbar compact>
        <x-slot:chips>
            @if ($search !== '')
                <span class="inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-medium" style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color);">{{ $search }}</span>
            @endif
            <span class="inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-medium" style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color);">
                {{ __('Status') }}: {{ $statusLabels[$statusFilter] ?? __('All') }}
            </span>
            <span class="inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-medium" style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color);">
                {{ __('Billing') }}: {{ $billingTypes[$billingFilter] ?? __('All billing cycles') }}
            </span>
            <span class="inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-medium" style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color);">
                {{ __('Featured') }}: {{ $featuredLabels[$featuredFilter] ?? __('All plans') }}
            </span>
        </x-slot:chips>

        <x-slot:selection>
            <div class="flex min-w-0 items-center gap-3">
                <span class="text-sm font-medium" style="color: var(--theme-header-text-color);">{{ trans_choice('{1} :count plan|[2,*] :count plans', $totalPlans, ['count' => $totalPlans]) }}</span>
                <span class="text-sm" style="color: var(--theme-muted-text-color);">{{ __('Assignments') }}: {{ number_format($totalAssignments) }}</span>
            </div>
        </x-slot:selection>
    </x-ui.bulk-toolbar>

    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($plans as $plan)
            @php
                $billingLabel = match((int) $plan->type) {
                    2 => __('Yearly'),
                    3 => __('Lifetime'),
                    default => __('Monthly'),
                };

                $featureTags = collect($plan->permissions ?? [])
                    ->filter(fn ($value) => is_array($value) || $value === 1 || $value === '1' || $value === true)
                    ->keys()
                    ->take(3)
                    ->values();
            @endphp

            <x-ui.card class="space-y-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="truncate font-semibold" style="color: var(--theme-header-text-color);">{{ $plan->name }}</p>
                            @if ($plan->featured)
                                <x-ui.badge variant="primary">{{ __('Featured') }}</x-ui.badge>
                            @endif
                            @if ($plan->free_plan)
                                <x-ui.badge variant="neutral">{{ __('Free') }}</x-ui.badge>
                            @endif
                            @if ($plan->default_signup_plan)
                                <x-ui.badge variant="success">{{ __('Signup default') }}</x-ui.badge>
                            @endif
                        </div>
                        <p class="mt-1 truncate text-xs font-mono" style="color: var(--theme-muted-text-color);">/{{ $plan->slug }}</p>
                    </div>
                    <x-ui.badge :variant="$plan->status ? 'success' : 'neutral'">{{ $plan->status ? __('Enabled') : __('Disabled') }}</x-ui.badge>
                </div>

                <x-ui.surface-card padding="sm" :accent="$plan->featured ? 'warning' : ($plan->status ? 'success' : 'neutral')" :featured="$plan->featured">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.16em]" style="color: var(--theme-muted-text-color);">{{ __('Pricing') }}</p>
                            <p class="mt-2 text-[1.75rem] font-semibold tracking-[-0.04em]" style="color: var(--theme-header-text-color);">{{ $plan->currency_symbol }} {{ number_format((float) $plan->price, 2) }}</p>
                            <p class="mt-1 text-xs font-medium" style="color: var(--theme-muted-text-color);">{{ $plan->currency }} · {{ $plan->currency_name }}</p>
                        </div>
                        <div class="text-right text-sm" style="color: var(--theme-muted-text-color);">
                            <p>{{ $billingLabel }}</p>
                            <p>{{ __('Trial') }}: {{ (int) $plan->trial_day }}</p>
                        </div>
                    </div>
                </x-ui.surface-card>

                <div class="grid gap-3 sm:grid-cols-2">
                    <x-ui.metric-card
                        :label="__('Assignments')"
                        :value="number_format($plan->users_count)"
                        :description="__('Users attached to this plan.')"
                        icon="fa-light fa-users"
                        accent="neutral"
                        padding="sm"
                        class="min-h-[auto]"
                    />
                    <x-ui.metric-card
                        :label="__('Position')"
                        :value="number_format((int) ($plan->position ?? 0))"
                        :description="__('Display order in plan listings.')"
                        icon="fa-light fa-arrow-down-1-9"
                        accent="neutral"
                        padding="sm"
                        class="min-h-[auto]"
                    />
                </div>

                <div class="space-y-3">
                    <p class="text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ $plan->desc ?: __('No description provided for this plan yet.') }}</p>

                    <div class="flex flex-wrap gap-2">
                        <x-ui.badge>{{ $billingLabel }}</x-ui.badge>
                        @foreach ($featureTags as $featureTag)
                            <x-ui.badge variant="primary">{{ str($featureTag)->replace('_', ' ')->title() }}</x-ui.badge>
                        @endforeach
                        @if ($featureTags->isEmpty())
                            <x-ui.badge variant="neutral">{{ __('No feature flags') }}</x-ui.badge>
                        @endif
                    </div>
                </div>

                <div class="flex items-center justify-between gap-4 border-t pt-4" style="border-color: var(--theme-border-color);">
                    <div class="text-xs" style="color: var(--theme-muted-text-color);">
                        {{ __('Updated') }}: {{ optional($plan->updated_at)->format('M d, Y') ?: __('N/A') }}
                    </div>
                    <div class="flex items-center gap-2">
                        <x-ui.button
                            type="button"
                            size="sm"
                            variant="{{ $plan->status ? 'secondary' : 'success' }}"
                            wire:click="toggleStatus({{ $plan->id }})"
                            wire:loading.attr="disabled"
                        >
                            {{ $plan->status ? __('Disable') : __('Enable') }}
                        </x-ui.button>

                        <x-ui.dialog
                            :title="__('Delete this plan?')"
                            :description="__('This removes the plan and clears it from users currently assigned to it.')"
                            width="sm"
                            dismissible
                        >
                            <x-slot:trigger>
                                <x-ui.button type="button" variant="danger" size="sm">{{ __('Delete') }}</x-ui.button>
                            </x-slot:trigger>
                            <x-slot:footer>
                                <div class="flex justify-end gap-3">
                                    <x-ui.button type="button" variant="outline" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                                    <x-ui.button
                                        type="button"
                                        variant="danger"
                                        wire:click="deletePlan({{ $plan->id }})"
                                        wire:loading.attr="disabled"
                                        x-on:click="open = false"
                                    >
                                        {{ __('Delete') }}
                                    </x-ui.button>
                                </div>
                            </x-slot:footer>
                        </x-ui.dialog>

                        <x-ui.button :href="route('admin-plans.edit', $plan)" variant="outline" size="sm" wire:navigate>{{ __('Edit') }}</x-ui.button>
                    </div>
                </div>
            </x-ui.card>
        @empty
            <x-ui.card class="md:col-span-2 xl:col-span-3">
                <x-ui.empty
                    class="py-12"
                    icon="fa-light fa-layer-group"
                    :title="__('No plans found.')"
                    :description="__('Try broadening your filters or create your first pricing plan.')"
                >
                    <x-ui.button :href="route('admin-plans.create')" wire:navigate>{{ __('Create plan') }}</x-ui.button>
                </x-ui.empty>
            </x-ui.card>
        @endforelse
    </div>
</div>
