<div class="space-y-6 px-4 pb-8 pt-4 sm:px-5 xl:px-6">
    <x-ui.sub-header :compact="true" :title="__('Calendar Planner')" :description="__('Build a dated content plan from your campaign brief.')" />

    <section class="grid gap-5 xl:grid-cols-[21rem_minmax(0,1fr)]">
        <aside class="space-y-5">
            <div class="rounded-[1.25rem] border p-5" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: color-mix(in srgb, var(--theme-surface-overlay) 97%, transparent);">
                <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Planning controls') }}</p>
                <div class="mt-5 grid gap-4">
                    <x-ui.input wire:model.live="planName" :label="__('Plan name')" :placeholder="__('April campaign plan')" :error="$errors->first('planName')" />
                    <x-ui.date-picker wire:model.live="plannerStartDate" name="planner_start_date" :label="__('Start date')" :value="$plannerStartDate" :error="$errors->first('plannerStartDate')" :placeholder="__('Choose start date')" />
                    <x-ui.input type="number" min="3" max="31" wire:model.live="plannerDays" :label="__('Days')" :error="$errors->first('plannerDays')" />
                    <x-ui.button type="button" variant="outline" wire:click="newPlan">
                        <i class="fa-light fa-plus"></i>
                        {{ __('New plan') }}
                    </x-ui.button>
                </div>
            </div>

            <div class="rounded-[1.25rem] border p-5" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: color-mix(in srgb, var(--theme-surface-overlay) 97%, transparent);">
                <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Saved plans') }}</p>
                @if ($savedPlans->isNotEmpty())
                    <div class="mt-4 space-y-2">
                        @foreach ($savedPlans as $plan)
                            <div x-data="{ confirmDelete: false }" class="rounded-[1rem] border px-4 py-3 transition" style="border-color: {{ $savedPlanId === $plan->id ? 'rgba(var(--theme-accent-rgb), 0.34)' : 'rgba(var(--theme-border-color-rgb), 0.42)' }}; background-color: {{ $savedPlanId === $plan->id ? 'rgba(var(--theme-accent-rgb), 0.07)' : 'color-mix(in srgb, var(--theme-surface-base) 94%, transparent)' }};">
                                <div class="flex items-start justify-between gap-3">
                                    <button type="button" wire:click="loadPlan({{ $plan->id }})" class="min-w-0 flex-1 text-left">
                                        <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $plan->title ?: __('Untitled plan') }}</p>
                                        <p class="mt-1 text-xs" style="color: var(--theme-muted-text-color);">{{ optional($plan->updated_at)->diffForHumans() }}</p>
                                    </button>
                                    <div class="flex items-center gap-2">
                                        @if ($savedPlanId === $plan->id)
                                            <span class="text-[11px] font-semibold uppercase tracking-[0.16em]" style="color: var(--theme-accent);">{{ __('Open') }}</span>
                                        @endif
                                        <button type="button" x-on:click="confirmDelete = true" class="inline-flex h-9 w-9 items-center justify-center rounded-[0.8rem] border transition hover:bg-slate-900/[0.03]" style="border-color: rgba(var(--theme-border-color-rgb), 0.52); color: var(--theme-danger-color);">
                                            <i class="fa-light fa-trash"></i>
                                        </button>
                                    </div>
                                </div>

                                <div
                                    x-cloak
                                    x-show="confirmDelete"
                                    x-transition.opacity
                                    class="fixed inset-0 z-[140] flex items-center justify-center bg-slate-950/45 px-4"
                                >
                                    <div
                                        x-show="confirmDelete"
                                        x-transition
                                        x-on:click.away="confirmDelete = false"
                                        class="w-full max-w-md rounded-[1.25rem] border p-5 shadow-[0_24px_80px_-32px_rgba(15,23,42,0.55)]"
                                        style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: var(--theme-surface-overlay);"
                                    >
                                        <p class="text-base font-semibold" style="color: var(--theme-header-text-color);">{{ __('Delete saved plan?') }}</p>
                                        <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">
                                            {{ __('This will remove ":name" from your saved planner library.', ['name' => $plan->title ?: __('Untitled plan')]) }}
                                        </p>

                                        <div class="mt-5 flex flex-wrap justify-end gap-2">
                                            <x-ui.button type="button" variant="outline" x-on:click="confirmDelete = false">
                                                {{ __('Cancel') }}
                                            </x-ui.button>
                                            <x-ui.button type="button" variant="danger" wire:click="deletePlan({{ $plan->id }})" wire:loading.attr="disabled" wire:target="deletePlan({{ $plan->id }})" x-on:click="confirmDelete = false">
                                                <i class="fa-light fa-trash"></i>
                                                <span wire:loading.remove wire:target="deletePlan({{ $plan->id }})">{{ __('Delete plan') }}</span>
                                                <span wire:loading wire:target="deletePlan({{ $plan->id }})">{{ __('Deleting...') }}</span>
                                            </x-ui.button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="mt-4">
                        <x-ui.empty
                            icon="fa-light fa-bookmark"
                            :title="__('No saved plans yet')"
                            :description="__('Save a planner result with a name to build your reusable planning library.')"
                        />
                    </div>
                @endif
            </div>

            <div class="rounded-[1.25rem] border p-5" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: color-mix(in srgb, var(--theme-surface-overlay) 97%, transparent);">
                <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Prompt history') }}</p>
                @if ($promptHistory->isNotEmpty())
                    <div class="mt-4 max-h-[22rem] space-y-2 overflow-y-auto pr-1">
                        @foreach ($promptHistory as $history)
                            <button type="button" wire:click="loadPromptHistory({{ $history->id }})" class="w-full rounded-[1rem] border px-4 py-3 text-left transition" style="border-color: rgba(var(--theme-border-color-rgb), 0.42); background-color: color-mix(in srgb, var(--theme-surface-base) 94%, transparent);">
                                <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $history->title ?: __('Planner prompt') }}</p>
                                <p class="mt-1 text-xs leading-5" style="color: var(--theme-muted-text-color);">{{ \Illuminate\Support\Str::limit($history->prompt, 78) }}</p>
                            </button>
                        @endforeach
                    </div>
                @else
                    <div class="mt-4">
                        <x-ui.empty
                            icon="fa-light fa-book-open"
                            :title="__('No prompt history yet')"
                            :description="__('Planner briefs you run here will be saved for quick reuse.')"
                        />
                    </div>
                @endif
            </div>

            <div class="rounded-[1.25rem] border p-5" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: color-mix(in srgb, var(--theme-surface-overlay) 97%, transparent);">
                <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Planner flow') }}</p>
                <div class="mt-4 space-y-3">
                    <div class="rounded-[1rem] border px-4 py-3" style="border-color: rgba(var(--theme-border-color-rgb), 0.42); background-color: color-mix(in srgb, var(--theme-surface-base) 94%, transparent);">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em]" style="color: var(--theme-accent);">{{ __('1. Brief') }}</p>
                        <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Set the campaign goal, audience, and core value proposition.') }}</p>
                    </div>
                    <div class="rounded-[1rem] border px-4 py-3" style="border-color: rgba(var(--theme-border-color-rgb), 0.42); background-color: color-mix(in srgb, var(--theme-surface-base) 94%, transparent);">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em]" style="color: var(--theme-accent);">{{ __('2. Map') }}</p>
                        <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('AI spreads the brief into objectives, dates, and content themes.') }}</p>
                    </div>
                    <div class="rounded-[1rem] border px-4 py-3" style="border-color: rgba(var(--theme-border-color-rgb), 0.42); background-color: color-mix(in srgb, var(--theme-surface-base) 94%, transparent);">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em]" style="color: var(--theme-accent);">{{ __('3. Schedule') }}</p>
                        <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Use the output as a planning board before creating real posts.') }}</p>
                    </div>
                </div>
            </div>
        </aside>

        <div class="space-y-5">
            <div class="rounded-[1.25rem] border p-5 sm:p-6" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: color-mix(in srgb, var(--theme-surface-overlay) 97%, transparent);">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="max-w-2xl">
                        <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Campaign brief') }}</p>
                        <p class="mt-1 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Describe the campaign direction, audience segment, offer, and outcome you want the content plan to support.') }}</p>
                    </div>
                    <div class="rounded-full border px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.16em]" style="border-color: rgba(var(--theme-border-color-rgb), 0.5); background-color: color-mix(in srgb, var(--theme-surface-base) 94%, transparent); color: var(--theme-muted-text-color);">
                        {{ __('Calendar-ready output') }}
                    </div>
                </div>

                <div class="mt-5">
                    <x-ui.textarea wire:model.defer="calendarBrief" :label="__('Campaign brief')" :error="$errors->first('calendarBrief')" rows="8" placeholder="{{ __('Describe the campaign, target audience, and main outcomes...') }}">{{ $calendarBrief }}</x-ui.textarea>
                </div>

                <div class="mt-5 flex flex-wrap items-center gap-3">
                    <x-ui.button type="button" wire:click="plan" wire:loading.attr="disabled" wire:target="plan" :disabled="!($creditPreview['enough'] ?? true)">
                        <i class="fa-light fa-calendar-range"></i>
                        <span wire:loading.remove wire:target="plan">{{ __('Plan calendar') }}</span>
                        <span wire:loading wire:target="plan">{{ __('Planning...') }}</span>
                    </x-ui.button>
                    <div class="inline-flex items-center gap-2 rounded-full border px-3 py-2 text-sm" style="border-color: rgba(var(--theme-border-color-rgb), 0.5); background-color: color-mix(in srgb, var(--theme-surface-base) 94%, transparent); color: var(--theme-muted-text-color);">
                        <i class="fa-light fa-coins text-xs" style="color: var(--theme-accent);"></i>
                        <span>{{ __(':credits credits', ['credits' => $creditPreview['amount'] ?? 0]) }}</span>
                        <span>&bull;</span>
                        <span>{{ ($creditPreview['unlimited'] ?? false) ? __('Unlimited plan') : __(':credits left', ['credits' => $creditPreview['remaining'] ?? 0]) }}</span>
                    </div>
                </div>
                @if (!($creditPreview['enough'] ?? true))
                    <p class="mt-2 text-sm font-medium" style="color: var(--theme-danger-color);">{{ __('Not enough credits remaining for this action.') }}</p>
                    @include(theme_view('partials.credit-topup-cta', 'app'))
                @endif

                <div wire:loading.flex wire:target="plan" class="mt-4 items-center gap-3 rounded-[1rem] border px-4 py-3 text-sm" style="border-color: rgba(var(--theme-accent-rgb), 0.16); background-color: rgba(var(--theme-accent-rgb), 0.06); color: var(--theme-muted-text-color);">
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-full" style="background-color: rgba(var(--theme-accent-rgb), 0.1); color: var(--theme-accent);">
                        <i class="fa-light fa-loader animate-spin"></i>
                    </span>
                    <div>
                        <p class="font-medium" style="color: var(--theme-header-text-color);">{{ __('Building your calendar plan...') }}</p>
                        <p class="text-xs sm:text-sm">{{ __('AI is mapping the brief into dated publishing ideas and content angles.') }}</p>
                    </div>
                </div>
            </div>

            @if ($result)
                <div class="rounded-[1.3rem] border p-5 sm:p-6" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: color-mix(in srgb, var(--theme-surface-overlay) 97%, transparent);">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Planned schedule') }}</p>
                            <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Use this timeline as a planning draft before scheduling real content.') }}</p>
                        </div>
                        @php
                            $sourceLabel = match ((string) ($result['source'] ?? '')) {
                                'ai' => __('AI plan'),
                                'fallback' => __('Local plan'),
                                default => __('Draft plan'),
                            };
                        @endphp
                        <div class="flex flex-wrap items-center gap-2">
                            @if ($savedPlanId)
                                <span class="rounded-full border px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.18em]" style="border-color: rgba(var(--theme-success-rgb, 34, 197, 94), 0.24); background-color: rgba(var(--theme-success-rgb, 34, 197, 94), 0.08); color: var(--theme-success-color);">
                                    {{ __('Saved') }}
                                </span>
                            @endif
                            <span class="rounded-full border px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.18em]" style="border-color: rgba(var(--theme-accent-rgb), 0.24); background-color: rgba(var(--theme-accent-rgb), 0.08); color: var(--theme-accent);">
                                {{ $sourceLabel }}
                            </span>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap items-center gap-3">
                        <x-ui.button type="button" variant="outline" wire:click="createPlan" wire:loading.attr="disabled" wire:target="createPlan">
                            <i class="fa-light fa-bookmark"></i>
                            <span wire:loading.remove wire:target="createPlan">{{ __('Save as new plan') }}</span>
                            <span wire:loading wire:target="createPlan">{{ __('Saving...') }}</span>
                        </x-ui.button>
                        @if ($savedPlanId)
                            <x-ui.button type="button" wire:click="updateSavedPlan" wire:loading.attr="disabled" wire:target="updateSavedPlan">
                                <i class="fa-light fa-floppy-disk"></i>
                                <span wire:loading.remove wire:target="updateSavedPlan">{{ __('Update selected plan') }}</span>
                                <span wire:loading wire:target="updateSavedPlan">{{ __('Updating...') }}</span>
                            </x-ui.button>
                        @endif
                        @if ($savedPlan)
                            <span class="text-sm" style="color: var(--theme-muted-text-color);">
                                {{ __('Last saved: :time', ['time' => optional($savedPlan->updated_at)->diffForHumans()]) }}
                            </span>
                        @endif
                    </div>

                    <div class="mt-5 space-y-3">
                        @foreach (($result['items'] ?? []) as $item)
                            <div class="rounded-[1.05rem] border p-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.48); background:
                                linear-gradient(180deg, rgba(var(--theme-accent-rgb), 0.04), transparent 28%),
                                color-mix(in srgb, var(--theme-surface-base) 95%, transparent);">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $item['date'] }}</p>
                                    <span class="text-xs uppercase tracking-[0.16em]" style="color: var(--theme-accent);">{{ $item['objective'] }}</span>
                                </div>
                                <p class="mt-3 text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $item['theme'] }}</p>
                                <p class="mt-2 text-sm leading-7" style="color: var(--theme-muted-text-color);">{{ $item['caption_brief'] }}</p>
                                @if (!empty($item['asset_brief']) || !empty($item['cta']))
                                    <div class="mt-4 grid gap-3 lg:grid-cols-2">
                                        @if (!empty($item['asset_brief']))
                                            <div class="rounded-[0.95rem] border px-4 py-3" style="border-color: rgba(var(--theme-border-color-rgb), 0.38); background-color: color-mix(in srgb, var(--theme-surface-base) 93%, transparent);">
                                                <p class="text-[11px] font-semibold uppercase tracking-[0.16em]" style="color: var(--theme-muted-text-color);">{{ __('Asset brief') }}</p>
                                                <p class="mt-2 text-sm leading-6" style="color: var(--theme-header-text-color);">{{ $item['asset_brief'] }}</p>
                                            </div>
                                        @endif
                                        @if (!empty($item['cta']))
                                            <div class="rounded-[0.95rem] border px-4 py-3" style="border-color: rgba(var(--theme-border-color-rgb), 0.38); background-color: color-mix(in srgb, var(--theme-surface-base) 93%, transparent);">
                                                <p class="text-[11px] font-semibold uppercase tracking-[0.16em]" style="color: var(--theme-muted-text-color);">{{ __('CTA') }}</p>
                                                <p class="mt-2 text-sm leading-6" style="color: var(--theme-header-text-color);">{{ $item['cta'] }}</p>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <x-ui.empty
                    class="rounded-[1.3rem] border px-6 py-10 sm:px-7"
                    icon="fa-light fa-calendar-days"
                    :title="__('No plan generated yet')"
                    :description="__('Your first planning run will appear here as dated content blocks with goals, angles, and asset prompts.')"
                >
                    <div class="inline-flex items-center gap-2 rounded-full border px-3 py-2 text-sm" style="border-color: rgba(var(--theme-border-color-rgb), 0.48); background-color: color-mix(in srgb, var(--theme-surface-base) 94%, transparent); color: var(--theme-muted-text-color);">
                        <span class="font-semibold" style="color: var(--theme-header-text-color);">{{ __('Ready to map') }}</span>
                        <span>&bull;</span>
                        <span>{{ __('Brief, plan, schedule.') }}</span>
                    </div>
                </x-ui.empty>
            @endif
        </div>
    </section>
</div>
