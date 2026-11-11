@props([
    'title' => null,
    'info' => null,
    'footerText' => null,
    'headerClass' => '',
    'eyebrowClass' => '',
    'titleClass' => '',
    'descriptionClass' => '',
])

<x-ui.section-card
    padding="none"
    class="sp-datatable shadow-none"
    :eyebrow="null"
    :title="$title"
    :description="$info"
    :header-class="$headerClass"
    :eyebrow-class="$eyebrowClass"
    :title-class="$titleClass"
    :description-class="$descriptionClass"
>
    <x-slot:actions>
        @if (isset($toolbar))
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                {{ $toolbar }}
            </div>
        @endif
    </x-slot:actions>

    <div class="[&>div]:rounded-none [&>div]:border-0 [&>div]:shadow-none">
        {{ $slot }}
    </div>

    @if (isset($footer) || $footerText)
        <x-slot:footer>
            <div class="flex flex-col gap-4 text-sm sm:flex-row sm:items-center sm:justify-between" style="color: var(--theme-muted-text-color);">
                <div>{{ $footerText }}</div>
                <div>{{ $footer ?? '' }}</div>
            </div>
        </x-slot:footer>
    @endif
</x-ui.section-card>
