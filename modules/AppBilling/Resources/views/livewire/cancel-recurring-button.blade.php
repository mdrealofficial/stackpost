<x-ui.button
    type="button"
    variant="outline"
    size="sm"
    wire:click="cancel"
    wire:confirm="{{ __('Cancel this recurring subscription?|This stops future recurring charges at the payment gateway. Your current paid period stays active until it expires.') }}"
>
    {{ __('Cancel recurring') }}
</x-ui.button>
