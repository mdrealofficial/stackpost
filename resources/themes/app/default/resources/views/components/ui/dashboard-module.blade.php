@props([
    'eyebrow' => null,
    'title' => null,
    'description' => null,
    'class' => '',
])

<section {{ $attributes->class(['sp-dashboard-module min-w-0 max-w-full space-y-4', $class]) }}>
    @if (filled($title) || filled($description) || filled($eyebrow) || isset($actions))
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="max-w-3xl">
                @if (filled($eyebrow))
                    <p class="text-[11px] font-semibold tracking-normal" style="color: var(--theme-muted-text-color);">{{ $eyebrow }}</p>
                @endif

                @if (filled($title))
                    <h3 class="mt-2 text-xl font-semibold tracking-[-0.04em]" style="color: var(--theme-header-text-color);">{{ $title }}</h3>
                @endif

                @if (filled($description))
                    <p class="mt-2 text-sm leading-7" style="color: var(--theme-muted-text-color);">{{ $description }}</p>
                @endif
            </div>

            @isset($actions)
                <div class="flex flex-wrap items-center gap-3">
                    {{ $actions }}
                </div>
            @endisset
        </div>
    @endif

    {{ $slot }}
</section>
