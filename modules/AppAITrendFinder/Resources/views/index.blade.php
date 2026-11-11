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
        platformIconStyle(value, surface, text) {
            return 'border-color: ' + (this.platformSelected(value) ? 'rgba(var(--theme-accent-rgb),0.24)' : 'rgba(var(--theme-border-color-rgb), 0.54)') + '; background: ' + surface + '; color: ' + text + ';';
        },
    }"
    x-on:select-menu:change.window="
        if (($event.detail?.name || '') === 'market_selector') { $wire.selectMarket(String($event.detail?.value || '')) }
        if (($event.detail?.name || '') === 'audience_selector') { $wire.selectAudience(String($event.detail?.value || '')) }
    "
>
    <x-ui.sub-header :compact="true" :title="__('Trend Finder')" :description="__('Explore content angles for your topic and audience.')"><x-slot:actions>
                    <x-ui.button type="button" wire:click="discover" wire:loading.attr="disabled" wire:target="discover" :disabled="!($creditPreview['enough'] ?? true)">
                        <i class="fa-light fa-sparkles"></i>
                        <span wire:loading.remove wire:target="discover">{{ __('Find trends') }}</span>
                        <span wire:loading wire:target="discover">{{ __('Scanning...') }}</span>
                    </x-ui.button>
                    <x-ui.button :href="route('portal.ai-content')" variant="outline" wire:navigate>
                        <i class="fa-light fa-pen-nib"></i>
                        {{ __('Create caption') }}
                    </x-ui.button>
                </x-slot:actions></x-ui.sub-header>

    <section class="grid overflow-hidden rounded-[1rem] border lg:grid-cols-[minmax(0,0.92fr)_minmax(0,1.08fr)]" style="border-color: rgba(var(--theme-border-color-rgb), 0.65); background: var(--theme-surface-base); box-shadow: 0 18px 50px -42px rgba(15,23,42,0.45);">
        <div class="min-w-0 p-5 lg:p-6">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Trend brief') }}</p>
                    <p class="mt-1 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Define the market, audience, goal, and channels to shape useful trend opportunities.') }}</p>
                </div>
                <x-ui.badge variant="primary">{{ __('Brand Kit aware') }}</x-ui.badge>
            </div>

            <div class="mt-5 grid gap-4 md:grid-cols-2">
                <x-ui.input wire:model.live="industry" :label="__('Industry / niche')" :error="$errors->first('industry')" :placeholder="__('SaaS social media tools')" />
                <x-ui.select-menu
                    name="audience_selector"
                    :label="__('Audience')"
                    :value="$audience"
                    :options="$audienceOptions"
                    :placeholder="__('Choose an audience')"
                    :searchable="true"
                    :search-placeholder="__('Search audience...')"
                    :error="$errors->first('audience')"
                />
                <x-ui.select-menu
                    name="market_selector"
                    :label="__('Market / country')"
                    :value="$market"
                    :options="$countryOptions"
                    :placeholder="__('Choose a country')"
                    :searchable="true"
                    :search-placeholder="__('Search country or code...')"
                    :error="$errors->first('market')"
                />
                <x-ui.input type="number" min="3" max="12" wire:model.live="total" :label="__('Ideas to generate')" :error="$errors->first('total')" />
            </div>

            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <x-ai.language-field name="language" wire:model.live="language" :value="$language" />
                <x-ai.tone-field name="tone" wire:model.live="tone" :value="$tone" :options="$toneOptions" />
            </div>

            <div class="mt-4">
                <x-ui.textarea wire:model.defer="goal" :label="__('Marketing goal')" :error="$errors->first('goal')" rows="4" placeholder="{{ __('What should these trend ideas help achieve? Engagement, lead generation, launches, community growth...') }}">{{ $goal }}</x-ui.textarea>
            </div>

            <div class="mt-4">
                <x-ui.textarea wire:model.defer="season" :label="__('Season / event / context')" :error="$errors->first('season')" rows="3" placeholder="{{ __('Optional: upcoming holiday, launch window, local event, promotion, or market shift...') }}">{{ $season }}</x-ui.textarea>
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
                    $selectedPlatformValues = collect($platforms)->map(fn ($value) => (string) $value)->all();
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
                            $isSelected = in_array($platformValue, $selectedPlatformValues, true);
                            $brandTone = $platformBrandTones[$platformValue] ?? ['surface' => 'rgba(var(--theme-border-color-rgb),0.14)', 'text' => 'var(--theme-muted-text-color)'];
                        @endphp
                        <button
                            type="button"
                            x-on:click="togglePlatform(@js($platformValue))"
                            @class([
                                'group rounded-[var(--theme-card-radius,0.9rem)] border px-3 py-3 text-left transition hover:-translate-y-[1px] hover:shadow-[0_14px_34px_-28px_rgba(15,23,42,0.35)] disabled:pointer-events-none disabled:opacity-70',
                            ])
                            x-bind:class="platformSelected(@js($platformValue)) ? 'ring-1 ring-[color:rgba(var(--theme-accent-rgb),0.22)]' : ''"
                            x-bind:aria-pressed="platformSelected(@js($platformValue)) ? 'true' : 'false'"
                            x-bind:style="platformCardStyle(@js($platformValue))"
                        >
                            <span class="flex min-w-0 items-center gap-3">
                                <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full border text-[15px]" x-bind:style="platformIconStyle(@js($platformValue), @js($brandTone['surface']), @js($brandTone['text']))">
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
                <x-ui.button type="button" wire:click="discover" wire:loading.attr="disabled" wire:target="discover" :disabled="!($creditPreview['enough'] ?? true)">
                    <i class="fa-light fa-radar"></i>
                    <span wire:loading.remove wire:target="discover">{{ __('Find trends') }}</span>
                    <span wire:loading wire:target="discover">{{ __('Scanning...') }}</span>
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
                    <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Trend opportunities') }}</p>
                    <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ $result ? __('Signals and content angles ready') : __('Result appears here after discovery') }}</p>
                </div>
                @if ($result)
                    <x-ui.badge variant="primary">{{ strtoupper((string) ($result['source'] ?? 'ai')) }}</x-ui.badge>
                @endif
            </div>

            <div wire:loading.flex wire:target="discover" class="mt-4 items-center gap-3 rounded-[0.9rem] border px-4 py-3 text-sm" style="border-color: rgba(var(--theme-accent-rgb), 0.16); background-color: rgba(var(--theme-accent-rgb), 0.06); color: var(--theme-muted-text-color);">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-full" style="background-color: rgba(var(--theme-accent-rgb), 0.1); color: var(--theme-accent);">
                    <i class="fa-light fa-loader animate-spin"></i>
                </span>
                <div>
                    <p class="font-medium" style="color: var(--theme-header-text-color);">{{ __('Finding trend opportunities') }}</p>
                    <p class="text-xs sm:text-sm">{{ __('AI is shaping timely topics into hooks, angles, platform fit, and planning hints.') }}</p>
                </div>
            </div>

            @if ($result)
                <div class="mt-4 space-y-4">
                    <div class="rounded-[0.9rem] border p-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.52); background: var(--theme-surface-base);">
                        <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $result['summary'] ?? __('Trend summary') }}</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <x-ui.button :href="route('portal.ai-content')" size="sm" variant="outline" wire:navigate>
                                <i class="fa-light fa-pen-nib"></i>
                                {{ __('Create caption') }}
                            </x-ui.button>
                            @if (Route::has('portal.ai-campaign-wizard'))
                                <x-ui.button :href="route('portal.ai-campaign-wizard')" size="sm" variant="outline" wire:navigate>
                                    <i class="fa-light fa-bullseye-arrow"></i>
                                    {{ __('Build campaign') }}
                                </x-ui.button>
                            @endif
                            @if (Route::has('portal.ai-content-planner'))
                                <x-ui.button :href="route('portal.ai-content-planner')" size="sm" variant="outline" wire:navigate>
                                    <i class="fa-light fa-calendar-lines-pen"></i>
                                    {{ __('Plan calendar') }}
                                </x-ui.button>
                            @endif
                        </div>
                    </div>

                    <div class="grid max-h-[52rem] gap-3 overflow-y-auto pr-1">
                        @foreach (($result['signals'] ?? []) as $signal)
                            <article class="rounded-[0.95rem] border p-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.52); background: var(--theme-surface-base);">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <h3 class="text-sm font-semibold leading-6" style="color: var(--theme-header-text-color);">{{ $signal['topic'] ?? __('Trend idea') }}</h3>
                                        <p class="mt-1 text-xs uppercase tracking-[0.14em]" style="color: var(--theme-muted-text-color);">{{ $signal['urgency'] ?? __('Medium') }} &bull; {{ __('Confidence') }} {{ $signal['confidence'] ?? 70 }}%</p>
                                    </div>
                                    <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-[0.8rem]" style="background: rgba(var(--theme-success-color-rgb), 0.10); color: rgb(5,150,105);">
                                        <i class="fa-light fa-arrow-trend-up"></i>
                                    </span>
                                </div>

                                @if (!empty($signal['why_now']))
                                    <p class="mt-3 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ $signal['why_now'] }}</p>
                                @endif

                                <div class="mt-3 grid gap-3 md:grid-cols-2">
                                    <div class="rounded-[0.75rem] border px-3 py-2.5" style="border-color: rgba(var(--theme-border-color-rgb), 0.45);">
                                        <p class="text-[10px] font-semibold uppercase tracking-[0.12em]" style="color: var(--theme-muted-text-color);">{{ __('Angle') }}</p>
                                        <p class="mt-1 text-sm leading-6" style="color: var(--theme-header-text-color);">{{ $signal['angle'] ?? '' }}</p>
                                    </div>
                                    <div class="rounded-[0.75rem] border px-3 py-2.5" style="border-color: rgba(var(--theme-border-color-rgb), 0.45);">
                                        <p class="text-[10px] font-semibold uppercase tracking-[0.12em]" style="color: var(--theme-muted-text-color);">{{ __('Audience fit') }}</p>
                                        <p class="mt-1 text-sm leading-6" style="color: var(--theme-header-text-color);">{{ $signal['audience_fit'] ?? '' }}</p>
                                    </div>
                                </div>

                                @if (!empty($signal['hooks']))
                                    <div class="mt-3 rounded-[0.75rem] border px-3 py-2.5" style="border-color: rgba(var(--theme-border-color-rgb), 0.45);">
                                        <p class="text-[10px] font-semibold uppercase tracking-[0.12em]" style="color: var(--theme-muted-text-color);">{{ __('Hooks') }}</p>
                                        <div class="mt-2 space-y-1.5">
                                            @foreach ($signal['hooks'] as $hook)
                                                <p class="text-sm" style="color: var(--theme-header-text-color);">{{ $hook }}</p>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                <div class="mt-3 flex flex-wrap gap-2">
                                    @foreach (($signal['suggested_platforms'] ?? []) as $platform)
                                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold uppercase tracking-[0.08em]" style="background: rgba(var(--theme-accent-rgb), 0.08); color: var(--theme-accent);">{{ $platform }}</span>
                                    @endforeach
                                    @foreach (($signal['tags'] ?? []) as $tag)
                                        <span class="rounded-full border px-2.5 py-1 text-xs" style="border-color: rgba(var(--theme-border-color-rgb), 0.55); color: var(--theme-muted-text-color);">#{{ ltrim((string) $tag, '#') }}</span>
                                    @endforeach
                                </div>

                                <div class="mt-3 grid gap-2 md:grid-cols-2">
                                    @if (!empty($signal['visual_brief']))
                                        <div class="rounded-[0.75rem] border px-3 py-2.5" style="border-color: rgba(var(--theme-border-color-rgb), 0.45);">
                                            <p class="text-[10px] font-semibold uppercase tracking-[0.12em]" style="color: var(--theme-muted-text-color);">{{ __('Visual') }}</p>
                                            <p class="mt-1 line-clamp-3 text-sm" style="color: var(--theme-header-text-color);">{{ $signal['visual_brief'] }}</p>
                                        </div>
                                    @endif
                                    @if (!empty($signal['cta']))
                                        <div class="rounded-[0.75rem] border px-3 py-2.5" style="border-color: rgba(var(--theme-border-color-rgb), 0.45);">
                                            <p class="text-[10px] font-semibold uppercase tracking-[0.12em]" style="color: var(--theme-muted-text-color);">{{ __('CTA') }}</p>
                                            <p class="mt-1 line-clamp-3 text-sm" style="color: var(--theme-header-text-color);">{{ $signal['cta'] }}</p>
                                        </div>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            @else
                <x-ui.empty class="mt-4 min-h-[28rem] content-center" icon="fa-light fa-radar" :title="__('No trends discovered yet')" :description="__('Fill the trend brief to generate timely content opportunities, hooks, and platform-ready angles.')" />
            @endif
        </div>
    </section>

    @if (!empty($result['calendar']) || $promptHistory->isNotEmpty())
        <section class="grid gap-5 lg:grid-cols-2">
            @if (!empty($result['calendar']))
                <x-ui.card>
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Planning hints') }}</p>
                            <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Use these to decide when each trend angle should move into publishing.') }}</p>
                        </div>
                        <i class="fa-light fa-calendar-clock" style="color: var(--theme-accent);"></i>
                    </div>
                    <div class="mt-4 grid gap-2">
                        @foreach ($result['calendar'] as $item)
                            <div class="rounded-[0.85rem] border px-4 py-3" style="border-color: rgba(var(--theme-border-color-rgb), 0.52);">
                                <div class="flex items-start justify-between gap-3">
                                    <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $item['theme'] ?? __('Planning idea') }}</p>
                                    <x-ui.badge>{{ $item['timing'] ?? '' }}</x-ui.badge>
                                </div>
                                <p class="mt-1 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ $item['reason'] ?? '' }}</p>
                            </div>
                        @endforeach
                    </div>
                </x-ui.card>
            @endif

            @if ($promptHistory->isNotEmpty())
                <x-ui.card>
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Prompt history') }}</p>
                            <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Reload previous trend runs with one click.') }}</p>
                        </div>
                        <x-ui.badge>{{ $promptHistory->count() }}</x-ui.badge>
                    </div>
                    <div class="mt-4 grid gap-2">
                        @foreach ($promptHistory as $history)
                            <button type="button" wire:click="loadPromptHistory({{ $history->id }})" class="rounded-[0.85rem] border px-4 py-3 text-left transition hover:-translate-y-[1px]" style="border-color: rgba(var(--theme-border-color-rgb), 0.52); background: var(--theme-surface-base);">
                                <p class="line-clamp-1 text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $history->title }}</p>
                                <p class="mt-1 line-clamp-2 text-xs" style="color: var(--theme-muted-text-color);">{{ $history->prompt }}</p>
                            </button>
                        @endforeach
                    </div>
                </x-ui.card>
            @endif
        </section>
    @endif
</div>
