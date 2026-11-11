@component(theme_view('layouts.app', 'app'), ['title' => __('Activity Log')])
    @php
        $formatMetadataValue = static function (mixed $value): string {
            if (is_array($value)) {
                return collect($value)
                    ->map(fn (mixed $nestedValue, mixed $nestedKey) => is_string($nestedKey)
                        ? $nestedKey.': '.(is_scalar($nestedValue) || $nestedValue === null ? (string) $nestedValue : json_encode($nestedValue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
                        : (is_scalar($nestedValue) || $nestedValue === null ? (string) $nestedValue : json_encode($nestedValue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)))
                    ->implode(', ');
            }

            if (is_object($value)) {
                $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                return $json !== false ? $json : '[object]';
            }

            if (is_bool($value)) {
                return $value ? 'true' : 'false';
            }

            return $value === null ? '-' : (string) $value;
        };
        $eventLabel = static function ($event): string {
            $event = (string) $event;
            $known = [
                'admin.plans.update' => __('Plan updated'),
                'default-livewire.update' => __('Workspace action'),
                'login' => __('Signed in'), 'logout' => __('Signed out'),
                'created' => __('Created'), 'updated' => __('Updated'), 'deleted' => __('Deleted'),
            ];
            return $known[$event] ?? (filled($event) ? (string) str(str_replace('.', ' ', $event))->headline() : __('No activity yet'));
        };
        $latestLog = $logs->first();
        $areaCount = $logs->pluck('area')->unique()->count();
        $activityMetrics = [
            [
                'label' => __('Entries'),
                'value' => number_format($logs->count()),
                'detail' => __('Recent activity records loaded for your account.'),
                'icon' => 'fa-light fa-list-timeline',
                'tone' => 'rgba(59, 130, 246, 0.10)',
                'color' => 'rgb(37, 99, 235)',
            ],
            [
                'label' => __('Latest event'),
                'value' => $eventLabel($latestLog?->event),
                'detail' => $latestLog?->created_at?->format('Y-m-d H:i') ?? __('Activity will appear here after key actions.'),
                'icon' => 'fa-light fa-clock-rotate-left',
                'tone' => 'rgba(245, 158, 11, 0.12)',
                'color' => 'rgb(217, 119, 6)',
            ],
            [
                'label' => __('Areas'),
                'value' => number_format($areaCount),
                'detail' => __('Portal and admin actions are recorded together for this user.'),
                'icon' => 'fa-light fa-layer-group',
                'tone' => 'rgba(168, 85, 247, 0.10)',
                'color' => 'rgb(126, 34, 206)',
            ],
        ];
    @endphp

    <div class="space-y-6">
        <x-ui.sub-header :compact="true" :title="__('Activity')" :description="__('Review recent actions on your account.')"><x-slot:actions>
                        <x-ui.button href="{{ route('portal.dashboard') }}" variant="outline" wire:navigate>
                            <i class="fa-light fa-arrow-left"></i>
                            {{ __('Back to dashboard') }}
                        </x-ui.button>
                    </x-slot:actions></x-ui.sub-header>

        <section class="grid gap-5 md:grid-cols-3">
            @foreach ($activityMetrics as $metric)
                <x-ui.stat-card :compact="true" :label="$metric['label']" :value="$metric['value']" :description="$metric['detail']" />
            @endforeach
        </section>

        <x-ui.datatable-shell :title="__('Recent activity')" :info="__('Chronological action history for your account.')">
            <x-ui.table class="rounded-none border-0 shadow-none">
                <x-ui.table-head>
                    <x-ui.table-cell head>{{ __('Event') }}</x-ui.table-cell>
                    <x-ui.table-cell head>{{ __('Description') }}</x-ui.table-cell>
                    <x-ui.table-cell head>{{ __('Area') }}</x-ui.table-cell>
                    <x-ui.table-cell head>{{ __('Details') }}</x-ui.table-cell>
                    <x-ui.table-cell head>{{ __('Time') }}</x-ui.table-cell>
                </x-ui.table-head>

                <x-ui.table-body>
                    @forelse ($logs as $log)
                        <x-ui.table-row>
                            <x-ui.table-cell>
                                <div class="flex items-center gap-3">
                                    <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl" style="background: rgba(var(--theme-accent-rgb), 0.10); color: var(--theme-accent-color);">
                                        <i class="fa-light fa-bolt"></i>
                                    </span>
                                    <p class="font-semibold" style="color: var(--theme-header-text-color);">{{ $eventLabel($log->event) }}</p>
                                </div>
                            </x-ui.table-cell>
                            <x-ui.table-cell>
                                <div class="space-y-1">
                                    <p class="font-medium" style="color: var(--theme-header-text-color);">{{ $log->description ?: '—' }}</p>
                                    @if (! empty($log->metadata))
                                        <details class="text-xs" style="color: var(--theme-muted-text-color);"><summary class="cursor-pointer">{{ __('More details') }}</summary><p class="mt-2 break-words">{{ collect($log->metadata)->map(fn ($value, $key) => $key.': '.$formatMetadataValue($value))->implode(' · ') }}</p></details>
                                    @endif
                                </div>
                            </x-ui.table-cell>
                            <x-ui.table-cell><x-ui.badge :variant="$log->area === 'user' ? 'primary' : 'neutral'">{{ strtoupper($log->area) }}</x-ui.badge></x-ui.table-cell>
                            <x-ui.table-cell><details class="text-xs"><summary class="cursor-pointer" style="color:var(--theme-accent)">{{ __('Technical details') }}</summary><p class="mt-2 break-all">{{ $log->event }}</p><p class="mt-1 break-all">{{ $log->route_name ?: '—' }}</p></details></x-ui.table-cell>
                            <x-ui.table-cell>{{ $log->created_at?->format('Y-m-d H:i:s') }}</x-ui.table-cell>
                        </x-ui.table-row>
                    @empty
                        <x-ui.table-row>
                            <x-ui.table-cell colspan="5" class="py-10">
                                <x-ui.empty icon="fa-light fa-list-timeline" :title="__('No activity recorded yet.')" :description="__('Portal and account actions will appear here after activity is captured.')" />
                            </x-ui.table-cell>
                        </x-ui.table-row>
                    @endforelse
                </x-ui.table-body>
            </x-ui.table>
        </x-ui.datatable-shell>
    </div>
@endcomponent
