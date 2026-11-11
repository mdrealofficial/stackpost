@props(['teams', 'currentTeam' => null])

<div class="sp-team-switcher">
    <style>
        .sp-header-workspace{padding-right:18px;flex-shrink:0}
        .sp-team-switcher .sp-team-trigger{display:flex;align-items:center;justify-content:space-between;gap:18px;min-width:190px;max-width:250px;height:46px;padding:6px 12px;text-align:left}
        .sp-team-trigger-copy{min-width:0;display:block}
        .sp-team-trigger-copy small{display:block;font-size:10px;line-height:14px;font-weight:500;color:var(--theme-muted-text-color)}
        .sp-team-trigger-copy strong{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:13px;line-height:20px;font-weight:600}
        .sp-team-trigger>i{font-size:10px;flex-shrink:0;transition:transform .15s}
        .sp-team-trigger[aria-expanded=true]>i{transform:rotate(180deg)}
        .sp-team-menu{width:280px;max-width:calc(100vw - 40px)}
        .sp-team-menu-title{padding:10px 10px 8px;font-size:12px;font-weight:600;color:var(--theme-muted-text-color)}
        .sp-team-list{max-height:300px;overflow-y:auto;overscroll-behavior:contain}
        .sp-team-option{display:flex;align-items:center;gap:12px;padding:10px;border-radius:9px;color:var(--theme-header-text-color);font-size:13px}
        .sp-team-option:hover,.sp-team-option[aria-current=true]{background:var(--theme-surface-soft)}
        .sp-team-option[aria-current=true]{color:var(--theme-accent)}
        .sp-team-option>span{flex:1;min-width:0}.sp-team-option strong{display:block;overflow-wrap:anywhere;font-weight:600}.sp-team-option small{display:block;margin-top:3px;font-size:11px;color:var(--theme-muted-text-color)}
        .sp-team-option:focus-visible{outline:2px solid var(--theme-accent);outline-offset:-2px}
        .sp-team-menu-footer{margin-top:6px;padding:6px 0 0;border-top:1px solid var(--theme-border-color)}
        @media(max-width:767px){
            .app-default-header:has(.sp-team-switcher)>div{flex-wrap:wrap;padding-top:8px;padding-bottom:8px;row-gap:8px}
            .app-default-header:has(.sp-team-switcher) .sp-header-start{display:contents}
            .sp-header-workspace{order:3;flex-basis:100%;padding-right:0}
            .sp-team-switcher>div{width:100%}.sp-team-switcher .sp-team-trigger{max-width:none;width:100%;height:42px}
            .sp-team-menu{width:calc(100vw - 32px);max-width:calc(100vw - 32px)}
        }
        @media(min-width:768px) and (max-width:1023px){.sp-header-workspace{padding-left:10px;padding-right:6px}.sp-team-switcher .sp-team-trigger{min-width:160px;max-width:210px}}
        @media(prefers-reduced-motion:reduce){.sp-team-trigger>i{transition:none}}
    </style>
    <x-ui.dropdown-menu align="left" width="auto">
        <x-slot:trigger>
            <x-ui.button variant="ghost" class="sp-team-trigger" aria-haspopup="true" x-bind:aria-expanded="open.toString()" :aria-label="__('Switch team: :name', ['name' => $currentTeam?->name ?: __('My workspace')])">
                <span class="sp-team-trigger-copy"><small>{{ __('Workspace team') }}</small><strong>{{ $currentTeam?->name ?: __('My workspace') }}</strong></span>
                <i class="fa-light fa-chevron-down" aria-hidden="true"></i>
            </x-ui.button>
        </x-slot:trigger>
        <div class="sp-team-menu">
            <p class="sp-team-menu-title">{{ __('Switch workspace') }}</p>
            <div class="sp-team-list">
                @forelse($teams as $team)
                    <a class="sp-team-option" href="{{ route('portal.teams.switch', ['team' => $team->id, 'redirect' => route('portal.dashboard')]) }}" aria-current="{{ (int) $team->id === (int) $currentTeam?->id ? 'true' : 'false' }}" x-on:click="open = false">
                        <span><strong>{{ $team->name }}</strong><small>{{ (int) $team->owner_user_id === (int) auth()->id() ? __('Owner') : __('Member') }}</small></span>
                        @if((int) $team->id === (int) $currentTeam?->id)<i class="fa-light fa-check" aria-hidden="true"></i><span class="sr-only">{{ __('Current team') }}</span>@endif
                    </a>
                @empty
                    <p class="sp-team-menu-title">{{ __('No teams available yet.') }}</p>
                @endforelse
            </div>
            @if(Route::has('portal.teams') && auth()->user()?->canUsePlanFeature('teams'))
                <div class="sp-team-menu-footer"><x-ui.dropdown-menu-item :href="route('portal.teams', $currentTeam ? ['team' => $currentTeam->id] : [])" wire:navigate>{{ __('Manage teams') }}</x-ui.dropdown-menu-item></div>
            @endif
        </div>
    </x-ui.dropdown-menu>
</div>
