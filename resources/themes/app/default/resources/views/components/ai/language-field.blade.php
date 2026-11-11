@props([
    'name',
    'label' => __('Default Language'),
    'value' => null,
    'help' => null,
    'preferred' => ['vi', 'en'],
    'placeholder' => null,
    'error' => null,
])

<x-ui.language-select
    :name="$name"
    :label="$label"
    :value="$value"
    :help="$help"
    :preferred="$preferred"
    :placeholder="$placeholder"
    :error="$error"
    {{ $attributes }}
/>
