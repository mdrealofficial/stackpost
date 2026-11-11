<div class="aic-studio" x-data="{}">
    @include('appaicontent::partials.studio-styles')
    <header class="aic-topbar"><div class="aic-brand"><span class="aic-brand-icon"><i class="fa-light fa-wand-magic-sparkles"></i></span><div><strong>{{ __('AI Content') }}</strong><span>{{ __('Your social writing studio') }}</span></div></div><div class="aic-top-actions"><x-ui.button variant="outline" size="sm" x-on:click="$dispatch('open-ai-templates')"><i class="fa-light fa-layer-group"></i> {{ __('Templates') }}</x-ui.button><x-ui.button variant="outline" size="sm" :href="route('portal.ai-studio.prompt-history')"><i class="fa-light fa-clock-rotate-left"></i> {{ __('History') }}</x-ui.button></div></header>
    <section class="aic-workspace {{ $result ? 'aic-has-results' : '' }}">
        <div class="aic-intro">
            <span class="aic-eyebrow"><span></span> {{ __('A little inspiration. A lot of possibility.') }}</span>
            <h1>{{ $result ? __('Good ideas, ready to take shape.') : __('Turn your next idea') }} @if (!$result)<em>{{ __('into a great post.') }}</em>@endif</h1>
            <p>{{ __('Bring the idea. Shape the voice. Create social content that sounds like you.') }}</p>
        </div>
        @include('appaicontent::partials.builder-panel')
        @if (!$result)
        <div class="aic-starters"><p>{{ __('Need a starting point?') }}</p><div class="aic-starter-grid">
        @foreach ([
            ['fa-light fa-megaphone','Launch a product','Introduce a product to your audience','Write a social caption introducing [product] to [audience]. Highlight [main benefit] and finish with a clear call to action.'],
            ['fa-light fa-pen-line','Refresh your copy','Give an existing caption a new angle','Rewrite this caption to make it clearer and more engaging while keeping its meaning: [paste your caption].'],
            ['fa-light fa-bolt','Find a stronger hook','Make the first line count','Write attention-grabbing opening hooks for a social post about [topic], aimed at [audience].'],
            ['fa-light fa-comments','Start a conversation','Invite your audience to join in','Write a social post about [topic] that shares a useful insight and ends with a thoughtful question for [audience].'],
        ] as [$icon,$title,$description,$brief])
            <button type="button" class="aic-starter" data-brief="{{ __($brief) }}" x-on:click="$wire.set('selectedTemplateId', null, false); $wire.set('promptTemplate', $el.dataset.brief, false); $nextTick(() => document.querySelector('.aic-prompt textarea').focus())"><i class="{{ $icon }}"></i><strong>{{ __($title) }}</strong><span>{{ __($description) }}</span><i class="fa-light fa-arrow-up-right aic-starter-arrow"></i></button>
        @endforeach
        </div></div>
        @endif
        <div class="aic-results" aria-live="polite">@if ($result) @include('appaicontent::partials.results-panel') @endif</div>
        <p class="aic-footnote"><i class="fa-light fa-book-bookmark"></i> {{ __('Keep the best variations in Captions. Review your content before publishing.') }}</p>
    </section>
    <x-ui.modal :title="__('Prompt library')" width="xl" body-class="aic-library-modal-body" open-event="open-ai-templates" close-event="close-ai-templates">
        @include('appaicontent::partials.template-sidebar')
    </x-ui.modal>
</div>
