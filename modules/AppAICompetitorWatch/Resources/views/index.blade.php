<div
    class="mx-auto max-w-[1320px] space-y-5 px-4 pb-8 pt-4 sm:px-5 xl:px-6"
    x-data="{
        selectedPlatforms: @js(array_values($platforms)),
        maxPlatforms: @js(count($platformOptions)),
        platformSelected(value) {
            return this.selectedPlatforms.includes(value);
        },
        togglePlatform(value) {
            if (this.platformSelected(value)) {
                this.selectedPlatforms = this.selectedPlatforms.filter((item) => item !== value);
            } else if (this.selectedPlatforms.length < this.maxPlatforms) {
                this.selectedPlatforms.push(value);
            }

            this.$wire.set('platforms', this.selectedPlatforms, false);
        },
        platformCardStyle(value) {
            return this.platformSelected(value)
                ? 'border-color: rgba(var(--theme-accent-rgb),0.48); background: rgba(var(--theme-accent-rgb),0.055);'
                : 'border-color: rgba(var(--theme-border-color-rgb), 0.58); background: var(--theme-surface-base);';
        },
    }"
    x-on:select-menu:change.window="
        if (($event.detail?.name || '') === 'competitor_market_selector') { $wire.set('market', String($event.detail?.value || ''), false) }
        if (($event.detail?.name || '') === 'competitor_audience_selector') { $wire.set('audience', String($event.detail?.value || ''), false) }
    "
