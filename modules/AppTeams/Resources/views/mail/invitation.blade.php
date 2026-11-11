<!doctype html><html><body style="font-family:Arial,sans-serif;color:#20352d;line-height:1.7">
<h1>{{ __('Join :team', ['team'=>$invitation->team->name]) }}</h1>
<p>{{ __('You have been invited to collaborate in this workspace. Sign in using the email address that received this invitation.') }}</p>
@if($invitation->message)<p>{{ $invitation->message }}</p>@endif
<p><a href="{{ route('portal.teams.join',['inviteCode'=>$invitation->invite_code]) }}">{{ __('Review invitation') }}</a></p>
<p>{{ __('Expires') }}: {{ $invitation->expires_at?->format('Y-m-d H:i T') }}</p>
<p>{{ __('Opening this link does not join the team. You will be asked to confirm.') }}</p>
</body></html>
