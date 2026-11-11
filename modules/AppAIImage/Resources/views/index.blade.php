<div class="aim-chat-shell">
    @include('appaiimage::partials.chat-styles')
    <header class="aim-topbar">
        <div class="aim-product"><span class="aim-product-icon"><i class="fa-light fa-sparkles"></i></span><div><strong>{{ __('AI Image') }}</strong><span>{{ __('Visual studio') }}</span></div></div>
        <a href="{{ route('portal.files.index') }}" wire:navigate class="aim-library-link"><i class="fa-light fa-grid-2"></i>{{ __('Files') }}</a>
    </header>
    <main class="aim-workspace {{ $result ? 'has-result' : '' }}">
        @if ($result)
            <section class="aim-stage">
                <div class="aim-stage-heading"><div><span>{{ __('LATEST CREATION') }}</span><h1>{{ __('Made for your next post.') }}</h1></div><span class="aim-status"><i class="fa-light fa-check"></i>{{ __('Saved to Files') }}</span></div>
                <div class="aim-image-stage" x-data="{ loaded:false, broken:false }">
                    <div class="aim-image-loading" x-show="!loaded || broken"><i class="fa-light fa-image"></i><p x-text="broken ? '{{ __('Preview unavailable') }}' : '{{ __('Preparing your image') }}'"></p></div>
                    @if (!empty($result['preview_url']))
                        <img src="{{ $result['preview_url'] }}" alt="{{ $result['file_name'] ?? __('AI image') }}" x-on:load="loaded=true" x-on:error="broken=true" x-bind:class="{ 'is-ready': loaded && !broken }">
                    @endif
                    <span class="aim-stage-ratio">{{ $result['ratio'] ?? $ratio }}</span>
                </div>
                <div class="aim-stage-footer"><div class="aim-stage-meta"><span>{{ $result['dimensions'] ?: __('Saved asset') }}</span><span>{{ $result['file_size'] ?? '' }}</span><span>{{ strtoupper((string) ($result['provider'] ?? '')) }}</span></div><div class="aim-stage-actions"><x-ui.button :href="$result['download_url'] ?? null" target="_blank" rel="noopener" size="sm"><i class="fa-light fa-download"></i>{{ __('Download') }}</x-ui.button><x-ui.button :href="$result['files_url'] ?? null" variant="outline" size="sm" wire:navigate><i class="fa-light fa-folder-open"></i>{{ __('Open Files') }}</x-ui.button></div></div>
            </section>
        @else
            <section class="aim-welcome"><span class="aim-welcome-icon"><i class="fa-light fa-image-polaroid"></i></span><p>{{ __('AI VISUAL STUDIO') }}</p><h1>{{ __('What do you want to') }} <em>{{ __('create?') }}</em></h1><span>{{ __('Describe the scene. We will turn it into a visual for your next post.') }}</span></section>
        @endif

        <section class="aim-composer" x-data="{ options:false }">
            <div class="aim-composer-label"><span><i class="fa-light fa-wand-magic-sparkles"></i>{{ __('Create an image') }}</span><span>{{ __('Saved automatically') }}</span></div>
            <x-ui.textarea wire:model.defer="prompt" :label="__('Image prompt')" rows="4" :placeholder="__('Describe a subject, setting, composition and light. Example: a premium coffee cup on marble, soft morning light, no text.')" />
            @error('prompt')<p class="aim-error">{{ $message }}</p>@enderror
            <button type="button" class="aim-options-toggle" x-on:click="options=!options" x-bind:aria-expanded="options"><span><i class="fa-light fa-sliders-simple"></i>{{ __('Creative settings') }}</span><span>{{ ucfirst($style) }} · {{ $ratio }} <i class="fa-light fa-chevron-down"></i></span></button>
            <div class="aim-options" x-show="options" x-cloak><x-ui.select wire:model.live="style" :label="__('Style')">@foreach ($styleOptions as $option)<option value="{{ $option['value'] }}">{{ $option['label'] }}</option>@endforeach</x-ui.select><x-ui.select wire:model.live="ratio" :label="__('Aspect ratio')">@foreach ($ratioOptions as $option)<option value="{{ $option['value'] }}">{{ $option['label'] }}</option>@endforeach</x-ui.select></div>
            <footer><div class="aim-credit"><i class="fa-light fa-coins"></i>{{ __(':credits credits', ['credits' => $creditPreview['amount'] ?? 0]) }} · {{ ($creditPreview['unlimited'] ?? false) ? __('Unlimited plan') : __(':credits left', ['credits' => $creditPreview['remaining'] ?? 0]) }}</div><x-ui.button type="button" wire:click="generate" wire:loading.attr="disabled" wire:target="generate" :disabled="!($creditPreview['enough'] ?? true)"><i class="fa-light fa-arrow-up"></i><span wire:loading.remove wire:target="generate">{{ __('Generate image') }}</span><span wire:loading wire:target="generate">{{ __('Generating...') }}</span></x-ui.button></footer>
            @if (!($creditPreview['enough'] ?? true))<p class="aim-error">{{ __('Not enough credits remaining for this action.') }}</p>@endif
        </section>

        @if (!$result)<div class="aim-starters"><p>{{ __('Try an idea') }}</p><div>@foreach ([['Product scene','A polished product photo of [product], [setting], refined commercial lighting, no text or watermark.'],['Social story','An authentic lifestyle scene around [topic], natural light, candid composition, vertical social format.'],['Editorial mood','An editorial image about [topic], expressive composition, soft grain, sophisticated color palette.']] as [$title,$brief])<button type="button" data-brief="{{ __($brief) }}" x-data x-on:click="$wire.set('prompt', $el.dataset.brief, false); $nextTick(()=>document.querySelector('.aim-composer textarea').focus())"><i class="fa-light fa-arrow-up-right"></i><strong>{{ __($title) }}</strong><span>{{ __('Start with a brief') }}</span></button>@endforeach</div></div>@endif
        @if ($recentJobs->isNotEmpty())<section class="aim-recent"><div><span>{{ __('RECENT VISUALS') }}</span><h2>{{ __('Your image shelf') }}</h2></div><div class="aim-recent-grid">@foreach ($recentJobs as $job)<button type="button" wire:click="loadJob({{ $job->id }})" class="{{ $jobId === $job->id ? 'is-active' : '' }}"><i class="fa-light fa-image"></i><strong>{{ ucfirst($job->style) }} · {{ $job->ratio }}</strong><span>{{ \Illuminate\Support\Str::limit($job->prompt, 62) }}</span></button>@endforeach</div></section>@endif
    </main>
</div>
