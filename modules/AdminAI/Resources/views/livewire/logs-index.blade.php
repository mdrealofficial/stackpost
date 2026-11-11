@php
    $totalLogs = $logs->count();
    $successfulLogs = $logs->where('status', 'success')->count();
    $failedLogs = $logs->where('status', 'failed')->count();
    $totalTokens = (int) $logs->sum('total_tokens');
@endphp

<div class="mx-auto max-w-[88rem] space-y-6">
    <x-ui.page-hero
        :eyebrow="__('AI observability')"
        :title="__('AI Usage Logs')"
        :description="__('Review provider traffic, user activity, request health, and usage metadata from one audit surface.')"
        :count="$totalLogs"
        icon="fa-light fa-waveform-lines"
    >
        <x-slot:actions>
            <x-ui.button href="{{ route('admin-ai-report.index') }}" variant="outline" wire:navigate>{{ __('Open report') }}</x-ui.button>
            <x-ui.button href="{{ route('settings.ai') }}" wire:navigate>{{ __('Open AI config') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-hero>

    <x-ui.metric-strip
        :items="[
            ['label' => __('Total'), 'value' => number_format($totalLogs), 'description' => __('Latest log records returned by the current query window.'), 'progress' => 100, 'tone' => 'var(--theme-accent)'],
            ['label' => __('Success'), 'value' => number_format($successfulLogs), 'description' => __('Requests completed without AI provider errors.'), 'progress' => $totalLogs > 0 ? (int) round(($successfulLogs / $totalLogs) * 100) : 0, 'tone' => 'var(--theme-success-color)'],
            ['label' => __('Failed'), 'value' => number_format($failedLogs), 'description' => __('Requests marked failed or returned an error state.'), 'progress' => $totalLogs > 0 ? (int) round(($failedLogs / $totalLogs) * 100) : 0, 'tone' => 'var(--theme-danger-color)'],
            ['label' => __('Tokens'), 'value' => number_format($totalTokens), 'description' => __('Token volume across the visible AI usage records.'), 'progress' => $totalTokens > 0 ? 100 : 0, 'tone' => 'var(--theme-warning-color)'],
        ]"
        :show-icons="false"
        columns="md:grid-cols-2 xl:grid-cols-4"
    />

    <x-ui.card class="relative border p-0 overflow-visible" style="border-color: var(--theme-border-color); background:
        radial-gradient(circle at top right, rgba(var(--theme-accent-rgb), 0.08), transparent 24%),
        linear-gradient(180deg,
            color-mix(in srgb, var(--theme-surface-base) 98%, transparent) 0%,
            color-mix(in srgb, var(--theme-surface-soft) 62%, var(--theme-surface-base)) 100%
        );">
        <div class="border-b px-6 py-5" style="border-color: rgba(var(--theme-border-color-rgb), 0.72);">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-2xl">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-accent);">{{ __('Query console') }}</p>
                    <h2 class="mt-2 text-[1.2rem] font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">{{ __('Refine AI traffic signals') }}</h2>
                    <p class="mt-1 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Search users, narrow providers and capabilities, then constrain the time window to inspect a cleaner request timeline.') }}</p>
                </div>

                <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto_auto] xl:min-w-[42rem]">
                    <x-ui.input
                        wire:model.live.debounce.300ms="userSearch"
                        :label="__('User search')"
                        :placeholder="__('Search by name, username, or email')"
                    />
                    <div class="flex items-end">
                        <x-ui.button type="button" class="w-full sm:w-auto" wire:click="$refresh">{{ __('Refresh') }}</x-ui.button>
                    </div>
                    <div class="flex items-end">
                        <x-ui.button type="button" variant="outline" class="w-full sm:w-auto" wire:click="resetFilters">{{ __('Reset') }}</x-ui.button>
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-5 px-6 py-6">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-5">
                <x-ui.select wire:model.live="provider" :label="__('Provider')">
                    <option value="">{{ __('All providers') }}</option>
                    @foreach ($providers as $providerOption)
                        <option value="{{ $providerOption }}">{{ ucfirst($providerOption) }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.select wire:model.live="capability" :label="__('Capability')">
                    <option value="">{{ __('All capabilities') }}</option>
                    @foreach ($capabilities as $capabilityOption)
                        <option value="{{ $capabilityOption }}">{{ ucfirst($capabilityOption) }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.select wire:model.live="status" :label="__('Status')">
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach ($statuses as $statusOption)
                        <option value="{{ $statusOption }}">{{ ucfirst($statusOption) }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.date-picker wire:model.live="dateFrom" :label="__('Date from')" :placeholder="__('Choose start date')" />
                <x-ui.date-picker wire:model.live="dateTo" :label="__('Date to')" :placeholder="__('Choose end date')" />
            </div>

            <div class="flex flex-wrap gap-2">
                <x-ui.badge variant="neutral">{{ __('Window') }}: {{ number_format($totalLogs) }} {{ __('records') }}</x-ui.badge>
                @if ($provider !== '')
                    <x-ui.badge variant="neutral">{{ __('Provider') }}: {{ ucfirst($provider) }}</x-ui.badge>
                @endif
                @if ($capability !== '')
                    <x-ui.badge variant="neutral">{{ __('Capability') }}: {{ ucfirst($capability) }}</x-ui.badge>
                @endif
                @if ($status !== '')
                    <x-ui.badge variant="neutral">{{ __('Status') }}: {{ ucfirst($status) }}</x-ui.badge>
                @endif
                @if ($dateFrom !== '' || $dateTo !== '')
                    <x-ui.badge variant="neutral">{{ __('Range') }}: {{ $dateFrom !== '' ? $dateFrom : __('Start') }} -> {{ $dateTo !== '' ? $dateTo : __('Now') }}</x-ui.badge>
                @endif
            </div>
        </div>
    </x-ui.card>

    <x-ui.datatable-shell :title="__('Usage timeline')" :info="__('Latest 100 AI requests after applying the current filters.')" :footer-text="number_format($totalLogs).' '.__('entries shown')" header-class="py-4" eyebrow-class="text-[10px] tracking-[0.2em]" title-class="mt-1 text-[1.15rem] tracking-[-0.025em]" description-class="mt-1 leading-6">
        <x-ui.table class="rounded-none border-0 shadow-none">
            <x-ui.table-head>
                <x-ui.table-cell head>{{ __('User') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Provider') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Capability') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Model') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Status') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Usage') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Date') }}</x-ui.table-cell>
            </x-ui.table-head>
            <x-ui.table-body>
                @forelse ($logs as $log)
                    <x-ui.table-row>
                        <x-ui.table-cell><div class="space-y-1"><p class="font-semibold" style="color: var(--theme-header-text-color);">{{ $log->user?->name ?? __('System') }}</p><p class="text-xs" style="color: var(--theme-muted-text-color);">{{ $log->user ? '@'.$log->user->username.' | '.$log->user->email : __('No user attached') }}</p></div></x-ui.table-cell>
                        <x-ui.table-cell><x-ui.badge variant="neutral">{{ strtoupper($log->provider) }}</x-ui.badge></x-ui.table-cell>
                        <x-ui.table-cell><p class="font-semibold" style="color: var(--theme-header-text-color);">{{ ucfirst($log->capability) }}</p></x-ui.table-cell>
                        <x-ui.table-cell><div class="space-y-1"><p class="break-all font-semibold" style="color: var(--theme-header-text-color);">{{ $log->model }}</p>@if ($log->feature || $log->route_name)<p class="text-xs" style="color: var(--theme-muted-text-color);">{{ collect([$log->feature, $log->route_name])->filter()->implode(' | ') }}</p>@endif</div></x-ui.table-cell>
                        <x-ui.table-cell><x-ui.badge :variant="$log->status === 'success' ? 'success' : ($log->status === 'failed' ? 'danger' : 'neutral')">{{ strtoupper($log->status) }}</x-ui.badge></x-ui.table-cell>
                        <x-ui.table-cell><div class="space-y-1"><p class="font-medium" style="color: var(--theme-header-text-color);">{{ __('Tokens: :count', ['count' => number_format((int) ($log->total_tokens ?? 0))]) }}</p><p class="text-xs" style="color: var(--theme-muted-text-color);">{{ collect([$log->prompt_tokens !== null ? 'prompt: '.number_format((int) $log->prompt_tokens) : null, $log->completion_tokens !== null ? 'completion: '.number_format((int) $log->completion_tokens) : null, $log->estimated_cost !== null ? 'cost: $'.number_format((float) $log->estimated_cost, 6) : null, $log->latency_ms !== null ? 'latency: '.number_format((int) $log->latency_ms).'ms' : null])->filter()->implode(' | ') ?: __('No usage metadata') }}</p>@if ($log->error_message)<p class="text-xs" style="color: var(--theme-danger-color);">{{ $log->error_message }}</p>@endif</div></x-ui.table-cell>
                        <x-ui.table-cell>{{ $log->created_at?->format('Y-m-d H:i:s') }}</x-ui.table-cell>
                    </x-ui.table-row>
                @empty
                    <x-ui.table-row>
                        <x-ui.table-cell colspan="7" class="py-10"><x-ui.empty icon="fa-light fa-waveform-lines" :title="__('No AI usage logs found.')" :description="__('Try broadening the current filters or wait for new AI traffic to appear.')" /></x-ui.table-cell>
                    </x-ui.table-row>
                @endforelse
            </x-ui.table-body>
        </x-ui.table>
    </x-ui.datatable-shell>
</div>