>
    <x-ui.sub-header :compact="true" :title="__('Competitor Watch')" :description="__('Compare the competitor information you provide and find content opportunities.')"><x-slot:actions>
                    <x-ui.button type="button" wire:click="analyze" wire:loading.attr="disabled" wire:target="analyze" :disabled="!($creditPreview['enough'] ?? true)">
                        <i class="fa-light fa-chart-network"></i>
                        <span wire:loading.remove wire:target="analyze">{{ __('Analyze competitors') }}</span>
                        <span wire:loading wire:target="analyze">{{ __('Analyzing...') }}</span>
                    </x-ui.button>
                    @if (Route::has('portal.ai-campaign-wizard'))
                        <x-ui.button :href="route('portal.ai-campaign-wizard')" variant="outline" wire:navigate>
                            <i class="fa-light fa-bullseye-arrow"></i>
                            {{ __('Build campaign') }}
                        </x-ui.button>
                    @endif
                </x-slot:actions></x-ui.sub-header>

    <section class="grid overflow-hidden rounded-[1rem] border lg:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)]" style="border-color: rgba(var(--theme-border-color-rgb), 0.65); background: var(--theme-surface-base); box-shadow: 0 18px 50px -42px rgba(15,23,42,0.45);">
        <div class="min-w-0 p-5 lg:p-6">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Competitor brief') }}</p>
                    <p class="mt-1 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Add competitors, define your positioning context, then find content openings.') }}</p>
                </div>
                <x-ui.badge variant="primary">{{ __('Brand Kit aware') }}</x-ui.badge>
            </div>

            <div class="mt-5 grid gap-4 md:grid-cols-2">
                <x-ui.input wire:model="brand" :label="__('Your brand / product')" :error="$errors->first('brand')" :placeholder="__('Stackposts')" />
                <x-ui.input wire:model="industry" :label="__('Industry / niche')" :error="$errors->first('industry')" :placeholder="__('Social media management SaaS')" />
                <x-ui.select-menu
                    name="competitor_audience_selector"
                    :label="__('Audience')"
                    :value="$audience"
                    :options="$audienceOptions"
                    :placeholder="__('Choose an audience')"
                    :searchable="true"
                    :search-placeholder="__('Search audience...')"
                    :error="$errors->first('audience')"
                />
                <x-ui.select-menu
                    name="competitor_market_selector"
                    :label="__('Market / country')"
                    :value="$market"
                    :options="$countryOptions"
                    :placeholder="__('Choose a country')"
                    :searchable="true"
                    :search-placeholder="__('Search country or code...')"
                    :error="$errors->first('market')"
                />
            </div>

            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <x-ai.language-field name="language" wire:model="language" :value="$language" />
                <x-ai.tone-field name="tone" wire:model="tone" :value="$tone" :options="$toneOptions" />
            </div>

            <div class="mt-4">
                <x-ui.textarea wire:model.defer="competitors" :label="__('Competitors')" :error="$errors->first('competitors')" rows="4" placeholder="{{ __('One per line: competitor name, website, Instagram handle, Facebook page, LinkedIn page...') }}">{{ $competitors }}</x-ui.textarea>
            </div>

            <div class="mt-4">
                <x-ui.textarea wire:model.defer="goal" :label="__('Competitive goal')" :error="$errors->first('goal')" rows="3" placeholder="{{ __('What do you want to learn or beat? More engagement, clearer positioning, stronger launch angles, better offers...') }}">{{ $goal }}</x-ui.textarea>
            </div>

            <div class="mt-5 border-t pt-5" style="border-color: rgba(var(--theme-border-color-rgb), 0.55);">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Target platforms') }}</p>
                    <span class="text-xs" style="color: var(--theme-muted-text-color);"><span x-text="selectedPlatforms.length"></span> {{ __('selected') }}</span>
                </div>
                @error('platforms')
                    <p class="mt-2 text-sm font-medium" style="color: var(--theme-danger-color);">{{ $message }}</p>
                @enderror
                @php
                    $platformBrandTones = [
                        'facebook' => ['surface' => 'rgba(24,119,242,0.10)', 'text' => '#1877F2'],
                        'instagram' => ['surface' => 'rgba(225,48,108,0.10)', 'text' => '#E1306C'],
                        'linkedin' => ['surface' => 'rgba(10,102,194,0.10)', 'text' => '#0A66C2'],
                        'x' => ['surface' => 'rgba(15,23,42,0.08)', 'text' => '#0F172A'],
                        'tiktok' => ['surface' => 'rgba(0,0,0,0.08)', 'text' => '#111827'],
                        'threads' => ['surface' => 'rgba(15,23,42,0.08)', 'text' => '#111827'],
                        'youtube' => ['surface' => 'rgba(255,0,0,0.10)', 'text' => '#FF0000'],
                    ];
                @endphp
                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                    @foreach ($platformOptions as $platform)
                        @php
                            $platformValue = (string) $platform['value'];
                            $brandTone = $platformBrandTones[$platformValue] ?? ['surface' => 'rgba(var(--theme-border-color-rgb),0.14)', 'text' => 'var(--theme-muted-text-color)'];
                        @endphp
                        <button
                            type="button"
                            x-on:click="togglePlatform(@js($platformValue))"
                            class="group rounded-[var(--theme-card-radius,0.9rem)] border px-3 py-3 text-left transition hover:-translate-y-[1px] hover:shadow-[0_14px_34px_-28px_rgba(15,23,42,0.35)]"
                            x-bind:class="platformSelected(@js($platformValue)) ? 'ring-1 ring-[color:rgba(var(--theme-accent-rgb),0.22)]' : ''"
                            x-bind:aria-pressed="platformSelected(@js($platformValue)) ? 'true' : 'false'"
                            x-bind:style="platformCardStyle(@js($platformValue))"
                        >
                            <span class="flex min-w-0 items-center gap-3">
                                <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full border text-[15px]" style="border-color: rgba(var(--theme-border-color-rgb), 0.54); background: {{ $brandTone['surface'] }}; color: {{ $brandTone['text'] }};">
                                    <i class="{{ $platform['icon'] }} text-sm"></i>
                                </span>
                                <span class="min-w-0 flex-1 truncate text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $platform['label'] }}</span>
                                <span x-show="platformSelected(@js($platformValue))" class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full border text-xs" style="border-color: rgba(var(--theme-accent-rgb),0.34); background: rgba(var(--theme-accent-rgb),0.10); color: var(--theme-accent);">
                                    <i class="fa-light fa-check"></i>
                                </span>
                                <span x-show="!platformSelected(@js($platformValue))" class="h-6 w-6 shrink-0"></span>
                            </span>
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="mt-5 flex flex-col gap-3 border-t pt-5 sm:flex-row sm:items-center sm:justify-between" style="border-color: rgba(var(--theme-border-color-rgb), 0.55);">
                <x-ui.button type="button" wire:click="analyze" wire:loading.attr="disabled" wire:target="analyze" :disabled="!($creditPreview['enough'] ?? true)">
                    <i class="fa-light fa-chart-network"></i>
                    <span wire:loading.remove wire:target="analyze">{{ __('Analyze competitors') }}</span>
                    <span wire:loading wire:target="analyze">{{ __('Analyzing...') }}</span>
                </x-ui.button>
                <div class="inline-flex w-fit items-center gap-2 rounded-[var(--theme-button-radius,0.75rem)] border px-3 py-2 text-sm shadow-sm" style="border-color: rgba(var(--theme-border-color-rgb), 0.5); background: var(--theme-surface-base); color: var(--theme-muted-text-color);">
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
        </div>

        <div class="min-w-0 border-t p-5 lg:border-l lg:border-t-0 lg:p-6" style="border-color: rgba(var(--theme-border-color-rgb), 0.65); background: color-mix(in srgb, var(--theme-surface-base) 98%, var(--theme-body-bg) 2%);">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Competitive report') }}</p>
                    <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ $result ? __('Gaps and opportunities ready') : __('Result appears here after analysis') }}</p>
                </div>
                @if ($result)
                    <x-ui.badge variant="primary">{{ strtoupper((string) ($result['source'] ?? 'ai')) }}</x-ui.badge>
                @endif
            </div>

            <div wire:loading.flex wire:target="analyze" class="mt-4 items-center gap-3 rounded-[0.9rem] border px-4 py-3 text-sm" style="border-color: rgba(var(--theme-accent-rgb), 0.16); background-color: rgba(var(--theme-accent-rgb), 0.06); color: var(--theme-muted-text-color);">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-full" style="background-color: rgba(var(--theme-accent-rgb), 0.1); color: var(--theme-accent);">
                    <i class="fa-light fa-loader animate-spin"></i>
                </span>
                <div>
                    <p class="font-medium" style="color: var(--theme-header-text-color);">{{ __('Analyzing competitor patterns') }}</p>
                    <p class="text-xs sm:text-sm">{{ __('AI is comparing positioning, hooks, gaps, and content openings.') }}</p>
                </div>
            </div>

            @if ($result)
                <div class="mt-4 space-y-4">
                    <div class="rounded-[0.9rem] border p-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.52); background: var(--theme-surface-base);">
                        <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $result['summary'] ?? __('Competitor watch summary') }}</p>
                        @if (($result['source'] ?? null) === 'fallback')
                            <div class="mt-3 rounded-[0.8rem] border px-3 py-2.5 text-sm" style="border-color: rgba(var(--theme-warning-color-rgb, 245,158,11), 0.28); background: rgba(var(--theme-warning-color-rgb, 245,158,11), 0.08); color: var(--theme-muted-text-color);">
                                <span class="font-semibold" style="color: var(--theme-header-text-color);">{{ __('AI analysis unavailable.') }}</span>
                                {{ __('This is a sample report generated from your brief.') }}
                                @if (!empty($result['notice']))
                                    <span class="block pt-1 text-xs">{{ $result['notice'] }}</span>
                                @endif
                            </div>
                        @endif
                        @if (!empty($result['gaps']))
                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach ($result['gaps'] as $gap)
                                    <span class="rounded-full px-2.5 py-1 text-xs" style="background: rgba(var(--theme-accent-rgb), 0.08); color: var(--theme-accent);">{{ $gap }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    @if (!empty($result['pattern_map']))
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach ([
                                'common_hooks' => __('Common hooks'),
                                'common_formats' => __('Common formats'),
                                'offer_angles' => __('Offer angles'),
                                'visual_patterns' => __('Visual patterns'),
                            ] as $key => $label)
                                <div class="rounded-[0.9rem] border p-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.52); background: var(--theme-surface-base);">
                                    <p class="text-xs font-semibold uppercase tracking-[0.14em]" style="color: var(--theme-muted-text-color);">{{ $label }}</p>
                                    <div class="mt-2 space-y-1.5">
                                        @foreach ((array) data_get($result, 'pattern_map.'.$key, []) as $item)
                                            <p class="text-sm leading-6" style="color: var(--theme-header-text-color);">{{ $item }}</p>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if (!empty($result['opportunities']))
                        <div class="space-y-3">
                            <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Content opportunities') }}</p>
                            @foreach ($result['opportunities'] as $opportunity)
                                <article class="rounded-[0.95rem] border p-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.52); background: var(--theme-surface-base);">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <h3 class="text-sm font-semibold leading-6" style="color: var(--theme-header-text-color);">{{ $opportunity['title'] ?? __('Opportunity') }}</h3>
                                            <p class="mt-1 text-xs uppercase tracking-[0.14em]" style="color: var(--theme-muted-text-color);">{{ __('Confidence') }} {{ $opportunity['confidence'] ?? 70 }}%</p>
                                        </div>
                                        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-[0.8rem]" style="background: rgba(var(--theme-accent-rgb), 0.10); color: var(--theme-accent);">
                                            <i class="fa-light fa-sparkles"></i>
                                        </span>
                                    </div>
                                    @if (!empty($opportunity['why_it_matters']))
                                        <p class="mt-3 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ $opportunity['why_it_matters'] }}</p>
                                    @endif
                                    @if (!empty($opportunity['hook']))
                                        <div class="mt-3 rounded-[0.75rem] border px-3 py-2.5" style="border-color: rgba(var(--theme-border-color-rgb), 0.45);">
                                            <p class="text-[10px] font-semibold uppercase tracking-[0.12em]" style="color: var(--theme-muted-text-color);">{{ __('Hook') }}</p>
                                            <p class="mt-1 text-sm" style="color: var(--theme-header-text-color);">{{ $opportunity['hook'] }}</p>
                                        </div>
                                    @endif
                                    @if (!empty($opportunity['content_brief']))
                                        <p class="mt-3 text-sm leading-6" style="color: var(--theme-header-text-color);">{{ $opportunity['content_brief'] }}</p>
                                    @endif
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @foreach ((array) ($opportunity['recommended_platforms'] ?? []) as $platform)
                                            <span class="rounded-full px-2.5 py-1 text-xs uppercase tracking-[0.08em]" style="background: rgba(var(--theme-border-color-rgb),0.14); color: var(--theme-muted-text-color);">{{ $platform }}</span>
                                        @endforeach
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @endif

                    @if (!empty($result['competitor_profiles']))
                        <div class="space-y-3">
                            <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Competitor profiles') }}</p>
                            <div class="grid gap-3 md:grid-cols-2">
                                @foreach ($result['competitor_profiles'] as $profile)
                                    <article class="rounded-[0.95rem] border p-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.52); background: var(--theme-surface-base);">
                                        <h3 class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $profile['name'] ?? __('Competitor') }}</h3>
                                        <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ $profile['likely_positioning'] ?? '' }}</p>
                                        <div class="mt-3 space-y-2">
                                            @foreach (['strengths' => __('Strengths'), 'weaknesses' => __('Weaknesses')] as $key => $label)
                                                <div>
                                                    <p class="text-[10px] font-semibold uppercase tracking-[0.12em]" style="color: var(--theme-muted-text-color);">{{ $label }}</p>
                                                    <div class="mt-1 space-y-1">
                                                        @foreach ((array) ($profile[$key] ?? []) as $item)
                                                            <p class="text-xs leading-5" style="color: var(--theme-header-text-color);">{{ $item }}</p>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @else
                <div class="mt-4 flex min-h-[28rem] items-center justify-center rounded-[1rem] border border-dashed p-8 text-center" style="border-color: rgba(var(--theme-border-color-rgb),0.55); background: rgba(var(--theme-border-color-rgb),0.025);">
                    <div class="max-w-md">
                        <span class="mx-auto inline-flex h-12 w-12 items-center justify-center rounded-[1rem]" style="background: rgba(var(--theme-accent-rgb),0.10); color: var(--theme-accent);">
                            <i class="fa-light fa-binoculars text-lg"></i>
                        </span>
                        <h3 class="mt-4 text-base font-semibold" style="color: var(--theme-header-text-color);">{{ __('No competitor report yet') }}</h3>
                        <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Enter competitors and run an analysis to reveal patterns, content gaps, and next moves.') }}</p>
                    </div>
                </div>
            @endif
        </div>
    </section>

    @if ($promptHistory->isNotEmpty())
        <section class="rounded-[1rem] border p-5" style="border-color: rgba(var(--theme-border-color-rgb), 0.65); background: var(--theme-surface-base);">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Prompt history') }}</p>
                    <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Reload previous competitor watch reports.') }}</p>
                </div>
                <x-ui.badge>{{ $promptHistory->count() }}</x-ui.badge>
            </div>
            <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                @foreach ($promptHistory as $history)
                    <button type="button" wire:click="loadPromptHistory({{ $history->id }})" class="rounded-[0.85rem] border px-4 py-3 text-left transition hover:-translate-y-[1px]" style="border-color: rgba(var(--theme-border-color-rgb), 0.52); background: var(--theme-surface-base);">
                        <p class="truncate text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $history->title ?: __('Competitor Watch') }}</p>
                        <p class="mt-1 line-clamp-2 text-xs leading-5" style="color: var(--theme-muted-text-color);">{{ $history->prompt }}</p>
                    </button>
                @endforeach
            </div>
        </section>
    @endif
</div>
