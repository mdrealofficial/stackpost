@props([
    'action',
    'method' => 'DELETE',
    'title',
    'description' => null,
    'submitLabel' => null,
    'cancelLabel' => null,
    'variant' => 'danger',
    'size' => 'md',
])

<x-ui.dialog
    width="sm"
    dismissible
    :title="$title"
    :description="$description"
>
    <x-slot:trigger>
        {{ $trigger }}
    </x-slot:trigger>

    <x-slot:footer>
        <div class="flex items-center justify-end gap-3">
            <x-ui.button type="button" variant="outline" x-on:click="open = false">
                {{ $cancelLabel ?: __('Cancel') }}
            </x-ui.button>

            <form method="POST" action="{{ $action }}">
                @csrf
                @if (strtoupper($method) !== 'POST')
                    @method($method)
                @endif
                {{ $slot }}
                <x-ui.button type="submit" :variant="$variant" :size="$size">
                    {{ $submitLabel ?: __('Confirm') }}
                </x-ui.button>
            </form>
        </div>
    </x-slot:footer>
</x-ui.dialog>
