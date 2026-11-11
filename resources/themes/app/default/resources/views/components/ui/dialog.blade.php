@props([
    'title' => null,
    'description' => null,
    'width' => 'lg',
    'dismissible' => false,
])

{{-- Share modal focus, motion and sizing while preserving the confirmation API. --}}
<x-ui.modal :title="$title" :description="$description" :width="$width" :dismissible="$dismissible" {{ $attributes }}>
    @if (isset($trigger))
        <x-slot:trigger>{{ $trigger }}</x-slot:trigger>
    @endif
    {{ $slot }}
    @if (isset($footer))
        <x-slot:footer>{{ $footer }}</x-slot:footer>
    @endif
</x-ui.modal>
