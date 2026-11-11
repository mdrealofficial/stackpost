<x-ui.dashboard-module
    :eyebrow="__('Activity')"
    :title="null"
    :description="null"
>
    <div class="max-w-full overflow-hidden rounded-[1.2rem] border p-4 sm:p-5 xl:p-6" style="border-color: var(--theme-card-border-color); background-color: var(--theme-surface-base);">
        <div class="flex min-w-0 flex-col gap-5">
            <div class="min-w-0 rounded-[1rem] border px-4 py-4 sm:px-6" style="border-color: var(--theme-divider-border-color); background-color: var(--theme-surface-soft);">
                <div class="flex min-w-0 flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center rounded-full border px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.18em]" style="border-color: rgba(var(--theme-accent-rgb),0.16); background: rgba(var(--theme-accent-rgb),0.08); color: var(--theme-accent);">
                                <i class="fa-light fa-clipboard-list-check mr-1.5 text-[10px]"></i>{{ __('Recent account activity') }}
                            </span>
                            <span class="inline-flex items-center rounded-full px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.18em]" style="background: rgba(var(--theme-success-color-rgb),0.1); color: var(--theme-success-color);">
                                {{ __('Chronological log') }}
                            </span>
                        </div>
                        <h3 class="mt-3 text-[1.55rem] font-semibold tracking-[-0.04em]" style="color: var(--theme-header-text-color);">{{ __('Review what happened across your account, day by day') }}</h3>
                        <p class="mt-2 max-w-3xl text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Keep an eye on recent actions, portal changes, and logged events without leaving the dashboard.') }}</p>
                    </div>

                    <div class="flex w-full min-w-0 flex-wrap items-center gap-3 lg:w-auto lg:shrink-0">
                        <x-ui.button :href="$route" size="sm" class="w-full justify-center px-3 text-center whitespace-normal sm:w-auto" wire:navigate>{{ __('Open log') }}</x-ui.button>
                    </div>
                </div>
            </div>

            <div class="min-w-0 overflow-hidden rounded-[var(--theme-card-radius,1.15rem)] border" style="border-color: var(--theme-divider-border-color); background-color: var(--theme-surface-base);">
        <div class="grid min-w-0 gap-3 border-b px-4 py-4 sm:px-5 md:grid-cols-3" style="border-color: var(--theme-border-color); background: rgba(var(--theme-accent-rgb), 0.03);">
            <div class="rounded-[0.95rem] border px-4 py-3" style="border-color: rgba(var(--theme-accent-rgb), 0.12); background: color-mix(in srgb, var(--theme-surface-overlay) 82%, transparent);">
                <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Today') }}</p>
                <p class="mt-2 text-[1.35rem] font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">{{ number_format($metrics['today'] ?? 0) }}</p>
            </div>

            <div class="rounded-[0.95rem] border px-4 py-3" style="border-color: rgba(var(--theme-accent-rgb), 0.12); background: color-mix(in srgb, var(--theme-surface-overlay) 82%, transparent);">
                <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Last 7 days') }}</p>
                <p class="mt-2 text-[1.35rem] font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">{{ number_format($metrics['week'] ?? 0) }}</p>
            </div>

            <div class="rounded-[0.95rem] border px-4 py-3" style="border-color: rgba(var(--theme-accent-rgb), 0.12); background: color-mix(in srgb, var(--theme-surface-overlay) 82%, transparent);">
                <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Entries loaded') }}</p>
                <p class="mt-2 text-[1.35rem] font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">{{ number_format($metrics['loaded'] ?? 0) }}</p>
            </div>
        </div>

        <x-ui.table class="rounded-none border-0 shadow-none">
            <x-ui.table-head>
                <x-ui.table-cell head class="w-[250px]">{{ __('Event') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Description') }}</x-ui.table-cell>
                <x-ui.table-cell head class="w-[120px]">{{ __('Area') }}</x-ui.table-cell>
                <x-ui.table-cell head class="w-[180px]">{{ __('Time') }}</x-ui.table-cell>
            </x-ui.table-head>

            <x-ui.table-body>
                @forelse ($logs as $log)
                    <x-ui.table-row>
                    <x-ui.table-cell class="w-[250px] align-top">
                        <div class="flex items-start gap-3">
                            <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full" style="background: {{ $log->area_variant === 'primary' ? 'rgba(var(--theme-accent-rgb), 0.92)' : 'rgba(100,116,139,0.7)' }};"></span>
                            <div class="space-y-1">
                                <p class="font-semibold tracking-[-0.02em]" style="color: var(--theme-header-text-color);">{{ $log->action }}</p>
                                @if ($log->module && ! in_array($log->module, ['Default Livewire', 'General'], true))
                                    <p class="text-sm" style="color: var(--theme-muted-text-color);">{{ $log->module }}</p>
                                @endif
                            </div>
                        </div>
                    </x-ui.table-cell>

                        <x-ui.table-cell class="align-top">
                            <div class="space-y-1.5">
                                <p class="font-medium leading-6" style="color: var(--theme-header-text-color);">{{ $log->description }}</p>
                                @if ($log->metadata_summary->isNotEmpty())
                                    <p class="text-sm leading-6" style="color: var(--theme-muted-text-color);">
                                        {{ $log->metadata_summary->take(2)->implode(' · ') }}
                                    </p>
                                @endif
                            </div>
                        </x-ui.table-cell>

                        <x-ui.table-cell class="w-[120px] align-top">
                            <x-ui.badge :variant="$log->area_variant">{{ $log->area_label }}</x-ui.badge>
                        </x-ui.table-cell>

                        <x-ui.table-cell class="w-[180px] align-top">
                            <div class="space-y-1">
                                <p class="font-medium" style="color: var(--theme-header-text-color);">{{ $log->created_at_label }}</p>
                                <p class="text-sm" style="color: var(--theme-muted-text-color);">{{ $log->created_at_relative }}</p>
                            </div>
                        </x-ui.table-cell>
                    </x-ui.table-row>
                @empty
                    <x-ui.table-row>
                        <x-ui.table-cell colspan="4" class="py-10">
                            <x-ui.empty
                                icon="fa-light fa-clipboard-list-check"
                                :title="__('No activity recorded yet')"
                                :description="__('User-facing activity entries will appear here once actions are logged from the portal.')"
                            />
                        </x-ui.table-cell>
                    </x-ui.table-row>
                @endforelse
            </x-ui.table-body>
        </x-ui.table>
            </div>
        </div>
    </div>
</x-ui.dashboard-module>
