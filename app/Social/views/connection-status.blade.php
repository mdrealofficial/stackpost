@php($needsSetup = count($readiness['issues']) > 0 || $readiness['attention']->isNotEmpty())
<section class="eng-connection-status" aria-label="{{ __('Connection status') }}">
    <div class="eng-connection-summary">
        <span class="eng-connection-symbol"><i class="fa-light {{ $needsSetup ? 'fa-plug-circle-exclamation' : 'fa-plug' }}"></i></span>
        <div><strong>{{ $readiness['channels']->isEmpty() ? __('Connect a channel to get started') : ($needsSetup ? __('Automation needs setup') : __('Connection settings saved')) }}</strong>
            <p>{{ $readiness['channels']->isEmpty() ? __('Add your Facebook or Instagram account in Channels.') : ($needsSetup ? __('You can prepare rules while these connection checks are completed.') : __('Confirm delivery with a real incoming interaction before relying on automation.')) }}</p>
        </div>
        @if($readiness['channels']->isEmpty())<a class="eng-link" href="{{ route('portal.channels') }}" wire:navigate>{{ __('Add channels') }} <i class="fa-light fa-arrow-up-right"></i></a>
        @elseif($needsSetup)<button class="eng-link" type="button" wire:click="$toggle('showConnectionDetails')" aria-expanded="{{ $showConnectionDetails ? 'true' : 'false' }}" aria-controls="eng-connection-details">{{ $showConnectionDetails ? __('Hide details') : __('View details') }}<i class="fa-light {{ $showConnectionDetails ? 'fa-chevron-up' : 'fa-chevron-down' }}"></i></button>@endif
    </div>
    @if($showConnectionDetails && $needsSetup)
        <div class="eng-connection-details" id="eng-connection-details">
            @if(count($readiness['issues']))<div class="eng-shared-issues"><h3>{{ __('Workspace setup') }}</h3>
                @foreach($readiness['issues'] as $issue)<div class="eng-shared-issue"><i class="fa-light fa-circle-info"></i><div><strong>{{ $issue['label'] }}</strong><p>{{ $issue['detail'] }}</p></div></div>@endforeach
            </div>@endif
            @if($readiness['attention']->isNotEmpty())<div class="eng-channel-attention"><h3>{{ __('Channels that need attention') }} <span>{{ $readiness['attention']->count() }}</span></h3>
                @foreach($readiness['attention'] as $channel)<div class="eng-channel-status-row" wire:key="connection-{{ $channel['id'] }}">
                    @include('social-automation::channel-avatar',['channel'=>$channel])
                    <div class="eng-channel-identity"><strong>{{ $channel['name'] }}</strong><span>{{ $channel['username'] ? '@'.ltrim($channel['username'],'@') : ucfirst($channel['provider']) }}</span></div>
                    <span class="eng-channel-issue">{{ $channel['issue'] }}</span><a class="eng-link" href="{{ $channel['url'] }}" wire:navigate>{{ $channel['action'] }}<i class="fa-light fa-arrow-up-right"></i></a>
                </div>@endforeach
            </div>@endif
        </div>
    @endif
</section>
