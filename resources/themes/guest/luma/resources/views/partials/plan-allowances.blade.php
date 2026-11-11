@php
    $allowanceItems = collect($plan['features'] ?? [])->flatMap(function ($feature) {
        $children = collect($feature['subfeature'] ?? [])->flatMap(fn ($group) => $group['items'] ?? []);
        return collect([$feature])->concat($children);
    })->keyBy('key');
    $allowanceLabels = [
        'max_channels' => __('Connected profiles'),
        'max_posts_per_month' => __('Posts / month'),
        'credits_usage_limit' => __('AI credits'),
        'max_team_members' => __('Team members'),
        'max_storage_size_mb' => __('Storage (MB)'),
    ];
@endphp
<dl class="lm-plan-allowances" aria-label="{{ __('Plan allowances') }}">
    @foreach($allowanceLabels as $key => $label)
        @php($allowance = $allowanceItems->get($key))
        @if($allowance && ($allowance['display'] ?? '') !== '')
            <div><dt>{{ $label }}</dt><dd>{{ __((string) $allowance['display']) }}</dd></div>
        @endif
    @endforeach
</dl>
