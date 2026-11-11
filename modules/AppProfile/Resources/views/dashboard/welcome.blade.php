@php
    $creditRemainingLabel = $credits['unlimited'] ? __('Unlimited') : number_format((int)($credits['remaining'] ?? 0));
    $usagePercent = max(0, min(100, (int)($credits['usage_percent'] ?? 0)));
@endphp
<div class="sp-welcome dash-hero">
    <div class="sp-welcome-main">
        <span class="sp-dashboard-kicker">{{ $todayLabel }}</span>
        <h1>{{ __('Make your next post matter, :name', ['name' => $user?->name ?: $user?->username ?: __('there')]) }}</h1>
        <p>{{ __('A clear view of your content. A focused start to your day.') }}</p>
        <div class="sp-welcome-plan">
            <span>@if($user?->hasActivePlan())<i class="sp-status-dot" aria-hidden="true"></i>@endif{{ $planName }}</span>
            <span>{{ $planExpiryLabel }}</span>
            @unless($user?->hasActivePlan())<span>{{ __('Plan inactive') }}</span>@endunless
        </div>
        <div class="sp-dashboard-actions">
            @if(Route::has('portal.publishing.calendar') && \Modules\AppTeams\Support\TeamWorkspaceAccess::teamHasModule(\Modules\AppTeams\Support\TeamWorkspaceAccess::activeTeam(auth()->user()), 'publishing'))
                <x-ui.button :href="route('portal.publishing.calendar', ['compose' => 1])" wire:navigate><i class="fa-light fa-plus" aria-hidden="true"></i>{{ __('Create post') }}</x-ui.button>
            @endif
            @if(Route::has('portal.packages'))<x-ui.button :href="route('portal.packages')" variant="outline" wire:navigate>{{ __('Manage plan') }}</x-ui.button>@endif
        </div>
    </div>
    <section class="sp-credit-card" aria-label="{{ __('Your credits') }}">
        <div class="sp-credit-top"><span>{{ __('Available credits') }}</span><i class="fa-light fa-gem" aria-hidden="true"></i></div>
        <p class="sp-credit-balance">{{ $creditRemainingLabel }}</p>
        <div class="sp-credit-detail"><span>{{ __('Plan allowance used') }}</span><strong>{{ $credits['unlimited'] ? __('Unlimited') : $usagePercent.'%' }}</strong></div>
        @unless($credits['unlimited'])
            <div class="sp-credit-progress"><div role="progressbar" aria-label="{{ __('Plan allowance used') }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $usagePercent }}" style="width:{{ $usagePercent }}%"></div></div>
        @endunless
        <div class="sp-credit-detail"><span>{{ __('Balance includes top-ups') }}</span><span>{{ __(':used used', ['used' => number_format((int)$credits['used'])]) }}</span></div>
    </section>
</div>
