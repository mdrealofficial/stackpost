<div class="min-w-0 max-w-full overflow-x-hidden px-5 pb-12 pt-6 sm:px-6 xl:px-8">
    <div class="mx-auto max-w-[1600px] space-y-5">
        <x-ui.sub-header compact
            :title="__('AI Workflows')"
            :description="__('Turn new Google Drive files into publishing drafts or approved, scheduled posts.')"
        >
            <x-slot:actions>
                <x-ui.button :href="route('portal.automation.runs.index')" wire:navigate variant="outline" size="sm">
                    <i class="fa-light fa-list-timeline"></i>{{ __('Runs') }}
                </x-ui.button>
                <x-ui.button :href="route('portal.automation.templates.index')" wire:navigate variant="outline" size="sm">
                    <i class="fa-light fa-grid-2"></i>{{ __('Templates') }}
                </x-ui.button>
                @if ($canManage)
                    <x-ui.button :href="route('portal.automation.workflows.create')" wire:navigate size="sm">
                        <i class="fa-light fa-plus"></i>{{ __('Create workflow') }}
                    </x-ui.button>
                @endif
            </x-slot:actions>
        </x-ui.sub-header>

        @if ($connectionFlash = session('automation_connection_flash'))
            <x-ui.alert
                :variant="in_array(($connectionFlash['tone'] ?? 'neutral'), ['success', 'warning', 'danger'], true) ? $connectionFlash['tone'] : 'neutral'"
                :title="__('Google Drive connection')"
                :description="(string) ($connectionFlash['message'] ?? '')"
                dismissible
            />
        @endif

        @if (! $schemaReady)
            <x-ui.empty
                icon="fa-light fa-database"
                :title="__('Prepare AI Workflow storage')"
                :description="__('Run the pending database migrations, then return here to create durable workflows and run history.')"
            />
        @else
            @php
                $activeDriveConnections = $connections->where('provider', 'google_drive')->where('status', 'active');
                $activeConnectionCount = $connections->where('status', 'active')->count();
                $activeDriveWatcher = $driveStorageReady
                    && $activeDriveConnections->contains(fn ($connection) => $connection->driveChannel?->status === 'active');
                $driveRuntimeReady = $activeDriveWatcher && $queueReadiness['ready'];
                $setupItems = [
                    [
                        'ready' => $activeDriveConnections->isNotEmpty(),
                        'label' => __('Google Drive'),
                        'detail' => $activeDriveConnections->isNotEmpty()
                            ? trans_choice(':count account connected|:count accounts connected', $activeDriveConnections->count(), ['count' => $activeDriveConnections->count()])
                            : __('Connect a workspace account'),
                        'icon' => 'fa-brands fa-google-drive',
                    ],
                    [
                        'ready' => $queueReadiness['ready'],
                        'label' => __('Background processing'),
                        'detail' => $queueReadiness['ready'] ? __('Running') : __('Administrator setup required'),
                        'icon' => 'fa-light fa-bolt',
                    ],
                    [
                        'ready' => $channelCount > 0,
                        'label' => __('Publishing channels'),
                        'detail' => trans_choice(':count eligible channel|:count eligible channels', $channelCount, ['count' => $channelCount]),
                        'icon' => 'fa-light fa-share-nodes',
                    ],
                ];
                $setupReadyCount = collect($setupItems)->where('ready', true)->count();
                $setupPercent = (int) round(($setupReadyCount / count($setupItems)) * 100);
                $starterTemplate = collect($templates)->firstWhere('key', 'google_drive');
                $overviewStats = [
                    [
                        'label' => __('Active workflows'),
                        'value' => (int) $summary['active'],
                        'detail' => __('Can receive new triggers'),
                        'icon' => 'fa-light fa-circle-play',
                        'color' => 'var(--theme-success-color)',
                        'rgb' => 'var(--theme-success-color-rgb)',
                    ],
                    [
                        'label' => __('Drafts'),
                        'value' => (int) $summary['drafts'],
                        'detail' => __('Not yet activated'),
                        'icon' => 'fa-light fa-file-pen',
                        'color' => 'var(--theme-info-color)',
                        'rgb' => 'var(--theme-info-color-rgb)',
                    ],
                    [
                        'label' => __('Connections'),
                        'value' => $activeConnectionCount,
                        'detail' => __('Connected Drive accounts'),
                        'icon' => 'fa-light fa-link',
                        'color' => 'var(--theme-accent)',
                        'rgb' => 'var(--theme-accent-rgb)',
                    ],
                    [
                        'label' => __('Needs attention'),
                        'value' => (int) $summary['attention'],
                        'detail' => __('Failed or waiting approval'),
                        'icon' => 'fa-light fa-triangle-exclamation',
                        'color' => 'var(--theme-warning-color)',
                        'rgb' => 'var(--theme-warning-color-rgb)',
                    ],
                ];
            @endphp

            @if ($summary['attention'] > 0)
                <x-ui.button :href="route('portal.automation.runs.index', ['status' => 'attention'])" wire:navigate variant="outline" size="sm">{{ __('Review runs needing attention') }} <i class="fa-light fa-arrow-right"></i></x-ui.button>
            @endif
            <section aria-label="{{ __('Automation overview') }}" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($overviewStats as $stat)
                    <div class="flex min-w-0 items-center gap-3 rounded-[1rem] border p-4" style="border-color: var(--theme-card-border-color); background-color: var(--theme-surface-base);">
                        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-[0.8rem]" style="background: rgba({{ $stat['rgb'] }},0.10); color: {{ $stat['color'] }};">
                            <i class="{{ $stat['icon'] }} text-sm"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex min-w-0 items-baseline justify-between gap-3">
                                <p class="truncate text-[11px] font-semibold uppercase tracking-[0.14em]" style="color: var(--theme-muted-text-color);">{{ $stat['label'] }}</p>
                                <p class="shrink-0 text-2xl font-semibold tracking-[-0.04em]" style="color: var(--theme-header-text-color);">{{ number_format($stat['value']) }}</p>
                            </div>
                            <p class="mt-1 truncate text-xs" style="color: var(--theme-muted-text-color);">{{ $stat['detail'] }}</p>
                        </div>
                    </div>
                @endforeach
            </section>

            <section class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
                <main class="min-w-0">
                    @if ($workflows->isEmpty())
                        <x-ui.surface-card padding="none" class="rounded-[1.2rem]">
                            <div class="p-5 sm:p-6">
                                <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                                    <div class="flex min-w-0 items-start gap-4">
                                        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-[0.9rem]" style="background: rgba(var(--theme-info-color-rgb),0.10); color: var(--theme-info-color);">
                                            <i class="fa-brands fa-google-drive text-base"></i>
                                        </span>
                                        <div class="min-w-0">
                                            <p class="text-[11px] font-semibold uppercase tracking-[0.16em]" style="color: var(--theme-muted-text-color);">{{ __('Starter workflow') }}</p>
                                            <h2 class="mt-2 text-xl font-semibold tracking-[-0.035em]" style="color: var(--theme-header-text-color);">{{ __('Turn Drive files into approved social posts') }}</h2>
                                            <p class="mt-2 max-w-2xl text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ $starterTemplate['description'] ?? __('A new Drive file starts a controlled path through content preparation, approval, and scheduling.') }}</p>
                                        </div>
                                    </div>

                                    @if ($canManage)
                                        <x-ui.button :href="route('portal.automation.workflows.create', ['template' => 'google_drive'])" wire:navigate class="w-full justify-center lg:w-auto lg:shrink-0">
                                            {{ __('Set up workflow') }} <i class="fa-light fa-arrow-right"></i>
                                        </x-ui.button>
                                    @endif
                                </div>

                                <div class="mt-6 grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                                    @foreach ([
                                        [__('Source'), __('Drive file'), 'fa-brands fa-google-drive', 'info'],
                                        [__('Prepare'), __('AI content'), 'fa-light fa-wand-magic-sparkles', 'accent'],
                                        [__('Control'), __('Team approval'), 'fa-light fa-shield-check', 'success'],
                                        [__('Deliver'), __('Schedule post'), 'fa-light fa-calendar-clock', 'warning'],
                                    ] as $index => [$kicker, $label, $icon, $tone])
                                        @php
                                            $toneColor = $tone === 'accent' ? 'var(--theme-accent)' : "var(--theme-{$tone}-color)";
                                            $toneRgb = $tone === 'accent' ? 'var(--theme-accent-rgb)' : "var(--theme-{$tone}-color-rgb)";
                                        @endphp
                                        <div class="flex min-w-0 items-center gap-3 rounded-[0.9rem] border px-3.5 py-3" style="border-color: var(--theme-divider-border-color); background-color: var(--theme-surface-soft);">
                                            <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-[0.7rem]" style="background: rgba({{ $toneRgb }},0.10); color: {{ $toneColor }};">
                                                <i class="{{ $icon }} text-sm"></i>
                                            </span>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-[11px] font-medium" style="color: var(--theme-muted-text-color);">{{ sprintf('%02d', $index + 1) }} · {{ $kicker }}</p>
                                                <p class="mt-0.5 truncate text-xs font-semibold" style="color: var(--theme-header-text-color);">{{ $label }}</p>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="mt-5 flex flex-col gap-2 border-t pt-4 sm:flex-row sm:items-center sm:justify-between" style="border-color: var(--theme-divider-border-color);">
                                    <p class="inline-flex items-center gap-2 text-xs font-semibold" style="color: {{ $driveRuntimeReady ? 'var(--theme-success-color)' : 'var(--theme-warning-color)' }};">
                                        <i class="fa-light {{ $driveRuntimeReady ? 'fa-circle-check' : 'fa-circle-info' }}"></i>
                                        {{ $driveRuntimeReady ? __('Ready to activate') : __(':ready of 3 setup steps ready', ['ready' => $setupReadyCount]) }}
                                    </p>
                                    <p class="text-xs" style="color: var(--theme-muted-text-color);">{{ __('Nothing publishes until the approval step succeeds.') }}</p>
                                </div>
                            </div>
                        </x-ui.surface-card>
                    @else
                        <x-ui.surface-card padding="none" class="rounded-[1.2rem]">
                            <div class="flex flex-wrap items-center justify-between gap-4 border-b px-5 py-4 sm:px-6" style="border-color: var(--theme-divider-border-color);">
                                <div>
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em]" style="color: var(--theme-muted-text-color);">{{ __('Your workflows') }}</p>
                                    <h2 class="mt-1.5 text-lg font-semibold tracking-[-0.025em]" style="color: var(--theme-header-text-color);">{{ __('Automation paths') }}</h2>
                                </div>
                                <span class="text-xs font-medium" style="color: var(--theme-muted-text-color);">{{ trans_choice(':count workflow|:count workflows', $workflows->count(), ['count' => $workflows->count()]) }}</span>
                            </div>

                            <div class="divide-y" style="border-color: var(--theme-divider-border-color);">
                                @foreach ($workflows as $workflow)
                                    @php
                                        $nodes = collect((array) data_get($workflow->definition, 'nodes', []));
                                        $trigger = $nodes->first(fn ($node) => is_array($node) && str_starts_with((string) ($node['type'] ?? ''), 'trigger_'));
                                        $middle = $nodes->first(fn ($node) => is_array($node) && ! str_starts_with((string) ($node['type'] ?? ''), 'trigger_') && ($node['type'] ?? '') !== 'end');
                                        $action = $nodes->reverse()->first(fn ($node) => is_array($node) && ! in_array((string) ($node['type'] ?? ''), ['end', 'approval'], true));
                                        $pathNodes = collect([$trigger, $middle, $action])
                                            ->filter()
                                            ->unique(fn ($node) => (string) data_get($node, 'id', data_get($node, 'type')))
                                            ->values();
                                        $statusColor = match ($workflow->status) {
                                            'active' => 'var(--theme-success-color)',
                                            'paused' => 'var(--theme-warning-color)',
                                            default => 'var(--theme-muted-text-color)',
                                        };
                                        $statusRgb = match ($workflow->status) {
                                            'active' => 'var(--theme-success-color-rgb)',
                                            'paused' => 'var(--theme-warning-color-rgb)',
                                            default => 'var(--theme-border-color-rgb)',
                                        };
                                        $labelFor = fn ($node) => (string) data_get($nodeCatalog, ((string) data_get($node, 'type')).'.label', __('Workflow step'));
                                    @endphp

                                    <article class="p-5 sm:p-6">
                                        <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                                            <div class="min-w-0 flex-1">
                                                <div class="flex min-w-0 items-start gap-3">
                                                    <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-[0.8rem]" style="background: rgba(var(--theme-accent-rgb),0.09); color: var(--theme-accent);">
                                                        <i class="fa-light fa-diagram-project"></i>
                                                    </span>
                                                    <div class="min-w-0 flex-1">
                                                        <div class="flex min-w-0 flex-wrap items-center gap-2">
                                                            <h3 class="min-w-0 truncate text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $workflow->name }}</h3>@if (data_get($workflow->settings, 'draft_revision'))<span class="text-xs" style="color:var(--theme-warning-color)">{{ __('Unpublished edits') }}</span>@endif
                                                            <span class="inline-flex shrink-0 items-center rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.08em]" style="background: rgba({{ $statusRgb }},0.10); color: {{ $statusColor }};">
                                                                {{ str($workflow->status)->headline() }}
                                                            </span>
                                                        </div>
                                                        <p class="mt-1 text-xs" style="color: var(--theme-muted-text-color);">{{ __('Revision :revision · :runs recorded runs', ['revision' => $workflow->current_revision, 'runs' => $workflow->runs_count]) }}</p>
                                                    </div>
                                                </div>

                                                @if ($pathNodes->isNotEmpty())
                                                    <div class="mt-4 flex min-w-0 flex-wrap items-center gap-2">
                                                        @foreach ($pathNodes as $node)
                                                            <span class="inline-flex items-center rounded-lg border px-3 py-2 text-xs font-medium" style="border-color: var(--theme-divider-border-color); background: var(--theme-surface-soft); color: var(--theme-header-text-color);">
                                                                {{ $labelFor($node) }}
                                                            </span>
                                                            @if (! $loop->last)
                                                                <i class="fa-light fa-arrow-right text-[11px]" style="color: var(--theme-muted-text-color);"></i>
                                                            @endif
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>

                                            <div class="flex shrink-0 flex-wrap items-center gap-2">
                                                @if ($workflow->status === 'active' && $canManage)
                                                    <x-ui.button type="button" wire:click="pauseWorkflow({{ $workflow->id }})" variant="outline" size="sm">
                                                        <i class="fa-light fa-pause"></i>{{ __('Pause') }}
                                                    </x-ui.button>
                                                @endif
                                                <x-ui.button :href="route('portal.automation.workflows.edit', ['workflow' => $workflow->id])" wire:navigate variant="outline" size="sm">
                                                    {{ $canManage ? __('Edit flow') : __('View flow') }} <i class="fa-light fa-arrow-right"></i>
                                                </x-ui.button>
                                            </div>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </x-ui.surface-card>
                    @endif
                </main>

                <aside id="workflow-readiness" class="scroll-mt-24 xl:sticky xl:top-5">
                    <x-ui.surface-card padding="none" class="rounded-[1.2rem]">
                        <div class="border-b px-5 py-5" style="border-color: var(--theme-divider-border-color);">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em]" style="color: var(--theme-muted-text-color);">{{ __('Setup readiness') }}</p>
                                    <h2 class="mt-1.5 text-lg font-semibold tracking-[-0.025em]" style="color: var(--theme-header-text-color);">{{ __('Prepare this workspace') }}</h2>
                                </div>
                                <span class="text-sm font-semibold" style="color: {{ $driveRuntimeReady ? 'var(--theme-success-color)' : 'var(--theme-accent)' }};">{{ $setupReadyCount }}/{{ count($setupItems) }}</span>
                            </div>
                            <div class="mt-4 h-1.5 overflow-hidden rounded-full" style="background: var(--theme-surface-overlay, #f1f5f9);">
                                <span class="block h-full rounded-full transition-all" style="width: {{ $setupPercent }}%; background: {{ $driveRuntimeReady ? 'var(--theme-success-color)' : 'var(--theme-accent)' }};"></span>
                            </div>
                        </div>

                        <div class="divide-y px-5" style="border-color: var(--theme-divider-border-color);">
                            @foreach ($setupItems as $setupItem)
                                @php
                                    $setupColor = $setupItem['ready'] ? 'var(--theme-success-color)' : 'var(--theme-warning-color)';
                                    $setupRgb = $setupItem['ready'] ? 'var(--theme-success-color-rgb)' : 'var(--theme-warning-color-rgb)';
                                @endphp
                                <div class="flex items-center gap-3 py-4">
                                    <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-[0.7rem]" style="background: rgba({{ $setupRgb }},0.10); color: {{ $setupColor }};">
                                        <i class="{{ $setupItem['ready'] ? 'fa-light fa-check' : $setupItem['icon'] }} text-sm"></i>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs font-semibold" style="color: var(--theme-header-text-color);">{{ $setupItem['label'] }}</p>
                                        <p class="mt-1 truncate text-xs" style="color: var(--theme-muted-text-color);">{{ $setupItem['detail'] }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="border-t p-5" style="border-color: var(--theme-divider-border-color);">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold" style="color: var(--theme-header-text-color);">{{ __('Google Drive account') }}</p>
                                    <p class="mt-1 text-xs leading-5" style="color: var(--theme-muted-text-color);">
                                        {{ $activeDriveConnections->isNotEmpty()
                                            ? __('A persistent workspace connection is available.')
                                            : __('Connect once so folder watching can run in the background.') }}
                                    </p>
                                </div>
                                @if ($canManageConnections && $driveConfiguration['ready'])
                                    <x-ui.button :href="route('portal.automation.connections.google-drive.connect')" size="sm">
                                        <i class="fa-brands fa-google-drive"></i>{{ __('Connect') }}
                                    </x-ui.button>
                                @endif
                            </div>

                            @if (! $driveConfiguration['ready'])
                                <p class="mt-3 rounded-xl border px-3 py-2.5 text-xs leading-5" style="border-color: rgba(var(--theme-warning-color-rgb),0.18); background: rgba(var(--theme-warning-color-rgb),0.06); color: var(--theme-warning-color);">
                                    <i class="fa-light fa-triangle-exclamation mr-1.5"></i>{{ __('Ask your workspace administrator to enable Google Drive connections first.') }}
                                </p>
                            @elseif (! $driveConfiguration['webhook_public'])
                                <p class="mt-3 rounded-xl border px-3 py-2.5 text-xs leading-5" style="border-color: rgba(var(--theme-warning-color-rgb),0.18); background: rgba(var(--theme-warning-color-rgb),0.06); color: var(--theme-warning-color);">
                                    <i class="fa-light fa-link-slash mr-1.5"></i>{{ __('Folder watching also needs a public HTTPS notification URL.') }}
                                </p>
                            @endif

                            @foreach ($activeDriveConnections as $connection)
                                <div class="mt-3 flex items-center gap-2.5 rounded-xl border p-2.5" style="border-color: var(--theme-divider-border-color); background: var(--theme-surface-soft);">
                                    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg" style="background: rgba(var(--theme-info-color-rgb),0.10); color: var(--theme-info-color);">
                                        <i class="fa-brands fa-google-drive"></i>
                                    </span>
                                    <p class="min-w-0 flex-1 truncate text-xs font-semibold" style="color: var(--theme-header-text-color);">{{ $connection->external_account_email ?: $connection->label }}</p>
                                    @if ($canManageConnections)
                                        <form method="POST" action="{{ route('portal.automation.connections.google-drive.verify', ['connection' => $connection->id]) }}">
                                            @csrf
                                            <x-ui.button type="submit" variant="ghost" size="sm" title="{{ __('Verify') }}">
                                                <i class="fa-light fa-arrows-rotate"></i>
                                            </x-ui.button>
                                        </form>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </x-ui.surface-card>
                </aside>
            </section>
        @endif
    </div>
</div>
