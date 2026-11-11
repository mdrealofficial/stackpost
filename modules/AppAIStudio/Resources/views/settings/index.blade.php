@component(theme_view('layouts.app', 'app'), ['title' => __('AI Settings')])
    @php
        $resolveLanguageLabel = function ($code) {
            $code = (string) $code;

            return collect(world_languages())->firstWhere('code', $code)['name'] ?? strtoupper($code);
        };

        $defaultToneLabel = collect($toneOptions)->firstWhere('value', $userSettings['default_tone'] ?? $defaults['default_tone'])['label']
            ?? ucfirst((string) ($userSettings['default_tone'] ?? $defaults['default_tone']));

        $brandSummary = collect([
            $workspaceSettings['brand_name'] ?? null,
            $workspaceSettings['brand_voice'] ?? null,
            $workspaceSettings['target_audience'] ?? null,
        ])->filter(fn ($value) => filled($value))->count();
    @endphp

    <div class="mx-auto max-w-[1320px] space-y-5 px-4 pb-8 pt-4 sm:px-5 xl:px-6">
        <x-ui.sub-header :compact="true" :title="__('AI Settings')" :description="__('Set your personal defaults and shared brand voice.')"><x-slot:actions>
                        <x-ui.button type="button" x-data x-on:click="window.dispatchEvent(new CustomEvent('ai-settings-tab', { detail: 'brand' }))">
                            <i class="fa-light fa-signature"></i>
                            {{ __('Open Brand Kit') }}
                        </x-ui.button>
                        <x-ui.button :href="route('portal.ai-studio.prompt-history')" variant="outline" wire:navigate>
                            <i class="fa-light fa-clock-rotate-left"></i>
                            {{ __('Prompt History') }}
                        </x-ui.button>
                    </x-slot:actions></x-ui.sub-header>

        <section
            x-data="{ settingsTab: 'defaults' }"
            x-on:ai-settings-tab.window="settingsTab = $event.detail"
            class="overflow-hidden rounded-[1rem] border"
            style="border-color: rgba(var(--theme-border-color-rgb), 0.65); background: var(--theme-surface-base); box-shadow: 0 18px 50px -42px rgba(15,23,42,0.45);"
        >
            <div class="border-b px-4 py-3 sm:px-5" style="border-color: rgba(var(--theme-border-color-rgb), 0.65);">
                <x-ui.tab-strip model="settingsTab" label="AI settings" :items="[['key' => 'defaults', 'label' => __('My defaults')], ['key' => 'brand', 'label' => __('Brand Voice Kit')], ['key' => 'usage', 'label' => __('Usage map')]]" />
            </div>

            <div class="p-4 sm:p-5 lg:p-6">
                <div x-show="settingsTab === 'defaults'" x-cloak>
                    <form method="POST" action="{{ route('portal.ai-studio.settings.user') }}" class="space-y-6">
                        @csrf
                        @method('PUT')

                        <div>
                            <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('My AI defaults') }}</p>
                            <p class="mt-1 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('These values prefill AI Studio modules only for your own account.') }}</p>
                        </div>

                        <div class="grid gap-4 md:grid-cols-3">
                            <div class="rounded-[0.9rem] border px-4 py-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.58); background-color: color-mix(in srgb, var(--theme-surface-base) 96%, var(--theme-body-bg) 4%);">
                                <p class="text-[0.68rem] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Default language') }}</p>
                                <p class="mt-2 text-lg font-semibold" style="color: var(--theme-header-text-color);">{{ $resolveLanguageLabel($userSettings['default_language'] ?? $defaults['default_language']) }}</p>
                            </div>
                            <div class="rounded-[0.9rem] border px-4 py-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.58); background-color: color-mix(in srgb, var(--theme-surface-base) 96%, var(--theme-body-bg) 4%);">
                                <p class="text-[0.68rem] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Default tone') }}</p>
                                <p class="mt-2 text-lg font-semibold" style="color: var(--theme-header-text-color);">{{ $defaultToneLabel }}</p>
                            </div>
                            <div class="rounded-[0.9rem] border px-4 py-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.58); background-color: color-mix(in srgb, var(--theme-surface-base) 96%, var(--theme-body-bg) 4%);">
                                <p class="text-[0.68rem] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Caption defaults') }}</p>
                                <p class="mt-2 text-lg font-semibold" style="color: var(--theme-header-text-color);">{{ count($userSettings['default_caption_platforms'] ?? []) ?: 0 }} {{ __('selected') }}</p>
                            </div>
                        </div>

                        <div class="grid gap-6 xl:grid-cols-2">
                            <div class="space-y-5">
                                <div>
                                    <p class="text-[0.72rem] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('General configuration') }}</p>
                                    <p class="mt-1 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Language, tone, review language, and planning horizon.') }}</p>
                                </div>
                                <div class="grid gap-4 md:grid-cols-2">
                                    <x-ai.language-field name="default_language" :value="$userSettings['default_language']" />
                                    <x-ai.tone-field name="default_tone" :value="$userSettings['default_tone'] ?? $defaults['default_tone']" :options="$toneOptions" />
                                    <x-ai.language-field name="review_language" :label="__('Review Language')" :value="$userSettings['review_language']" />
                                    <x-ai.planner-days-field :value="$userSettings['planner_days']" />
                                </div>
                            </div>

                            <div class="space-y-5">
                                <div>
                                    <p class="text-[0.72rem] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Media defaults') }}</p>
                                    <p class="mt-1 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Default image and video settings for new generation jobs.') }}</p>
                                </div>
                                <div class="grid gap-4 md:grid-cols-2">
                                    <x-ai.image-style-field :value="$userSettings['image_style'] ?? $defaults['image_style']" :options="$imageStyleOptions" />
                                    <x-ai.image-ratio-field :value="$userSettings['image_ratio'] ?? $defaults['image_ratio']" :options="$imageRatioOptions" />
                                    <x-ai.video-format-field :value="$userSettings['video_format'] ?? $defaults['video_format']" :options="$videoFormatOptions" />
                                    <x-ai.video-duration-field :value="$userSettings['video_duration'] ?? $defaults['video_duration']" :options="$videoDurationOptions" />
                                </div>
                            </div>
                        </div>

                        <div class="border-t pt-5" style="border-color: rgba(var(--theme-border-color-rgb), 0.55);">
                            <x-ai.caption-platforms-field :options="$platformOptions" :selected="$userSettings['default_caption_platforms'] ?? []" />
                        </div>

                        <div class="flex flex-wrap items-center gap-3 border-t pt-5" style="border-color: rgba(var(--theme-border-color-rgb), 0.55);">
                            <x-ui.button type="submit"><i class="fa-light fa-floppy-disk"></i>{{ __('Save my defaults') }}</x-ui.button>
                            <x-ui.button :href="route('portal.ai-studio.prompt-history')" variant="outline" wire:navigate><i class="fa-light fa-clock-rotate-left"></i>{{ __('Prompt History') }}</x-ui.button>
                        </div>
                    </form>
                </div>

                <div x-show="settingsTab === 'brand'" x-cloak>
                    @if ($canManageWorkspace)
                        <form method="POST" action="{{ route('portal.ai-studio.settings.workspace') }}" class="space-y-5">
                            @csrf
                            @method('PUT')

                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Brand Voice Kit') }}</p>
                                    <p class="mt-1 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Keep AI output aligned with the brand voice across all AI Studio tools.') }}</p>
                                </div>
                                @if ($team)
                                    <x-ui.badge variant="neutral">{{ __('Team: :name', ['name' => $team->name]) }}</x-ui.badge>
                                @else
                                    <x-ui.badge variant="neutral">{{ __('Personal workspace') }}</x-ui.badge>
                                @endif
                            </div>

                            <div class="grid gap-4 lg:grid-cols-3">
                                <x-ui.input name="brand_name" :label="__('Brand name')" :value="$workspaceSettings['brand_name'] ?? ''" :placeholder="__('Stackposts')" />
                                <x-ui.input name="target_audience" :label="__('Target audience')" :value="$workspaceSettings['target_audience'] ?? ''" :placeholder="__('Agencies, creators, local businesses')" />
                                <x-ai.cta-style-field :value="$workspaceSettings['preferred_cta_style'] ?? ''" :help="null" />
                            </div>

                            <div class="grid gap-4 lg:grid-cols-2">
                                <x-ui.textarea name="brand_description" :label="__('Brand description')" rows="4" placeholder="{{ __('What does this brand sell, who is it for, and why is it different?') }}">{{ $workspaceSettings['brand_description'] ?? '' }}</x-ui.textarea>
                                <x-ai.brand-voice-field :value="$workspaceSettings['brand_voice'] ?? ''" :label="__('Voice principles')" :help="null" rows="4" placeholder="{{ __('Clear, useful, confident, practical, no hype...') }}" />
                            </div>

                            <details class="rounded-[0.9rem] border" style="border-color: rgba(var(--theme-border-color-rgb), 0.55); background-color: color-mix(in srgb, var(--theme-surface-base) 98%, var(--theme-body-bg) 2%);">
                                <summary class="cursor-pointer px-4 py-3 text-sm font-semibold" style="color: var(--theme-header-text-color);">
                                    <i class="fa-light fa-sliders mr-2" style="color: var(--theme-accent);"></i>{{ __('Advanced settings') }}
                                </summary>
                                <div class="space-y-5 border-t p-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.55);">
                                    <div class="grid gap-4 lg:grid-cols-3">
                                        <x-ui.input name="brand_keywords" :label="__('Preferred words / phrases')" :value="$workspaceSettings['brand_keywords'] ?? ''" :placeholder="__('Comma-separated preferred terms')" />
                                        <x-ai.banned-words-field :value="$workspaceSettings['banned_words'] ?? ''" :help="null" rows="3" />
                                        <x-ui.textarea name="writing_examples" :label="__('Writing examples')" rows="3" placeholder="{{ __('Paste 1-3 examples that match the brand voice.') }}">{{ $workspaceSettings['writing_examples'] ?? '' }}</x-ui.textarea>
                                    </div>

                                    <div class="grid gap-5 border-t pt-5 lg:grid-cols-[minmax(0,0.75fr)_minmax(0,1.25fr)]" style="border-color: rgba(var(--theme-border-color-rgb), 0.55);">
                                        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                                            <x-ai.language-field name="default_language" :label="__('Workspace Language')" :value="$workspaceSettings['default_language']" />
                                            <x-ai.tone-field name="default_tone" :label="__('Workspace Tone')" :value="$workspaceSettings['default_tone'] ?? $defaults['default_tone']" :options="$toneOptions" />
                                        </div>
                                        <x-ai.caption-platforms-field
                                            :label="__('Workspace Caption Defaults')"
                                            :description="__('Team baseline when a user has not set personal platform defaults.')"
                                            :options="$platformOptions"
                                            :selected="$workspaceSettings['default_caption_platforms'] ?? []"
                                        />
                                    </div>
                                </div>
                            </details>

                            <div class="flex flex-wrap items-center gap-3 border-t pt-5" style="border-color: rgba(var(--theme-border-color-rgb), 0.55);">
                                <x-ui.button type="submit"><i class="fa-light fa-floppy-disk"></i>{{ __('Save Brand Voice Kit') }}</x-ui.button>
                            </div>
                        </form>
                    @else
                        <x-ui.empty
                            icon="fa-light fa-lock-keyhole"
                            :title="__('Brand Voice Kit is restricted')"
                            :description="__('Only the workspace owner can edit shared AI rules for this team.')"
                        />
                    @endif
                </div>

                <div x-show="settingsTab === 'usage'" x-cloak>
                    <div class="space-y-5">
                        <div>
                            <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('How these defaults apply') }}</p>
                            <p class="mt-1 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('A quick map of which AI Studio modules read each setting group.') }}</p>
                        </div>

                        <div class="grid gap-3 md:grid-cols-2">
                            @foreach ([
                                ['title' => __('AI Content + Repurpose'), 'body' => __('Read default language, tone, platforms, and the Brand Voice Kit.')],
                                ['title' => __('Calendar Planner'), 'body' => __('Reads planner days, default language, and the Brand Voice Kit for campaign direction.')],
                                ['title' => __('AI Review'), 'body' => __('Reads review language and Brand Voice Kit rules to judge copy against your preferred voice.')],
                                ['title' => __('AI Image + AI Video'), 'body' => __('Read media defaults and use Brand Voice Kit for mood, scene, style, pacing, and CTA direction.')],
                            ] as $item)
                                <div class="rounded-[0.9rem] border p-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.58); background-color: color-mix(in srgb, var(--theme-surface-base) 96%, var(--theme-body-bg) 4%);">
                                    <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $item['title'] }}</p>
                                    <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ $item['body'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endcomponent
