@props([
    'name' => 'brand_voice',
    'label' => __('Brand Voice'),
    'value' => null,
    'help' => __('Example: calm, expert, direct, useful, avoid hype.'),
    'rows' => 6,
    'error' => null,
])

<x-ui.textarea :name="$name" :label="$label" :help="$help" :rows="$rows" :error="$error" {{ $attributes }}>{{ $value }}</x-ui.textarea>
