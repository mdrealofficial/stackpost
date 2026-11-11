@props([
    'name',
    'value' => null,
    'checked' => false,
    'label' => null,
    'description' => null,
    'id' => null,
])

@php
    $radioId = $id ?: $name.'-'.\Illuminate\Support\Str::slug((string) $value ?: 'option').'-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(6));
    $inputAttributes = $attributes->except('class');
@endphp

<label for="{{ $radioId }}" class="inline-flex cursor-pointer items-start gap-3">
    <input
        id="{{ $radioId }}"
        type="radio"
        name="{{ $name }}"
        value="{{ $value }}"
        @checked($checked)
        {{ $inputAttributes }}
        class="mt-0.5 h-5 w-5 shrink-0 border shadow-[0_1px_2px_rgba(15,23,42,0.04)] focus:outline-none focus:ring-0 focus-visible:ring-4"
        style="accent-color: var(--theme-accent); border-color: color-mix(in srgb, var(--theme-border-color) 82%, rgba(var(--theme-accent-rgb), 0.18)); background-color: var(--theme-input-surface); color: var(--theme-accent); outline: none; --tw-ring-color: rgba(var(--theme-accent-rgb), 0.14);"
    >

    <span class="min-w-0">
        @if ($label)
            <span class="block text-sm font-medium" style="color: var(--theme-header-text-color);">{{ $label }}</span>
        @endif

        @if ($description)
            <span class="mt-1 block text-sm" style="color: var(--theme-muted-text-color);">{{ $description }}</span>
        @endif
    </span>
</label>
