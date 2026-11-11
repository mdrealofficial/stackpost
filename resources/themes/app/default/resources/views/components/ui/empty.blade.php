@props([
    'title' => null,
    'description' => null,
    'icon' => 'fa-light fa-inbox',
])

<div {{ $attributes->merge(['style' => 'border-color: var(--theme-border-color); background-color: var(--theme-empty-bg); color: var(--theme-muted-text-color);'])->class('rounded-[var(--theme-card-radius,0.9rem)] border border-dashed px-6 py-10 text-center') }}>
    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl shadow-sm" style="background-color: var(--theme-empty-icon-bg); color: var(--theme-muted-text-color);">
        @if (isset($icon) && !($icon instanceof \Illuminate\Support\HtmlString) && trim((string) $icon) !== '')
            <i class="{{ $icon }} text-2xl"></i>
        @else
            {{ $icon ?? new \Illuminate\Support\HtmlString('<i class="fa-light fa-inbox text-2xl"></i>') }}
        @endif
    </div>

    @if ($title)
        <h3 class="mt-4 text-base font-semibold" style="color: var(--theme-header-text-color);">{{ $title }}</h3>
    @endif
    @if ($description)
        <p class="mx-auto mt-2 max-w-md text-sm leading-7" style="color: var(--theme-muted-text-color);">{{ $description }}</p>
    @endif

    @if (trim((string) $slot) !== '')
        <div class="mt-5">
            {{ $slot }}
        </div>
    @endif
</div>
