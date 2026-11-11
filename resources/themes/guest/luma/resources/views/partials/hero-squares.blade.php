@php
    $heroTiles = collect($heroTiles ?? [])->take(6)->values();
    $heroCoreIcon = $heroCoreIcon ?? 'fa-layer-group';
    $heroCoreLabel = $heroCoreLabel ?? __('One workspace');
    $heroCoreTitle = $heroCoreTitle ?? __('Everything connected.');
    $heroCoreStatus = $heroCoreStatus ?? __('Ready for your workflow');
@endphp

<div class="lm-hero-square-cloud lm-page-reveal lm-page-reveal-right" aria-hidden="true">
    @foreach ($heroTiles as $tile)
        <div class="lm-hero-square is-slot-{{ $loop->iteration }}">
            <i class="fa-light {{ $tile['icon'] }}"></i>
            <div><span>{{ $tile['label'] }}</span><strong>{{ $tile['title'] }}</strong></div>
        </div>
    @endforeach
    <div class="lm-hero-square is-ghost-a"></div>
    <div class="lm-hero-square is-ghost-b"></div>
    <div class="lm-hero-square-core">
        <span class="lm-hero-square-core-icon"><i class="fa-light {{ $heroCoreIcon }}"></i></span>
        <div><small>{{ $heroCoreLabel }}</small><strong>{{ $heroCoreTitle }}</strong></div>
        <span class="lm-hero-square-core-status">{{ $heroCoreStatus }}</span>
    </div>
</div>
