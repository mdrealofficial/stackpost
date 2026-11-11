@props([
    'title' => null,
    'toggleable' => false,
    'name' => null,
    'checked' => false,
])

<div {{ $attributes->only('class')->class('overflow-hidden rounded-[0.75rem] border shadow-[0_2px_10px_rgba(15,23,42,0.04)]') }} style="border-color: var(--theme-border-color); background-color: var(--theme-surface-base); {{ $attributes->get('style') }}">
    <div class="px-6 py-5">
        @if ($toggleable)
            <x-ui.checkbox :name="$name" value="1" :checked="$checked" :label="$title" />
        @elseif ($title)
            <h2 class="text-[1.125rem] font-semibold" style="color: var(--theme-header-text-color);">{{ $title }}</h2>
        @endif
    </div>

    @if (trim((string) $slot) !== '')
        <div class="space-y-6 border-t px-6 py-6" style="border-color: var(--theme-border-color);">
            {{ $slot }}
        </div>
    @endif
</div>
