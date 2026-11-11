<section class="eng-welcome">
    <div class="eng-welcome-copy">
        <span class="eng-kicker"><span></span>{{ __('MAKE YOUR FIRST CONNECTION') }}</span>
        <h2>{{ $module['interaction'] === 'message' ? __('A better reply.') : __('Every comment.') }}<br><em>{{ $module['interaction'] === 'message' ? __('A stronger connection.') : __('A new connection.') }}</em></h2>
        <p>{{ $module['interaction'] === 'message' ? __('Give everyday questions a thoughtful answer. Build your first rule for Messenger and Instagram Direct, then let your team take it from there.') : __('Keep up with the conversation on your posts. Match the right comments, prepare a helpful response and stay in control of what gets sent.') }}</p>
        @if($canManage)<button class="eng-btn eng-primary eng-start-button" wire:click="newRule"><i class="fa-light fa-plus"></i>{{ __('Build your first rule') }}<i class="fa-light fa-arrow-right"></i></button>@endif
        <div class="eng-welcome-foot"><i class="fa-light fa-shield-check"></i>{{ __('Start paused. Review first. Activate when ready.') }}</div>
    </div>
    <div class="eng-flow" aria-label="{{ __('How an automation works') }}">
        <div class="eng-flow-caption"><span>{{ __('THE RESPONSE FLOW') }}</span><span><i class="fa-brands fa-facebook"></i><i class="fa-brands fa-instagram"></i></span></div>
        <div class="eng-flow-card"><span class="eng-flow-icon"><i class="fa-light {{ $module['interaction'] === 'message' ? 'fa-message-dots' : 'fa-comment-dots' }}"></i></span><div><small>{{ __('01 · LISTEN') }}</small><strong>{{ $module['interaction'] === 'message' ? __('A customer sends a message') : __('A customer leaves a comment') }}</strong><span>{{ $module['interaction'] === 'message' ? __('Messenger or Instagram Direct') : __('On your Facebook or Instagram post') }}</span></div></div>
        <div class="eng-flow-connector"><i class="fa-light fa-arrow-down"></i></div>
        <div class="eng-flow-card eng-flow-match"><span class="eng-flow-icon"><i class="fa-light fa-bolt"></i></span><div><small>{{ __('02 · MATCH') }}</small><strong>{{ __('Your rules set the direction') }}</strong><span>{{ __('Keywords, channels & operating hours') }}</span></div><i class="fa-light fa-sliders"></i></div>
        <div class="eng-flow-connector"><i class="fa-light fa-arrow-down"></i></div>
        <div class="eng-flow-card"><span class="eng-flow-icon"><i class="fa-light fa-paper-plane"></i></span><div><small>{{ __('03 · RESPOND') }}</small><strong>{{ __('A reply, with your team in control') }}</strong><span>{{ __('Review a draft or send automatically') }}</span></div></div>
    </div>
</section>
@if($canManage)<section class="eng-quick-start"><div><span class="eng-eyebrow">{{ __('A HEAD START') }}</span><h3>{{ __('Start with a familiar conversation') }}</h3></div><div class="eng-starters"><button wire:click="newRule('question')"><i class="fa-light fa-circle-question"></i><div><strong>{{ __('Answer common questions') }}</strong><span>{{ __('Pricing, products and everyday FAQs') }}</span></div><i class="fa-light fa-arrow-up-right"></i></button><button wire:click="newRule('welcome')"><i class="fa-light fa-hand-wave"></i><div><strong>{{ __('Start a conversation') }}</strong><span>{{ __('A warm welcome for new interactions') }}</span></div><i class="fa-light fa-arrow-up-right"></i></button></div></section>@endif
