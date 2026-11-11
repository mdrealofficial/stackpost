<section class="aic-composer" x-data="{ settingsOpen: false, platformsOpen: false }">
    <div class="aic-composer-top"><span><i class="fa-light fa-pen-nib"></i> {{ __('Your creative brief') }}</span><button type="button" class="aic-text-button" x-on:click="$dispatch('open-ai-templates')"><i class="fa-light fa-layer-group"></i> {{ __('Use a template') }}</button></div>
    <div class="aic-prompt">
        <x-ui.textarea wire:model.defer="promptTemplate" :label="__('What are we writing today?')" :error="$errors->first('promptTemplate')" rows="5" maxlength="5000" :placeholder="__('Describe your idea, audience and goal. For example: introduce our new summer collection with a warm, playful caption and a clear call to action.')" />
    </div>
    <div class="aic-platform-summary"><span class="aic-label">{{ __('Write for') }}</span><span class="aic-selected-platforms">@foreach ($platformOptions as $platform) @if (in_array($platform['key'], $selectedPlatforms)) <span>{{ $platform['label'] }}</span> @endif @endforeach</span><button type="button" class="aic-text-button" x-on:click="platformsOpen = !platformsOpen" x-bind:aria-expanded="platformsOpen" aria-controls="aic-platform-options">{{ __('Change platforms') }} <i class="fa-light fa-chevron-down"></i></button></div>
    <div class="aic-platforms" id="aic-platform-options" x-show="platformsOpen" x-cloak>
        @foreach ($platformOptions as $platform)
            <x-ui.checkbox minimal wire:model.live="selectedPlatforms" :value="$platform['key']" :label="$platform['label']" :id="'aic-platform-'.$platform['key']" class="aic-platform" />
        @endforeach
    </div>
    @error('selectedPlatforms') <p class="aic-error">{{ $message }}</p> @enderror
    <button type="button" class="aic-settings-toggle" x-on:click="settingsOpen = !settingsOpen" x-bind:aria-expanded="settingsOpen" aria-controls="aic-settings"><span><i class="fa-light fa-sliders-simple"></i> {{ __('Output settings') }}</span><span>{{ __('Language, tone & more') }} <i class="fa-light fa-chevron-down"></i></span></button>
    <div id="aic-settings" x-show="settingsOpen" x-cloak>
    <div class="aic-settings-grid">
        <x-ai.language-field wire:model.defer="language" name="language" :value="$language" :label="__('Language')" :preferred="['vi', 'en']" class="space-y-1.5" />

        <x-ai.tone-field
            wire:model.defer="tone"
            name="tone"
            :value="$tone"
            :label="__('Tone Of Voice')"
            :options="$toneOptions"
            class="space-y-1.5"
        />

        <x-ui.select wire:model.defer="creativity" :label="__('Creativity')" class="space-y-1.5">
            @foreach ($creativityOptions as $option)
                <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
            @endforeach
        </x-ui.select>

        <x-ui.select wire:model.defer="hashtagMode" :label="__('Add hashtags')" class="space-y-1.5">
            @foreach ($hashtagOptions as $option)
                <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
            @endforeach
        </x-ui.select>

        <x-ui.input
            wire:model.defer="approximateWords"
            type="number"
            min="40"
            max="320"
            step="10"
            :label="__('Approximate words')"
            class="space-y-1.5"
        />

        <x-ui.input
            wire:model.defer="totalResults"
            type="number"
            min="1"
            max="8"
            step="1"
            :label="__('Total results')"
            class="space-y-1.5"
        />
    </div>


        @error('approximateWords') <p class="aic-error">{{ $message }}</p> @enderror
        @error('totalResults') <p class="aic-error">{{ $message }}</p> @enderror
    </div>
    <div class="flex flex-col gap-3 border-t pt-4 sm:flex-row sm:items-center sm:justify-between" style="border-color: rgba(var(--theme-border-color-rgb), 0.5);">
        <x-ui.button type="button" wire:click="generate" wire:loading.attr="disabled" wire:target="generate" :disabled="!($creditPreview['enough'] ?? true)">
            <i class="fa-light fa-wand-magic-sparkles"></i>
            <span wire:loading.remove wire:target="generate">{{ __('Generate content') }}</span>
            <span wire:loading wire:target="generate">{{ __('Generating...') }}</span>
        </x-ui.button>

        <div class="flex flex-wrap items-center gap-2">
            <div class="inline-flex items-center gap-2 rounded-full border px-3 py-2 text-sm" style="border-color: rgba(var(--theme-border-color-rgb), 0.5); background-color: color-mix(in srgb, var(--theme-surface-base) 96%, var(--theme-body-bg) 4%); color: var(--theme-muted-text-color);">
                <i class="fa-light fa-coins text-xs" style="color: var(--theme-accent);"></i>
                <span>{{ __(':credits credits', ['credits' => $creditPreview['amount'] ?? 0]) }}</span>
                <span>&bull;</span>
                <span>{{ ($creditPreview['unlimited'] ?? false) ? __('Unlimited plan') : __(':credits left', ['credits' => $creditPreview['remaining'] ?? 0]) }}</span>
            </div>

            @if ($selectedTemplate)
                <span class="inline-flex items-center gap-2 rounded-full border px-3 py-2 text-sm" style="border-color: rgba(var(--theme-border-color-rgb), 0.5); background-color: color-mix(in srgb, var(--theme-surface-base) 96%, var(--theme-body-bg) 4%); color: var(--theme-muted-text-color);">
                    <span class="font-medium">{{ __('Template:') }}</span>
                    <span style="color: var(--theme-header-text-color);">{{ $selectedTemplate->category?->name ?: __('Template') }}</span>
                </span>
            @endif
        </div>
    </div>

    @if (!($creditPreview['enough'] ?? true))
        <p class="pt-1 text-sm font-medium" style="color: var(--theme-danger-color);">{{ __('Not enough credits remaining for this action.') }}</p>
        @include(theme_view('partials.credit-topup-cta', 'app'))
    @endif

    <div wire:loading.flex wire:target="generate" class="mt-4 items-center gap-3 rounded-[0.9rem] border px-4 py-3 text-sm" style="border-color: rgba(var(--theme-accent-rgb), 0.16); background-color: rgba(var(--theme-accent-rgb), 0.06); color: var(--theme-muted-text-color);">
        <span class="inline-flex h-9 w-9 items-center justify-center rounded-full" style="background-color: rgba(var(--theme-accent-rgb), 0.12); color: var(--theme-accent);">
            <i class="fa-light fa-loader animate-spin"></i>
        </span>
        <div>
            <p class="font-semibold" style="color: var(--theme-header-text-color);">{{ __('Generating content') }}</p>
            <p class="mt-1">{{ __('AI is combining your prompt and settings into multiple content variations.') }}</p>
        </div>
    </div>

    @if ($tags)
        <div class="pt-2 flex flex-wrap gap-2">
            @foreach ($tags as $tag)
                <span class="rounded-full px-2.5 py-1 text-[11px] font-medium" style="background-color: rgba(var(--theme-accent-rgb), 0.1); color: var(--theme-accent);">{{ $tag }}</span>
            @endforeach
        </div>
    @endif
</section>
