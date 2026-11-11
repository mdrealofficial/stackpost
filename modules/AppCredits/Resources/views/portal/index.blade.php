@component(theme_view('layouts.app', 'app'), ['title' => __('Credit Usage')])
    @php
        $activeAction = request('action', '');
        $remainingCredits = ($creditSummary['unlimited'] ?? false) ? __('Unlimited') : number_format((int) ($creditSummary['remaining'] ?? 0));
        $creditTotal = ($creditSummary['unlimited'] ?? false)
            ? null
            : max(0, (int) ($creditSummary['used'] ?? 0) + (int) ($creditSummary['remaining'] ?? 0));
        $creditUsedPercent = (! ($creditSummary['unlimited'] ?? false) && $creditTotal > 0)
            ? min(100, max(0, round(((int) ($creditSummary['used'] ?? 0) / $creditTotal) * 100)))
            : 0;
        $creditRemainingPercent = ($creditSummary['unlimited'] ?? false) ? 100 : max(0, 100 - $creditUsedPercent);
        $creditMetrics = [
            [
                'label' => __('Entries'),
                'value' => number_format($entriesCount),
                'detail' => __('Logged AI Studio credit events.'),
                'icon' => 'fa-light fa-list-check',
                'tone' => 'rgb(37, 99, 235)',
                'iconSurface' => 'rgba(59, 130, 246, 0.10)',
                'spark' => 'M2 25 C10 25 12 15 20 18 S28 29 36 20 S44 10 52 16 S58 17 62 11',
            ],
            [
                'label' => __('Credits used'),
                'value' => number_format($creditsUsed),
                'detail' => __('Total credits consumed by AI Studio actions.'),
                'icon' => 'fa-light fa-bolt',
                'tone' => 'rgb(217, 119, 6)',
                'iconSurface' => 'rgba(245, 158, 11, 0.12)',
                'spark' => 'M2 21 C10 21 12 28 20 22 S29 10 36 17 S44 27 52 16 S58 18 62 13',
            ],
            [
                'label' => __('Actions used'),
                'value' => number_format($actionsCount),
                'detail' => __('Distinct AI Studio actions that consumed credits.'),
                'icon' => 'fa-light fa-wand-magic-sparkles',
                'tone' => 'rgb(126, 34, 206)',
                'iconSurface' => 'rgba(168, 85, 247, 0.10)',
                'spark' => 'M2 25 C10 25 12 14 20 18 S28 29 36 19 S44 10 52 16 S58 19 62 12',
            ],
            [
                'label' => __('Remaining'),
                'value' => $remainingCredits,
                'detail' => __('Credits still available for the current plan period.'),
                'icon' => 'fa-light fa-coins',
                'tone' => 'rgb(5, 150, 105)',
                'iconSurface' => 'rgba(16, 185, 129, 0.10)',
                'spark' => 'M2 19 C10 19 12 26 20 22 S29 10 36 17 S44 26 52 16 S58 17 62 12',
            ],
        ];
    @endphp

    <div class="space-y-6">
        <x-ui.sub-header :compact="true" :title="__('Credit Usage')" :description="__('Check your balance and review how credits were used.')"><x-slot:actions>
                        @if ($canBuyMoreCredits && $creditPacks->isNotEmpty())
                            <x-ui.button :href="route('portal.credits').'#credit-packs'" wire:navigate>
                                <i class="fa-light fa-coins"></i>
                                {{ __('Buy more credits') }}
                            </x-ui.button>
                        @endif
                        <x-ui.button :href="route('portal.ai-studio.prompt-history')" variant="outline" wire:navigate>
                            <i class="fa-light fa-clock-rotate-left"></i>
                            {{ __('Prompt History') }}
                        </x-ui.button>
                        <x-ui.button :href="route('portal.ai-studio.settings')" variant="outline" wire:navigate>
                            <i class="fa-light fa-sliders"></i>
                            {{ __('AI Settings') }}
                        </x-ui.button>
                    </x-slot:actions></x-ui.sub-header>

        <x-ui.metric-strip
            :items="$creditMetrics"
            columns="grid-cols-1 md:grid-cols-2 xl:grid-cols-4"
            gap="gap-4"
            variant="portal"
            :show-progress="false"
            aria-label="{{ __('Credit usage summary') }}"
        />

        @if ($canBuyMoreCredits && $creditPacks->isNotEmpty())
            <x-ui.card id="credit-packs" class="space-y-5">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em]" style="color: var(--theme-muted-text-color);">{{ __('Top-up store') }}</p>
                        <h3 class="mt-2 flex items-center gap-2 text-[1.3rem] font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">
                            <i class="fa-light fa-cart-shopping text-[var(--theme-accent)]"></i>
                            {{ __('Buy more credits') }}
                        </h3>
                        <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">
                            {{ __('Top-up credits are cumulative, non-expiring, and can be spent before plan credits when the engine priority is configured that way.') }}
                        </p>
                    </div>
                    <div class="rounded-[1rem] border px-4 py-3" style="border-color: var(--theme-border-color); background: var(--theme-surface-soft);">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em]" style="color: var(--theme-muted-text-color);">{{ __('Top-up balance') }}</p>
                        <p class="mt-2 text-xl font-semibold" style="color: var(--theme-header-text-color);">{{ number_format((int) $creditTopupRemaining) }}</p>
                    </div>
                </div>

                <div class="grid gap-5 lg:grid-cols-3">
                    @foreach ($creditPacks as $pack)
                        <div class="rounded-[1.15rem] border p-5 transition hover:-translate-y-1 hover:shadow-[0_24px_60px_-42px_rgba(15,23,42,0.32)]" style="border-color: {{ $pack->featured ? 'rgba(var(--theme-accent-rgb), 0.34)' : 'var(--theme-border-color)' }}; background: var(--theme-surface-base);">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-start gap-3">
                                    <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl" style="background: rgba(var(--theme-accent-rgb), 0.10); color: var(--theme-accent-color);">
                                        <i class="fa-light fa-coins"></i>
                                    </span>
                                    <div>
                                        <p class="font-semibold" style="color: var(--theme-header-text-color);">{{ $pack->name }}</p>
                                    @if ($pack->description)
                                        <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ $pack->description }}</p>
                                    @endif
                                    </div>
                                </div>
                                @if ($pack->featured)
                                    <x-ui.badge variant="success">{{ __('Featured') }}</x-ui.badge>
                                @endif
                            </div>

                            <div class="mt-4 flex items-end justify-between gap-4">
                                <div>
                                    <p class="text-3xl font-semibold tracking-[-0.04em]" style="color: var(--theme-header-text-color);">{{ number_format((int) $pack->credits) }}</p>
                                    <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Credits') }}</p>
                                </div>
                                <p class="text-xl font-semibold" style="color: var(--theme-header-text-color);">{{ $pack->currency_symbol }}{{ number_format((float) $pack->price, 2) }}</p>
                            </div>

                            <div class="mt-5">
                                <x-ui.button :href="route('payment.credits', $pack->slug)" class="w-full justify-center" wire:navigate>
                                    <i class="fa-light fa-plus"></i>
                                    {{ __('Buy more credits') }}
                                </x-ui.button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-ui.card>
        @endif

        <x-ui.card class="space-y-4">
            <form method="GET" action="{{ route('portal.credits') }}" class="grid gap-4 md:grid-cols-[240px_auto]">
                <x-ui.select name="action" :label="__('Action')">
                    <option value="">{{ __('All actions') }}</option>
                    @foreach ($actionOptions as $key => $label)
                        <option value="{{ $key }}" @selected($activeAction === $key)>{{ $label }}</option>
                    @endforeach
                </x-ui.select>
                <div class="flex items-end gap-3">
                    <x-ui.button type="submit"><i class="fa-light fa-filter"></i>{{ __('Filter') }}</x-ui.button>
                    <x-ui.button :href="route('portal.credits')" variant="outline" wire:navigate><i class="fa-light fa-rotate-left"></i>{{ __('Reset') }}</x-ui.button>
                </div>
            </form>
        </x-ui.card>

        @if ($topupLedger->isNotEmpty())
            <x-ui.datatable-shell :title="__('Top-up ledger')" :info="__('Credit pack purchases and top-up consumption entries recorded on this account.')">
                <x-ui.table class="rounded-none border-0 shadow-none">
                    <x-ui.table-head>
                        <x-ui.table-cell head>{{ __('Type') }}</x-ui.table-cell>
                        <x-ui.table-cell head>{{ __('Amount') }}</x-ui.table-cell>
                        <x-ui.table-cell head>{{ __('Remaining') }}</x-ui.table-cell>
                        <x-ui.table-cell head>{{ __('Pack') }}</x-ui.table-cell>
                        <x-ui.table-cell head>{{ __('Time') }}</x-ui.table-cell>
                    </x-ui.table-head>
                    <x-ui.table-body>
                        @foreach ($topupLedger as $entry)
                            <x-ui.table-row>
                                <x-ui.table-cell>
                                    <div class="flex items-center gap-3">
                                        <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl" style="background: rgba(var(--theme-accent-rgb), 0.10); color: var(--theme-accent-color);">
                                            <i class="fa-light {{ in_array($entry->type, ['purchase', 'adjustment'], true) ? 'fa-plus' : 'fa-minus' }}"></i>
                                        </span>
                                        <x-ui.badge :variant="in_array($entry->type, ['purchase', 'adjustment'], true) ? 'success' : 'neutral'">
                                            {{ __(ucfirst(str_replace('_', ' ', $entry->type))) }}
                                        </x-ui.badge>
                                    </div>
                                </x-ui.table-cell>
                                <x-ui.table-cell>
                                    <span class="font-semibold" style="color: var(--theme-header-text-color);">
                                        {{ $entry->amount > 0 ? '+' : '' }}{{ number_format((int) $entry->amount) }}
                                    </span>
                                </x-ui.table-cell>
                                <x-ui.table-cell>{{ number_format((int) $entry->remaining) }}</x-ui.table-cell>
                                <x-ui.table-cell>{{ $entry->creditPack?->name ?: __('Manual adjustment') }}</x-ui.table-cell>
                                <x-ui.table-cell>{{ $entry->created_at?->format('Y-m-d H:i:s') }}</x-ui.table-cell>
                            </x-ui.table-row>
                        @endforeach
                    </x-ui.table-body>
                </x-ui.table>
            </x-ui.datatable-shell>
        @endif

        <x-ui.datatable-shell :title="__('Usage log')" :info="__('Each row represents credits consumed by one AI Studio action.')">
            <x-ui.table class="rounded-none border-0 shadow-none">
                <x-ui.table-head>
                    <x-ui.table-cell head>{{ __('Action') }}</x-ui.table-cell>
                    <x-ui.table-cell head>{{ __('Credits') }}</x-ui.table-cell>
                    <x-ui.table-cell head>{{ __('Quantity') }}</x-ui.table-cell>
                    <x-ui.table-cell head>{{ __('Balance') }}</x-ui.table-cell>
                    <x-ui.table-cell head>{{ __('Metadata') }}</x-ui.table-cell>
                    <x-ui.table-cell head>{{ __('Time') }}</x-ui.table-cell>
                </x-ui.table-head>
                <x-ui.table-body>
                    @forelse ($logs as $log)
                        <x-ui.table-row>
                            <x-ui.table-cell>
                                <div class="flex items-start gap-3">
                                    <span class="mt-0.5 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl" style="background: rgba(var(--theme-accent-rgb), 0.10); color: var(--theme-accent-color);">
                                        <i class="fa-light fa-wand-magic-sparkles"></i>
                                    </span>
                                    <div class="space-y-1">
                                        <p class="font-semibold" style="color: var(--theme-header-text-color);">{{ $actionOptions[$log->action_key] ?? $log->action_key }}</p>
                                        @if ($log->feature)
                                            <p class="text-xs" style="color: var(--theme-muted-text-color);">{{ $log->feature }}</p>
                                        @endif
                                    </div>
                                </div>
                            </x-ui.table-cell>
                            <x-ui.table-cell><span class="font-semibold" style="color: var(--theme-header-text-color);">{{ number_format((int) $log->amount) }}</span></x-ui.table-cell>
                            <x-ui.table-cell>{{ number_format((int) $log->quantity) }}</x-ui.table-cell>
                            <x-ui.table-cell>
                                @if ($log->is_unlimited)
                                    <x-ui.badge variant="success">{{ __('Unlimited') }}</x-ui.badge>
                                @else
                                    <div class="space-y-1 text-sm">
                                        <p style="color: var(--theme-header-text-color);">{{ __('Before: :value', ['value' => number_format((int) $log->credits_before)]) }}</p>
                                        <p style="color: var(--theme-muted-text-color);">{{ __('After: :value', ['value' => number_format((int) $log->credits_after)]) }}</p>
                                    </div>
                                @endif
                            </x-ui.table-cell>
                            <x-ui.table-cell>
                                <p class="text-xs leading-6" style="color: var(--theme-muted-text-color);">
                                    {{ collect((array) $log->metadata)->map(fn ($value, $key) => $key.': '.(is_array($value) ? implode(', ', array_map('strval', $value)) : (is_scalar($value) || $value === null ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))))->implode(' • ') ?: '—' }}
                                </p>
                            </x-ui.table-cell>
                            <x-ui.table-cell>{{ $log->created_at?->format('Y-m-d H:i:s') }}</x-ui.table-cell>
                        </x-ui.table-row>
                    @empty
                        <x-ui.table-row>
                            <x-ui.table-cell colspan="6" class="py-10">
                                <x-ui.empty icon="fa-light fa-coins" :title="__('No credit usage logged yet.')" :description="__('AI Studio usage entries will appear here after credit-consuming actions run.')" />
                            </x-ui.table-cell>
                        </x-ui.table-row>
                    @endforelse
                </x-ui.table-body>
            </x-ui.table>
        </x-ui.datatable-shell>

        @if ($logs->hasPages())
            <div>
                {{ $logs->links() }}
            </div>
        @endif
    </div>
@endcomponent
