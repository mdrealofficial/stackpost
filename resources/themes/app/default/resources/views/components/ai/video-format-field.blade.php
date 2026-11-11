@props([
    'name' => 'video_format',
    'label' => __('Video Format'),
    'value' => null,
    'help' => null,
    'error' => null,
    'options' => [],
])

<x-ui.select :name="$name" :label="$label" :help="$help" :error="$error" {{ $attributes }}>
    @foreach ($options as $option)
        <option value="{{ $option['value'] }}" @selected((string) $value === (string) $option['value'])>{{ $option['label'] }}</option>
    @endforeach
</x-ui.select>
