<div class="mx-auto max-w-[1320px] space-y-5 px-4 pb-8 pt-4 sm:px-5 xl:px-6">
    <x-ui.sub-header :compact="true" :title="__('Campaign Wizard')" :description="__('Turn your offer and goal into a campaign plan and draft content.')"><x-slot:actions>
                    <x-ui.button type="button" wire:click="generate" wire:loading.attr="disabled" wire:target="generate" :disabled="!($creditPreview['enough'] ?? true)">
                        <i class="fa-light fa-wand-magic-sparkles"></i>
                        <span wire:loading.remove wire:target="generate">{{ __('Build campaign') }}</span>
                        <span wire:loading wire:target="generate">{{ __('Building...') }}</span>
                    </x-ui.button>
                    <x-ui.button :href="route('portal.publishing.drafts')" variant="outline" wire:navigate>
                        <i class="fa-light fa-file-pen"></i>
                        {{ __('Open drafts') }}
                    </x-ui.button>
                </x-slot:actions></x-ui.sub-header>

    <section class="grid overflow-hidden rounded-[1rem] border lg:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)]" style="border-color: rgba(var(--theme-border-color-rgb), 0.65); background: var(--theme-surface-base); box-shadow: 0 18px 50px -42px rgba(15,23,42,0.45);">
        <div class="min-w-0 p-5 lg:p-6">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Campaign brief') }}</p>
                    <p class="mt-1 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Define the goal, offer, audience, timing, and channels.') }}</p>
                </div>
                <x-ui.badge variant="primary">{{ __('Brand Kit aware') }}</x-ui.badge>
            </div>

            <div class="mt-5 grid gap-4 md:grid-cols-2">
                <x-ui.input wire:model.defer="campaignName" :label="__('Campaign name')" :error="$errors->first('campaignName')" :placeholder="__('Summer launch campaign')" />
                <x-ui.input wire:model.defer="audience" :label="__('Audience')" :error="$errors->first('audience')" :placeholder="__('Agency owners, creators, local businesses...')" />
                <x-ui.date-picker wire:model.defer="startDate" name="campaign_start_date" :label="__('Start date')" :value="$startDate" :error="$errors->first('startDate')" />
                <x-ui.input type="number" min="3" max="31" wire:model.defer="days" :label="__('Campaign days')" :error="$errors->first('days')" />
            </div>

            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <x-ai.language-field name="language" wire:model.defer="language" :value="$language" />
                <x-ai.tone-field name="tone" wire:model.defer="tone" :value="$tone" :options="$toneOptions" />
            </div>

            <div class="mt-4">
                <x-ui.textarea wire:model.defer="campaignGoal" :label="__('Campaign goal')" :error="$errors->first('campaignGoal')" rows="4" placeholder="{{ __('What should this campaign achieve? What is the main message?') }}">{{ $campaignGoal }}</x-ui.textarea>
            </div>

            <div class="mt-4">
                <x-ui.textarea wire:model.defer="offer" :label="__('Offer / product')" :error="$errors->first('offer')" rows="3" placeholder="{{ __('Describe the product, service, promotion, or launch details...') }}">{{ $offer }}</x-ui.textarea>
            </div>

            <div class="mt-5 border-t pt-5" style="border-color: rgba(var(--theme-border-color-rgb), 0.55);">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Target channels') }}</p>
                    <span class="text-xs" style="color: var(--theme-muted-text-color);">{{ count($selectedAccountIds) }} {{ __('selected') }}</span>
                </div>
                @error('selectedAccountIds')
                    <p class="mt-2 text-sm font-medium" style="color: var(--theme-danger-color);">{{ $message }}</p>
                @enderror
                @if ($accounts->isNotEmpty())
                    <div class="mt-3 grid max-h-56 gap-2 overflow-y-auto pr-1 sm:grid-cols-2">
                        @foreach ($accounts as $account)
                            @php($channelId = 'campaign-channel-'.$account->id)
                            @php($providerKey = strtolower((string) $account->provider_key))
                            @php($platformIcon = match ($providerKey) {
                                'facebook' => 'fa-brands fa-facebook-f',
                                'instagram' => 'fa-brands fa-instagram',
                                'linkedin', 'linkedin_profile', 'linkedin_page' => 'fa-brands fa-linkedin-in',
                                'x', 'twitter' => 'fa-brands fa-x-twitter',
                                'tiktok' => 'fa-brands fa-tiktok',
                                'youtube' => 'fa-brands fa-youtube',
                                default => 'fa-light fa-share-nodes',
                            })
                            @php($platformTone = match ($providerKey) {
                                'facebook' => ['bg' => 'rgba(37, 99, 235, 0.12)', 'color' => '#2563eb'],
                                'instagram' => ['bg' => 'rgba(236, 72, 153, 0.12)', 'color' => '#ec4899'],
                                'linkedin', 'linkedin_profile', 'linkedin_page' => ['bg' => 'rgba(14, 118, 168, 0.12)', 'color' => '#0e76a8'],
                                'youtube' => ['bg' => 'rgba(239, 68, 68, 0.12)', 'color' => '#ef4444'],
                                'tiktok' => ['bg' => 'rgba(15, 23, 42, 0.08)', 'color' => '#020617'],
                                'x', 'twitter' => ['bg' => 'rgba(15, 23, 42, 0.08)', 'color' => '#020617'],
                                'threads' => ['bg' => 'rgba(15, 23, 42, 0.08)', 'color' => '#020617'],
                                default => ['bg' => 'rgba(var(--theme-accent-rgb), 0.08)', 'color' => 'var(--theme-accent)'],
                            })
                            <label
                                for="{{ $channelId }}"
                                class="group flex min-h-16 cursor-pointer items-center gap-3 rounded-[0.85rem] border px-3.5 py-3 transition hover:-translate-y-[1px] hover:shadow-[0_14px_34px_-28px_rgba(15,23,42,0.35)] [&:has(input:checked)]:border-[color:rgba(var(--theme-accent-rgb),0.55)] [&:has(input:checked)]:bg-[color:rgba(var(--theme-accent-rgb),0.07)]"
                                style="border-color: rgba(var(--theme-border-color-rgb), 0.58); background: var(--theme-surface-base);"
                            >
                                <input
                                    id="{{ $channelId }}"
                                    type="checkbox"
                                    wire:model.defer="selectedAccountIds"
                                    value="{{ (string) $account->id }}"
                                    class="peer sr-only"
                                >
                                <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full" style="background: {{ $platformTone['bg'] }}; color: {{ $platformTone['color'] }};">
                                    <i class="{{ $platformIcon }} text-sm"></i>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $account->display_name }}</span>
                                    <span class="mt-1 inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.12em]" style="background: rgba(var(--theme-border-color-rgb), 0.25); color: var(--theme-muted-text-color);">{{ strtoupper((string) $account->provider_key) }}</span>
                                </span>
                                <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full border text-transparent transition peer-checked:border-[color:rgba(var(--theme-accent-rgb),0.55)] peer-checked:bg-[color:rgba(var(--theme-accent-rgb),0.10)] peer-checked:text-[color:var(--theme-accent)]" style="border-color: rgba(var(--theme-border-color-rgb), 0.78);">
                                    <i class="fa-light fa-check text-xs"></i>
                                </span>
                            </label>
                        @endforeach
                    </div>
                @else
                    <x-ui.empty class="mt-3" icon="fa-light fa-share-nodes" :title="__('No active channels')" :description="__('Connect channels first, then return to build campaigns.')">
                        <x-ui.button class="mt-4" :href="route('portal.channels')" variant="outline" wire:navigate>{{ __('Open Channels') }}</x-ui.button>
                    </x-ui.empty>
                @endif
            </div>

            <div class="mt-5 flex flex-col gap-3 border-t pt-5 sm:flex-row sm:items-center sm:justify-between" style="border-color: rgba(var(--theme-border-color-rgb), 0.55);">
                <x-ui.button type="button" wire:click="generate" wire:loading.attr="disabled" wire:target="generate" :disabled="!($creditPreview['enough'] ?? true)">
                    <i class="fa-light fa-wand-magic-sparkles"></i>
                    <span wire:loading.remove wire:target="generate">{{ __('Build campaign') }}</span>
                    <span wire:loading wire:target="generate">{{ __('Building...') }}</span>
                </x-ui.button>
                <div class="inline-flex w-fit items-center gap-2 rounded-[var(--theme-button-radius,0.75rem)] border px-3 py-2 text-sm shadow-sm" style="border-color: rgba(var(--theme-border-color-rgb), 0.5); background: var(--theme-surface-base); color: var(--theme-muted-text-color);">
                    <i class="fa-light fa-coins text-xs" style="color: var(--theme-accent);"></i>
                    <span>{{ __(':credits credits', ['credits' => $creditPreview['amount'] ?? 0]) }}</span>
                    <span>&bull;</span>
                    <span>{{ ($creditPreview['unlimited'] ?? false) ? __('Unlimited plan') : __(':credits left', ['credits' => $creditPreview['remaining'] ?? 0]) }}</span>
                </div>
            </div>
        </div>

        <div class="min-w-0 border-t p-5 lg:border-l lg:border-t-0 lg:p-6" style="border-color: rgba(var(--theme-border-color-rgb), 0.65); background: color-mix(in srgb, var(--theme-surface-base) 98%, var(--theme-body-bg) 2%);">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Campaign output') }}</p>
                    <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ $result ? __('Strategy and timeline ready') : __('Result appears here after generation') }}</p>
                </div>
                @if ($result)
                    <x-ui.badge variant="primary">{{ strtoupper((string) ($result['source'] ?? 'ai')) }}</x-ui.badge>
                @endif
            </div>

            <div wire:loading.flex wire:target="generate" class="mt-4 items-center gap-3 rounded-[0.9rem] border px-4 py-3 text-sm" style="border-color: rgba(var(--theme-accent-rgb), 0.16); background-color: rgba(var(--theme-accent-rgb), 0.06); color: var(--theme-muted-text-color);">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-full" style="background-color: rgba(var(--theme-accent-rgb), 0.1); color: var(--theme-accent);">
                    <i class="fa-light fa-loader animate-spin"></i>
                </span>
                <div>
                    <p class="font-medium" style="color: var(--theme-header-text-color);">{{ __('Building campaign') }}</p>
                    <p class="text-xs sm:text-sm">{{ __('AI is creating strategy, captions, asset briefs, and draft-ready posts.') }}</p>
                </div>
            </div>

            @if ($result)
                @php($timelineCount = count($result['timeline'] ?? []))
                @php($selectedChannelCount = count($selectedAccountIds))
                @php($draftTargetCount = $timelineCount * max(1, $selectedChannelCount))
                <div class="mt-4 space-y-4">
                    <div class="rounded-[0.9rem] border p-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.52); background: var(--theme-surface-base);">
                        <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $result['summary'] ?? __('Campaign summary') }}</p>
                        @if (!empty($result['positioning']))
                            <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ $result['positioning'] }}</p>
                        @endif
                        @if (!empty($result['content_pillars']))
                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach ($result['content_pillars'] as $pillar)
                                    <span class="rounded-full px-2.5 py-1 text-xs" style="background: rgba(var(--theme-accent-rgb), 0.08); color: var(--theme-accent);">{{ $pillar }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="sticky top-3 z-20 flex flex-col gap-3 rounded-[0.9rem] border px-3 py-3 shadow-[0_18px_42px_-34px_rgba(15,23,42,0.38)] sm:flex-row sm:items-center sm:justify-between" style="border-color: rgba(var(--theme-border-color-rgb), 0.56); background: color-mix(in srgb, var(--theme-surface-base) 94%, transparent); backdrop-filter: blur(12px);">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Ready to draft') }}</p>
                            <p class="mt-0.5 text-xs" style="color: var(--theme-muted-text-color);">{{ __(':posts campaign posts x :channels channels = :drafts drafts.', ['posts' => $timelineCount, 'channels' => $selectedChannelCount, 'drafts' => $draftTargetCount]) }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <x-ui.button type="button" wire:click="createDraftPosts" wire:loading.attr="disabled" wire:target="createDraftPosts">
                                <i class="fa-light fa-file-circle-plus"></i>
                                <span wire:loading.remove wire:target="createDraftPosts">{{ __('Create draft posts') }}</span>
                                <span wire:loading wire:target="createDraftPosts">{{ __('Creating...') }}</span>
                            </x-ui.button>
                            @if ($draftsCreated > 0)
                                <x-ui.button :href="route('portal.publishing.drafts')" variant="outline" wire:navigate>{{ __('View :count drafts', ['count' => $draftsCreated]) }}</x-ui.button>
                            @endif
                        </div>
                    </div>

                    <div class="space-y-3">
                        @foreach (($result['timeline'] ?? []) as $item)
                            <article x-data="{ expanded: false, canExpand: false, checkOverflow() { this.$nextTick(() => { const caption = this.$refs.caption; this.canExpand = !!caption && caption.scrollHeight > caption.clientHeight + 1; }); } }" x-init="checkOverflow()" x-on:resize.window="checkOverflow()" class="rounded-[0.9rem] border p-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.52); background: var(--theme-surface-base);">
                                <div class="flex items-start gap-3">
                                    <div class="flex h-12 w-12 shrink-0 flex-col items-center justify-center rounded-[0.8rem] border text-center" style="border-color: rgba(var(--theme-accent-rgb), 0.22); background: rgba(var(--theme-accent-rgb), 0.08); color: var(--theme-accent);">
                                        <span class="text-[10px] font-semibold uppercase tracking-[0.12em]">{{ __('Day') }}</span>
                                        <span class="text-base font-semibold leading-none">{{ ((int) ($item['day'] ?? 0)) + 1 }}</span>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                            <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $item['theme'] ?? __('Campaign post') }}</p>
                                            @if (!empty($item['objective']))
                                                <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.12em]" style="background: rgba(var(--theme-border-color-rgb), 0.28); color: var(--theme-muted-text-color);">{{ $item['objective'] }}</span>
                                            @endif
                                        </div>
                                        <p class="mt-1 text-xs font-semibold uppercase tracking-[0.14em]" style="color: var(--theme-muted-text-color);">{{ $item['date'] ?? '' }}</p>
                                    </div>
                                </div>

                                @if (!empty($item['hook']))
                                    <p class="mt-3 text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $item['hook'] }}</p>
                                @endif

                                @php($captionText = $item['caption'] ?? data_get($item, 'captions.default', ''))
                                <p x-ref="caption" class="mt-2 whitespace-pre-line text-sm leading-7" :class="expanded ? '' : 'line-clamp-4'" style="color: var(--theme-muted-text-color);">{{ $captionText }}</p>
                                @if (trim((string) $captionText) !== '')
                                    <button x-show="canExpand || expanded" type="button" class="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold transition hover:opacity-75" style="display: none; color: var(--theme-accent);" x-on:click="expanded = ! expanded; checkOverflow()">
                                        <span x-text="expanded ? @js(__('Show less')) : @js(__('Read full caption'))"></span>
                                        <i class="fa-light fa-chevron-down text-[10px] transition" :class="expanded ? 'rotate-180' : ''"></i>
                                    </button>
                                @endif

                                <div class="mt-4 space-y-3 border-t pt-3" style="border-color: rgba(var(--theme-border-color-rgb), 0.45);">
                                    @if (!empty($item['asset_brief']))
                                        <div class="grid gap-2 sm:grid-cols-[5rem_minmax(0,1fr)]">
                                            <p class="text-[10px] font-semibold uppercase tracking-[0.12em]" style="color: var(--theme-muted-text-color);">{{ __('Asset') }}</p>
                                            <p class="text-sm leading-6" style="color: var(--theme-header-text-color);">{{ $item['asset_brief'] }}</p>
                                        </div>
                                    @endif
                                    @if (!empty($item['cta']))
                                        <div class="grid gap-2 sm:grid-cols-[5rem_minmax(0,1fr)]">
                                            <p class="text-[10px] font-semibold uppercase tracking-[0.12em]" style="color: var(--theme-muted-text-color);">{{ __('CTA') }}</p>
                                            <p class="text-sm leading-6" style="color: var(--theme-header-text-color);">{{ $item['cta'] }}</p>
                                        </div>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            @else
                <x-ui.empty class="mt-4 min-h-[28rem] content-center" icon="fa-light fa-bullseye-arrow" :title="__('No campaign generated yet')" :description="__('Fill the brief and build a campaign to create strategy, content timeline, and draft-ready posts.')" />
            @endif
        </div>
    </section>

    @if ($promptHistory->isNotEmpty())
        <section class="rounded-[1rem] border p-4 sm:p-5" style="border-color: rgba(var(--theme-border-color-rgb), 0.65); background: var(--theme-surface-base);">
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Recent campaign prompts') }}</p>
                <x-ui.button :href="route('portal.ai-studio.prompt-history', ['module' => 'campaign_wizard'])" variant="outline" size="sm" wire:navigate>{{ __('View all') }}</x-ui.button>
            </div>
            <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                @foreach ($promptHistory as $history)
                    <button type="button" wire:click="loadPromptHistory({{ $history->id }})" class="rounded-[0.85rem] border p-4 text-left transition hover:-translate-y-0.5" style="border-color: rgba(var(--theme-border-color-rgb), 0.48); background: color-mix(in srgb, var(--theme-surface-base) 96%, var(--theme-body-bg) 4%);">
                        <p class="line-clamp-1 text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $history->title ?: __('Campaign prompt') }}</p>
                        <p class="mt-2 line-clamp-2 text-xs leading-5" style="color: var(--theme-muted-text-color);">{{ $history->prompt }}</p>
                    </button>
                @endforeach
            </div>
        </section>
    @endif
</div>
