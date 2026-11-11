@props([
    'eyebrow' => null,
    'title' => null,
    'description' => null,
    'padding' => 'md',
    'tone' => 'default',
    'accent' => 'none',
    'featured' => false,
    'bodyClass' => '',
    'headerClass' => '',
    'footerClass' => '',
    'eyebrowClass' => '',
    'titleClass' => '',
    'descriptionClass' => '',
])

<x-ui.surface-card
    :padding="'none'"
    :tone="$tone"
    :accent="$accent"
    :featured="$featured"
    {{ $attributes }}
>
    @if ($eyebrow || $title || $description || isset($actions))
        <div class="border-b px-6 py-5 {{ $headerClass }}" style="border-color: var(--theme-border-color);">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    @if ($eyebrow)
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] {{ $eyebrowClass }}" style="color: var(--theme-muted-text-color);">{{ $eyebrow }}</p>
                    @endif

                    @if ($title)
                        <h2 class="mt-2 text-[1.7rem] font-semibold tracking-[-0.035em] {{ $titleClass }}" style="color: var(--theme-header-text-color);">{{ $title }}</h2>
                    @endif

                    @if ($description)
                        <p class="mt-2 text-sm leading-7 {{ $descriptionClass }}" style="color: var(--theme-muted-text-color);">{{ $description }}</p>
                    @endif
                </div>

                @isset($actions)
                    <div class="shrink-0">
                        {{ $actions }}
                    </div>
                @endisset
            </div>
        </div>
    @endif

    <div class="{{ $padding === 'none' ? '' : 'p-6' }} {{ $bodyClass }}">
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="border-t px-6 py-4 {{ $footerClass }}" style="border-color: var(--theme-border-color);">
            {{ $footer }}
        </div>
    @endisset
</x-ui.surface-card>
