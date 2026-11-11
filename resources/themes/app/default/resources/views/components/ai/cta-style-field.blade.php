@props([
    'name' => 'preferred_cta_style',
    'label' => __('Preferred CTA Style'),
    'value' => null,
    'help' => __('Example: ask for comments, invite saves, soft conversion, DM-first.'),
    'error' => null,
])

<x-ui.input :name="$name" :label="$label" :value="$value" :help="$help" :error="$error" {{ $attributes }} />
