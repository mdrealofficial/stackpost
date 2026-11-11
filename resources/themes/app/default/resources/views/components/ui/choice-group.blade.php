@props([
    'label' => null,
    'name',
    'options' => [],
    'value' => null,
    'error' => null,
])

<x-ui.field :label="$label" :error="$error" {{ $attributes->only('class') }}>
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        @foreach ($options as $option)
            @php
                $optionValue = (string) ($option['value'] ?? '');
                $optionLabel = $option['label'] ?? $optionValue;
                $optionDescription = $option['description'] ?? null;
            @endphp
            <x-ui.radio
                :name="$name"
                :value="$optionValue"
                :checked="(string) $value === $optionValue"
                :label="$optionLabel"
                :description="$optionDescription"
            />
        @endforeach
    </div>
</x-ui.field>
