<div class="min-w-0 max-w-full overflow-x-hidden px-5 pb-12 pt-6 sm:px-6 xl:px-8">
    <div class="mx-auto max-w-[1600px] space-y-5">
        <header class="flex flex-col gap-4 border-b pb-5 lg:flex-row lg:items-end lg:justify-between" style="border-color:var(--theme-divider-border-color,var(--theme-border-color))">
            <x-ui.sub-header compact :title="__('Workflow runs')" :description="__('Review real executions, approvals, credits and recoverable errors in one place.')" />
            <x-ui.button :href="route('portal.automation.index')" wire:navigate variant="outline" size="sm"><i class="fa-light fa-arrow-left"></i>{{ __('Back to workflows') }}</x-ui.button>
        </header>

        <section class="flex flex-wrap items-end gap-3 rounded-xl border p-4" style="border-color:var(--theme-card-border-color);background:var(--theme-surface-base)" aria-label="{{ __('Run filters') }}">
            <label class="text-xs"><span class="mb-2 block">{{ __('Status') }}</span><select class="rounded-lg border px-3 py-2" style="border-color:var(--theme-control-border-color);background:var(--theme-surface-base)" wire:model.live="statusFilter"><option value="">{{ __('All statuses') }}</option><option value="attention">{{ __('Needs attention') }}</option>@foreach (['queued','running','retrying','waiting_delay','waiting_approval','succeeded','failed','rejected','cancelled'] as $status)<option value="{{ $status }}">{{ str($status)->headline() }}</option>@endforeach</select></label>
            <label class="text-xs"><span class="mb-2 block">{{ __('Workflow') }}</span><select class="max-w-full rounded-lg border px-3 py-2" style="border-color:var(--theme-control-border-color);background:var(--theme-surface-base)" wire:model.live="workflowFilter"><option value="">{{ __('All workflows') }}</option>@foreach ($workflows as $workflow)<option value="{{ $workflow->id }}">{{ $workflow->name }}</option>@endforeach</select></label>
            <label class="text-xs"><span class="mb-2 block">{{ __('Created') }}</span><select class="rounded-lg border px-3 py-2" style="border-color:var(--theme-control-border-color);background:var(--theme-surface-base)" wire:model.live="period"><option value="">{{ __('Any time') }}</option><option value="7">{{ __('Last 7 days') }}</option><option value="30">{{ __('Last 30 days') }}</option><option value="90">{{ __('Last 90 days') }}</option></select></label>
            @if ($statusFilter || $workflowFilter || $period)<x-ui.button type="button" variant="outline" size="sm" wire:click="clearFilters">{{ __('Clear filters') }}</x-ui.button>@endif
            <span class="ml-auto text-xs" style="color:var(--theme-muted-text-color)">{{ trans_choice(':count run|:count runs', $runs->total(), ['count' => $runs->total()]) }}</span>
        </section>
        <section class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
            <x-ui.surface-card padding="none" class="overflow-hidden rounded-[24px]">
                <div class="flex items-center justify-between border-b px-6 py-5" style="border-color:var(--theme-divider-border-color,var(--theme-border-color))">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[.13em]" style="color:var(--theme-muted-text-color)">{{ __('Activity') }}</p>
                        <h2 class="mt-1.5 text-[17px] font-black tracking-[-.035em]" style="color:var(--theme-header-text-color)">{{ __('Recent workflow runs') }}</h2>
                    </div>
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl" style="background:color-mix(in srgb,var(--theme-accent) 10%,transparent);color:var(--theme-accent)"><i class="fa-light fa-list-timeline"></i></span>
                </div>

                @if ($runs->count())
                    <div class="divide-y" style="border-color:var(--theme-divider-border-color,var(--theme-border-color))">
                        @foreach ($runs as $run)
                            <button type="button" wire:click="selectRun({{ $run->id }})" class="grid w-full gap-3 px-6 py-4 text-left transition-colors hover:bg-black/[.015] md:grid-cols-[120px_minmax(0,1fr)_140px_20px] md:items-center" style="{{ $selectedRunId === $run->id ? 'background:var(--theme-surface-overlay,#f8fafc)' : '' }}">
                                <span class="w-fit rounded-full px-2.5 py-1 text-[9px] font-black uppercase tracking-[.06em]" style="background:color-mix(in srgb,var(--theme-accent) 11%,transparent);color:var(--theme-accent)">{{ str($run->status)->headline() }}</span>
                                <span class="min-w-0"><span class="block truncate text-xs font-black" style="color:var(--theme-header-text-color)">{{ $run->workflow?->name ?: __('Workflow run') }}</span><span class="mt-1 block truncate text-[10px]" style="color:var(--theme-muted-text-color)">{{ $run->error ?: str($run->trigger_key)->replace('_', ' ')->headline() }}</span></span>
                                <span class="text-[10px]" style="color:var(--theme-muted-text-color)">{{ $run->created_at?->diffForHumans() }}</span>
                                <i class="fa-light fa-chevron-right text-[10px]" style="color:var(--theme-muted-text-color)"></i>
                            </button>
                        @endforeach
                    </div>
                    <div class="border-t px-6 py-4" style="border-color:var(--theme-divider-border-color,var(--theme-border-color))">{{ $runs->links() }}</div>
                @else
                    <div class="grid min-h-[280px] place-items-center px-6 py-10 text-center"><div class="max-w-sm"><span class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl" style="background:color-mix(in srgb,var(--theme-accent) 10%,transparent);color:var(--theme-accent)"><i class="fa-light fa-clock"></i></span><h3 class="mt-4 text-[15px] font-black tracking-[-.025em]" style="color:var(--theme-header-text-color)">{{ $statusFilter || $workflowFilter || $period ? __('No matching runs') : __('No workflow runs yet') }}</h3><p class="mt-2 text-xs leading-5" style="color:var(--theme-muted-text-color)">{{ $statusFilter || $workflowFilter || $period ? __('Try another status, workflow or date range.') : __('When an active workflow receives a new file, its progress will appear here.') }}</p><x-ui.button :href="route('portal.automation.index')" wire:navigate variant="outline" size="sm" class="mt-5"><i class="fa-light fa-diagram-project"></i>{{ __('View workflows') }}</x-ui.button></div></div>
                @endif
            </x-ui.surface-card>

            <aside class="xl:sticky xl:top-4">
                <x-ui.surface-card padding="none" class="overflow-hidden rounded-[24px]">
                    <div class="border-b px-5 py-5" style="border-color:var(--theme-divider-border-color,var(--theme-border-color))"><p class="text-[10px] font-bold uppercase tracking-[.13em]" style="color:var(--theme-muted-text-color)">{{ __('Run inspector') }}</p><h2 class="mt-1.5 text-[17px] font-black tracking-[-.035em]" style="color:var(--theme-header-text-color)">{{ $selectedRun?->workflow?->name ?: __('Execution details') }}</h2></div>
                    @if ($selectedRun)
                        @php($traceEvents = (array) data_get($selectedRun->trace, 'events', []))
                        <div class="space-y-4 p-5">
                            <div class="flex items-center justify-between gap-3"><span class="rounded-full px-2.5 py-1 text-[9px] font-black uppercase" style="background:color-mix(in srgb,var(--theme-accent) 11%,transparent);color:var(--theme-accent)">{{ str($selectedRun->status)->headline() }}</span><span class="text-[10px]" style="color:var(--theme-muted-text-color)">{{ __('Revision :revision', ['revision' => $selectedRun->workflow_revision]) }}</span></div>
                            <div class="grid grid-cols-2 gap-2"><div class="rounded-xl border p-3" style="border-color:var(--theme-control-border-color,var(--theme-border-color))"><p class="text-[9px] font-bold uppercase tracking-[.1em]" style="color:var(--theme-muted-text-color)">{{ __('Credits used') }}</p><p class="mt-1 text-lg font-black" style="color:var(--theme-header-text-color)">{{ number_format((int) $selectedRun->credits_used) }}</p></div><div class="rounded-xl border p-3" style="border-color:var(--theme-control-border-color,var(--theme-border-color))"><p class="text-[9px] font-bold uppercase tracking-[.1em]" style="color:var(--theme-muted-text-color)">{{ __('Attempts') }}</p><p class="mt-1 text-lg font-black" style="color:var(--theme-header-text-color)">{{ number_format((int) $selectedRun->attempt) }} / {{ number_format((int) $selectedRun->max_attempts) }}</p></div></div>
                            @if ($canExecute && $selectedRun->status === 'failed')
                                <x-ui.button type="button" variant="outline" class="w-full justify-center" wire:click="recoverRun({{ $selectedRun->id }}, 'retry')" wire:loading.attr="disabled" wire:confirm="{{ __('Retry the unfinished step using this run’s original settings? Completed steps are kept. An unfinished AI step may use credits.') }}">{{ __('Check setup & retry') }}</x-ui.button>
                                <p class="text-xs leading-5" style="color:var(--theme-muted-text-color)">{{ __('Uses the original workflow version. Changes saved later do not change this run.') }}</p>
                            @elseif ($canExecute && in_array($selectedRun->status, ['queued','retrying','waiting_delay'], true))
                                <x-ui.button type="button" variant="outline" class="w-full justify-center" wire:click="recoverRun({{ $selectedRun->id }}, 'cancel')" wire:loading.attr="disabled" wire:confirm="{{ __('Stop the remaining steps? Existing publishing drafts and scheduled posts will be kept.') }}">{{ __('Stop remaining steps') }}</x-ui.button>
                            @endif
                            @if ($selectedRun->status === 'waiting_approval')<x-ui.button :href="route('portal.publishing.approvals')" variant="outline" class="w-full justify-center"><i class="fa-light fa-shield-check"></i>{{ __('Review publishing drafts') }}</x-ui.button>@endif
                            @if ($selectedRun->error)<p class="rounded-xl border p-3 text-[10px] leading-5" style="border-color:color-mix(in srgb,var(--theme-danger-color) 28%,var(--theme-border-color));background:color-mix(in srgb,var(--theme-danger-color) 6%,var(--theme-surface-base));color:var(--theme-danger-color)">{{ $selectedRun->error }}</p>@endif
                            <div class="space-y-3"><h3 class="text-sm font-semibold">{{ __('Step history') }}</h3>
                                @forelse ($steps as $step)
                                    <div class="rounded-xl border p-3" style="border-color:var(--theme-card-border-color)"><div class="flex items-center justify-between gap-2"><strong class="text-xs">{{ data_get(\Modules\AppAutomation\Support\AutomationWorkflowNodeCatalog::definitions(), $step->node_type.'.label', str($step->node_type)->headline()) }}</strong><span class="text-xs" style="color:{{ $step->status === 'failed' ? 'var(--theme-danger-color)' : 'var(--theme-muted-text-color)' }}">{{ str($step->status)->headline() }}</span></div><p class="mt-1 text-xs" style="color:var(--theme-muted-text-color)">{{ __('Attempt :attempt · :credits credits', ['attempt' => $step->attempt, 'credits' => $step->credits_used]) }}</p>@if ($step->error)<p class="mt-2 break-words text-xs leading-5" style="color:var(--theme-danger-color)">{{ $step->error }}</p>@endif</div>
                                @empty<p class="text-xs" style="color:var(--theme-muted-text-color)">{{ __('No step has started yet.') }}</p>@endforelse
                            </div>
                            @foreach ([__('Source content') => $inputPreview, __('Latest output') => $outputPreview] as $label => $values)
                                <details class="rounded-xl border p-3" style="border-color:var(--theme-card-border-color)"><summary class="cursor-pointer text-sm font-semibold">{{ $label }}</summary><dl class="mt-3 space-y-3">@forelse ($values as $key => $value)<div><dt class="text-xs font-semibold">{{ str($key)->headline() }}</dt><dd class="mt-1 whitespace-pre-wrap break-words text-xs leading-5" style="color:var(--theme-muted-text-color)">{{ $value }}</dd></div>@empty<p class="text-xs" style="color:var(--theme-muted-text-color)">{{ __('No content preview was recorded.') }}</p>@endforelse</dl></details>
                            @endforeach
                        </div>
                    @else
                        <div class="grid min-h-[280px] place-items-center px-6 py-10 text-center"><div><span class="mx-auto flex h-11 w-11 items-center justify-center rounded-xl" style="background:color-mix(in srgb,var(--theme-accent) 10%,transparent);color:var(--theme-accent)"><i class="fa-light fa-list-timeline"></i></span><p class="mt-3 text-sm font-black" style="color:var(--theme-header-text-color)">{{ __('Select a workflow run') }}</p><p class="mt-1 text-xs leading-5" style="color:var(--theme-muted-text-color)">{{ __('Steps, credits and recoverable errors will appear here.') }}</p></div></div>
                    @endif
                </x-ui.surface-card>
            </aside>
        </section>
    </div>
</div>
