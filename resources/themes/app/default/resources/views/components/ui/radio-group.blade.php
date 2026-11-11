@props([
    'name',
    'options' => [],
    'value' => null,
    'label' => null,
])

@php
    $wireModel = $attributes->get('wire:model');
    $wireModelLive = $attributes->get('wire:model.live');
    $wireModelDefer = $attributes->get('wire:model.defer');
    $wireModelBlur = $attributes->get('wire:model.blur');
    $xModel = $attributes->get('x-model');
    $isDisabled = $attributes->has('disabled');
    $isRequired = $attributes->has('required');
@endphp

<div data-no-loading {{ $attributes->only('class')->class('space-y-2.5') }}>
    @if ($label)
        <span class="mb-2.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $label }}</span>
    @endif

    <div class="flex flex-wrap gap-x-6 gap-y-3">
        @foreach ($options as $option)
            @php
                $optionValue = $option['value'] ?? null;
                $optionLabel = $option['label'] ?? $optionValue;
                $optionDescription = $option['description'] ?? null;
                $checked = (string) $value === (string) $optionValue;
                $radioId = $name.'-'.\Illuminate\Support\Str::slug((string) $optionValue ?: 'option').'-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(6));
            @endphp

            <label for="{{ $radioId }}" data-no-loading class="inline-flex cursor-pointer items-start gap-3">
                <input
                    id="{{ $radioId }}"
                    type="radio"
                    name="{{ $name }}"
                    value="{{ $optionValue }}"
                    @checked($checked)
                    @if ($wireModel) wire:model="{{ $wireModel }}" @endif
                    @if ($wireModelLive) wire:model.live="{{ $wireModelLive }}" @endif
                    @if ($wireModelDefer) wire:model.defer="{{ $wireModelDefer }}" @endif
                    @if ($wireModelBlur) wire:model.blur="{{ $wireModelBlur }}" @endif
                    @if ($xModel) x-model="{{ $xModel }}" @endif
                    @disabled($isDisabled)
                    @required($isRequired)
                    data-no-loading
                    class="mt-0.5 h-5 w-5 shrink-0 border shadow-[0_1px_2px_rgba(15,23,42,0.04)] focus:outline-none focus:ring-0 focus-visible:ring-4"
                    style="accent-color: var(--theme-accent); border-color: color-mix(in srgb, var(--theme-border-color) 82%, rgba(var(--theme-accent-rgb), 0.18)); background-color: var(--theme-input-surface); color: var(--theme-accent); outline: none; --tw-ring-color: rgba(var(--theme-accent-rgb), 0.14);"
                >

                <span class="min-w-0">
                    @if ($optionLabel)
                        <span class="block text-sm font-medium" style="color: var(--theme-header-text-color);">{{ $optionLabel }}</span>
                    @endif

                    @if ($optionDescription)
                        <span class="mt-1 block text-sm" style="color: var(--theme-muted-text-color);">{{ $optionDescription }}</span>
                    @endif
                </span>
            </label>
        @endforeach
    </div>
</div>
