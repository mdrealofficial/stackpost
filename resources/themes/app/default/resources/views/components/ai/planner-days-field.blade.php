@props([
    'name' => 'planner_days',
    'label' => __('Planner Days'),
    'value' => null,
    'help' => null,
    'error' => null,
])

<x-ui.input
    :name="$name"
    type="number"
    min="3"
    max="31"
    :label="$label"
    :value="$value"
    :help="$help"
    :error="$error"
    {{ $attributes }}
/>
