@props([
    'name' => 'default_caption_platforms',
    'label' => __('Default Caption Platforms'),
    'description' => __('Preselect the channels you most often target in Caption Generator and Repurpose.'),
    'options' => [],
    'selected' => [],
    'columns' => 'md:grid-cols-2',
])

<x-ui.checkbox-list
    :name="$name"
    :label="$label"
    :description="$description"
    :options="$options"
    :selected="$selected"
    :columns="$columns"
    {{ $attributes }}
/>
