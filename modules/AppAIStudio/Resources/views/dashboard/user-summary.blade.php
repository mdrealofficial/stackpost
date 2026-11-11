@php
    $availableTools = collect($tools ?? [])
        ->filter(fn (array $tool): bool => filled($tool['route'] ?? null))
        ->values();
    $creationTools = $availableTools->take(4);
    $assistTools = $availableTools->slice(4)->values();
    $availableSupportTools = collect($supportTools ?? [])
        ->filter(fn (array $tool): bool => filled($tool['route'] ?? null))
        ->values();
@endphp

<section class="dash-panel dash-ai-panel"><div class="dash-section-heading"><div><span class="dash-kicker">{{ __('Creative workspace') }}</span><h2>{{ __('A little spark. A new idea.') }}</h2></div><span class="dash-ai-mark"><i class="fa-light fa-sparkles"></i></span></div><p class="dash-muted">{{ __('Start with an idea. Turn it into content worth sharing.') }}</p>
<div class="dash-ai-tools">@forelse($creationTools as $tool)<a href="{{ $tool['route'] }}" wire:navigate><span><i class="{{ $tool['icon'] }}"></i></span><strong>{{ $tool['label'] }}</strong><i class="fa-light fa-arrow-up-right"></i></a>@empty<p>{{ __('No AI tools available for this workspace.') }}</p>@endforelse</div>
@if($assistTools->isNotEmpty())<details class="dash-ai-more"><summary>{{ __('Explore :count more tools', ['count'=>$assistTools->count()]) }}</summary><div class="dash-shortcuts">@foreach($assistTools as $tool)<a href="{{ $tool['route'] }}" wire:navigate>{{ $tool['label'] }}</a>@endforeach</div></details>@endif
<div class="dash-foot">@foreach($availableSupportTools as $tool)<a class="dash-link" href="{{ $tool['route'] }}" wire:navigate>{{ $tool['label'] }}</a>@endforeach</div></section>
