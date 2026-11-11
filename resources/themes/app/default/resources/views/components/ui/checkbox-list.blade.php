@props([
    'label' => null,
    'name',
    'options' => [],
    'selected' => [],
    'description' => null,
    'columns' => 'md:grid-cols-2 xl:grid-cols-3',
    'selectAllLabel' => null,
])

@php
    $normalizedSelected = collect($selected)->map(fn ($value) => (string) $value)->values()->all();
    $optionKeys = collect($options)->pluck('key')->map(fn ($value) => (string) $value)->values()->all();
    $checkboxListId = 'checkbox-list-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(8));
@endphp

<div class="space-y-4" x-data='{"selected": @json($normalizedSelected), "options": @json($optionKeys)}' {{ $attributes->only('class') }}>
    <div class="flex items-center justify-between gap-4 border-b pb-3" style="border-color: var(--theme-border-color);">
        <div>
            @if ($label)
                <p class="text-sm font-medium" style="color: var(--theme-header-text-color);">{{ $label }}</p>
            @endif
            @if ($description)
                <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ $description }}</p>
            @endif
        </div>

        @if (($selectAllLabel ?? true) && count($optionKeys) > 1)
            <label class="group inline-flex cursor-pointer items-start gap-3 whitespace-nowrap">
                <input
                    type="checkbox"
                    class="peer sr-only"
                    :checked="options.length > 0 && selected.length === options.length"
                    x-on:change="selected = $event.target.checked ? [...options] : []"
                >
                <span class="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-[0.4rem] border border-slate-300 bg-white text-white shadow-[0_1px_2px_rgba(15,23,42,0.06)] transition-all duration-150 group-hover:border-[color:rgba(var(--theme-accent-rgb),0.45)] peer-checked:border-[color:var(--theme-accent)] peer-checked:bg-[color:var(--theme-accent)] peer-checked:text-white peer-checked:shadow-[0_0_0_3px_rgba(var(--theme-accent-rgb),0.14)] peer-checked:[&_svg]:opacity-100">
                    <svg viewBox="0 0 16 16" aria-hidden="true" class="h-3.5 w-3.5 opacity-0 transition-opacity duration-150" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2">
                        <path d="M3.5 8.5 6.5 11.5 12.5 4.5" />
                    </svg>
                </span>
                <span class="pt-0.5 text-sm font-medium" style="color: var(--theme-header-text-color);">{{ is_string($selectAllLabel) ? $selectAllLabel : __('Select All') }}</span>
            </label>
        @endif
    </div>

    <div class="grid gap-x-6 gap-y-4 {{ $columns }}">
        @foreach ($options as $option)
            <x-ui.checkbox
                :name="$name.'[]'"
                :value="$option['key']"
                :label="$option['label']"
                :id="$checkboxListId.'-'.\Illuminate\Support\Str::slug((string) $option['key'])"
                :checked="in_array((string) $option['key'], $normalizedSelected, true)"
                x-model="selected"
            />
        @endforeach
    </div>
</div>
