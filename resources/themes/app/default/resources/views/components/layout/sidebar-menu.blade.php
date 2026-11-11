@props(['sections' => [], 'mode' => 'desktop'])
@php
    $icons = [
        'dashboard' => 'fa-light fa-house',
        'blogs' => 'fa-light fa-rss',
        'faq' => 'fa-light fa-messages-question',
        'support' => 'fa-light fa-life-ring',
        'mail' => 'fa-light fa-envelopes-bulk',
        'notification' => 'fa-light fa-bell',
        'proxy' => 'fa-light fa-hard-drive',
        'ai-report' => 'fa-light fa-chart-mixed',
        'ai-template' => 'fa-light fa-brain-circuit',
        'plans' => 'fa-light fa-box-open',
        'money' => 'fa-light fa-coins',
        'coupon' => 'fa-light fa-ticket-percent',
        'affiliate' => 'fa-light fa-handshake-angle',
        'users' => 'fa-light fa-users',
        'user-report' => 'fa-light fa-chart-user',
        'themes' => 'fa-light fa-swatchbook',
        'settings' => 'fa-light fa-sliders',
    ];
@endphp
<nav class="sp-navigation" aria-label="{{ __('Main navigation') }}" x-bind:class="{ 'sp-nav-compact': !sidebarContentVisible }">
    @foreach ($sections as $section)
        <section class="sp-nav-group">
            @if (!empty($section['label']))
                <p class="sp-nav-heading" x-show="sidebarContentVisible">{{ __($section['label']) }}</p>
                <div class="sp-nav-divider" x-show="!sidebarContentVisible" aria-hidden="true"></div>
            @endif
            @foreach ($section['items'] ?? [] as $item)
                @php
                    $hasChildren = !empty($item['children']);
                    $isActive = (bool)($item['active'] ?? false);
                    $isCurrent = $isActive || collect($item['children'] ?? [])->contains(fn($child) => $child['active'] ?? false);
                    $isDisabled = (bool)($item['disabled'] ?? false);
                    $iconKey = (string)($item['icon'] ?? 'dashboard');
                    $icon = str_starts_with($iconKey, 'fa-') ? $iconKey : ($icons[$iconKey] ?? $icons['dashboard']);
                    $badge = $item['badge'] ?? null;
                @endphp
                @if ($hasChildren)
                    <div x-data="{ expanded: {{ $isCurrent ? 'true' : 'false' }} }">
                        <button type="button" class="sp-nav-item" data-active="{{ $isCurrent ? 'true' : 'false' }}" @disabled($isDisabled) title="{{ __($item['label']) }}" aria-label="{{ __($item['label']) }}" x-bind:aria-expanded="expanded && sidebarContentVisible" x-on:click="if (!sidebarContentVisible) { toggleSidebar(); expanded = true; } else { expanded = !expanded; }">
                            <i class="{{ $icon }} sp-nav-icon" aria-hidden="true"></i>
                            <span class="sp-nav-label" x-show="sidebarContentVisible">{{ __($item['label']) }}</span>
                            @if($badge)<span class="sp-nav-badge" x-show="sidebarContentVisible" title="{{ __('This is a separate addon module and is not included in the main script.') }}">{{ __($badge) }}</span>@endif
                            <i class="fa-light fa-chevron-down sp-nav-chevron" x-show="sidebarContentVisible" x-bind:class="{ 'sp-rotated': expanded }" aria-hidden="true"></i>
                        </button>
                        <div class="sp-nav-children" x-cloak x-show="expanded && sidebarContentVisible">
                            @foreach ($item['children'] as $child)
                                @php($disabled = (bool)($child['disabled'] ?? false))
                                <a class="sp-nav-child" href="{{ $disabled ? '#' : ($child['route'] ?? '#') }}" aria-disabled="{{ $disabled ? 'true' : 'false' }}" @if($child['active'] ?? false) aria-current="page" @endif @if(!$disabled && !empty($child['route'])) wire:navigate @endif @if($disabled) x-on:click.prevent @endif><span>{{ __($child['label']) }}</span>@if(!empty($child['badge']))<span class="sp-nav-badge" title="{{ __('This is a separate addon module and is not included in the main script.') }}">{{ __($child['badge']) }}</span>@endif</a>
                            @endforeach
                        </div>
                    </div>
                @else
                    <a class="sp-nav-item" data-active="{{ $isCurrent ? 'true' : 'false' }}" href="{{ $isDisabled ? '#' : ($item['route'] ?? '#') }}" title="{{ __($item['label']) }}" aria-label="{{ __($item['label']) }}" aria-disabled="{{ $isDisabled ? 'true' : 'false' }}" @if($isActive) aria-current="page" @endif @if(!$isDisabled && !empty($item['route'])) wire:navigate @endif @if($isDisabled) x-on:click.prevent @endif>
                        <i class="{{ $icon }} sp-nav-icon" aria-hidden="true"></i>
                        <span class="sp-nav-label" x-show="sidebarContentVisible">{{ __($item['label']) }}</span>
                        @if($badge)<span class="sp-nav-badge" x-show="sidebarContentVisible" title="{{ __('This is a separate addon module and is not included in the main script.') }}">{{ __($badge) }}</span>@endif
                        @if(!empty($item['suffix']))<span class="sp-nav-suffix" x-show="sidebarContentVisible">{{ $item['suffix'] }}</span>@endif
                    </a>
                @endif
            @endforeach
        </section>
    @endforeach
</nav>
