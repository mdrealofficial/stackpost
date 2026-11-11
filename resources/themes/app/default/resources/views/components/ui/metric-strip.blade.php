@props([
    'items' => [],
    'columns' => 'md:grid-cols-3',
    'gap' => 'gap-4',
    'cardStyle' => 'border-color: var(--theme-border-color); background-color: color-mix(in srgb, var(--theme-surface-base) 98%, transparent);',
    'progressTrackStyle' => 'background-color: color-mix(in srgb, var(--theme-border-color) 55%, transparent);',
    'showIcons' => true,
    'showProgress' => true,
    'variant' => 'default',
])

@php
    $isPortal = $variant === 'portal';
@endphp

<div {{ $attributes->class("sp-metric-strip grid {$gap} {$columns}" . ($isPortal ? ' sp-metric-strip--portal' : '')) }}>
    @foreach ($items as $item)
        @php
            // `tone` is also used as the icon surface by portal cards that
            // provide a separate `color`. Prefer the explicit color for the
            // foreground so pastel surfaces never wash out the icon.
            $tone = $item['color'] ?? $item['tone'] ?? 'var(--theme-accent)';
            $progress = max(0, min(100, (int) ($item['progress'] ?? 0))); 
            $resolvedCardStyle = trim(($cardStyle ?? '').' '.($item['cardStyle'] ?? ''));
            $resolvedProgressTrackStyle = $item['progressTrackStyle'] ?? $progressTrackStyle;
            $iconSurface = $item['iconSurface'] ?? $item['tone'] ?? 'transparent';
            $iconBorder = $item['iconBorder'] ?? 'var(--theme-border-color)';
            $iconClass = preg_match('/\\bfa-(?:light|regular|solid|brands|thin|duotone|sharp(?:-[a-z]+)?)\\b/', (string) ($item['icon'] ?? ''))
                ? $item['icon']
                : 'fa-light '.($item['icon'] ?? '');
            $description = $item['description'] ?? $item['detail'] ?? null;
            $href = $item['href'] ?? null;
            $wireNavigate = (bool) ($item['wireNavigate'] ?? false);
        @endphp

        @if ($isPortal)
            @if ($href)
                <a href="{{ $href }}" @if ($wireNavigate) wire:navigate @endif class="sp-metric-card block rounded-[1rem] border px-4 py-4" style="border-color: var(--theme-card-border-color, var(--theme-border-color)); background: var(--theme-surface-base);">
            @else
                <div class="sp-metric-card rounded-[1rem] border px-4 py-4" style="border-color: var(--theme-card-border-color, var(--theme-border-color)); background: var(--theme-surface-base);">
            @endif
                <div class="flex items-center gap-4">
                    @if ($showIcons && !empty($item['icon']))
                        <span class="inline-flex h-14 w-14 shrink-0 items-center justify-center rounded-[1rem] text-xl" style="background: {{ $iconSurface }}; color: {{ $tone }};">
                            <i class="{{ $iconClass }}" aria-hidden="true"></i>
                        </span>
                    @endif

                    <div class="min-w-0 flex-1">
                        @if (!empty($item['label']))
                            <p class="truncate text-[11px] font-semibold uppercase tracking-[0.14em]" style="color: var(--theme-muted-text-color);">{{ $item['label'] }}</p>
                        @endif

                        <div class="mt-1 flex items-baseline gap-2">
                            <p class="truncate text-3xl font-semibold leading-none tracking-[-0.04em]" style="color: var(--theme-header-text-color);">{{ $item['value'] ?? '0' }}</p>
                            @if (!empty($item['suffix']))
                                <span class="text-sm font-medium" style="color: var(--theme-muted-text-color);">{{ $item['suffix'] }}</span>
                            @endif
                        </div>

                        @if ($description)
                            <p class="mt-2 truncate text-sm" style="color: var(--theme-muted-text-color);">{{ $description }}</p>
                        @endif
                    </div>

                    @if (!empty($item['spark']))
                        <svg class="hidden h-10 w-16 shrink-0 sm:block" viewBox="0 0 64 40" fill="none" aria-hidden="true" focusable="false">
                            <path d="{{ $item['spark'] }}" stroke="{{ $tone }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    @endif

                    @if ($href)
                        <i class="fa-light fa-arrow-up-right shrink-0 text-sm" style="color: {{ $tone }};" aria-hidden="true"></i>
                    @endif
                </div>
            @if ($href)
                </a>
            @else
                </div>
            @endif
        @else
        <div class="rounded-xl border p-4" style="{{ $resolvedCardStyle }}">
            <div class="flex items-center justify-between gap-4">
                <div>
                    @if (!empty($item['label']))
                        <p class="text-[11px] font-medium" style="color: {{ $tone }};">{{ $item['label'] }}</p>
                    @endif

                    <div class="mt-2 flex items-end gap-2">
                        <p class="text-2xl font-semibold leading-none" style="color: var(--theme-header-text-color);">{{ $item['value'] ?? '0' }}</p>

                        @if (!empty($item['suffix']))
                            <span class="text-[11px] font-semibold uppercase tracking-[0.2em]" style="color: var(--theme-muted-text-color);">{{ $item['suffix'] }}</span>
                        @endif
                    </div>
                </div>

                @if ($showIcons && !empty($item['icon']))
                    <span class="inline-flex h-11 w-11 items-center justify-center rounded-full border text-sm" style="border-color: {{ $iconBorder }}; background: {{ $iconSurface }}; color: {{ $tone }};">
                        <i class="{{ $iconClass }}" aria-hidden="true"></i>
                    </span>
                @endif
            </div>

            @if ($description)
                <p class="mt-2 text-xs leading-5" style="color: var(--theme-muted-text-color);">{{ $description }}</p>
            @endif

            @if ($showProgress)
                <div class="mt-3 flex items-center gap-3">
                    <div class="h-2 flex-1 overflow-hidden rounded-full" style="{{ $resolvedProgressTrackStyle }}">
                        <div class="h-full rounded-full" style="width: {{ $progress }}%; background-color: {{ $tone }};"></div>
                    </div>
                    <span class="text-xs font-semibold" style="color: var(--theme-muted-text-color);">{{ $progress }}%</span>
                </div>
            @endif
        </div>
        @endif
    @endforeach
</div>
