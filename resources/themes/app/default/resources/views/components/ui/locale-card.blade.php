@props([
    'title' => null,
    'locale' => null,
    'default' => false,
])

<x-ui.card {{ $attributes->class('space-y-4') }}>
    <div class="flex items-center justify-between gap-4">
        <div>
            @if ($title)
                <p class="font-semibold" style="color: var(--theme-header-text-color);">{{ $title }}</p>
            @endif
            @if ($locale)
                <p class="text-sm uppercase tracking-[0.16em]" style="color: var(--theme-muted-text-color);">{{ $locale }}</p>
            @endif
        </div>

        @if ($default)
            <x-ui.badge variant="primary">{{ __('Default') }}</x-ui.badge>
        @endif
    </div>

    {{ $slot }}
</x-ui.card>
