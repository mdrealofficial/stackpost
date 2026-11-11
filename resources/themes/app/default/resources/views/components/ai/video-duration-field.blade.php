@props([
    'name' => 'video_duration',
    'label' => __('Video Duration'),
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
