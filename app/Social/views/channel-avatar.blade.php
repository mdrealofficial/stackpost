@php
    $avatarName = (string)($channel['name'] ?? __('Channel'));
    $avatarSource = (string)($channel['avatar'] ?? '');
    $avatarAllowed = preg_match('~^(https?://|/)~i',$avatarSource) === 1;
@endphp
<span class="eng-channel-avatar" aria-hidden="true">
    <span>{{ mb_strtoupper(mb_substr(trim($avatarName),0,1)) ?: '?' }}</span>
    @if($avatarAllowed)<img src="{{ $avatarSource }}" alt="" loading="lazy" referrerpolicy="no-referrer" x-data x-on:error="$el.hidden = true">@endif
    <i class="fa-brands {{ $channel['provider'] === 'facebook' ? 'fa-facebook-f is-facebook' : 'fa-instagram is-instagram' }}"></i>
</span>
