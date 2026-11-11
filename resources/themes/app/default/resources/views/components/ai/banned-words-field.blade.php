@props([
    'name' => 'banned_words',
    'label' => __('Banned Words / Phrases'),
    'value' => null,
    'help' => __('Comma-separated words or phrases the AI should avoid in outputs.'),
    'rows' => 4,
    'error' => null,
])

<x-ui.textarea :name="$name" :label="$label" :help="$help" :rows="$rows" :error="$error" {{ $attributes }}>{{ $value }}</x-ui.textarea>
