@props([
    'eyebrow' => null,
    'title' => null,
    'description' => null,
    'cardClass' => '',
    'cardPadding' => 'md',
])

<div {{ $attributes->class('space-y-6') }}>
    <x-ui.sub-header
        :eyebrow="$eyebrow"
        :title="$title"
        :description="$description"
    >
        @if (isset($actions))
            <x-slot:actions>
                {{ $actions }}
            </x-slot:actions>
        @endif
    </x-ui.sub-header>

    <x-ui.card :padding="$cardPadding" :class="$cardClass">
        {{ $slot }}
    </x-ui.card>
</div>
