@php
    $settings = credit_settings();
    $primaryBuyMore = $settings->actionEmptyCta() === 'buy_more';
    $canBuyMore = auth()->check() && credit_topup_service()->canUserBuyTopup(auth()->user());
@endphp

<div class="mt-3 flex flex-wrap items-center gap-3">
    @if ($primaryBuyMore)
        @if ($canBuyMore)
            <x-ui.button :href="route('portal.credits')" size="sm" wire:navigate>
                {{ __('Buy more credits') }}
            </x-ui.button>
        @endif
        <x-ui.button :href="route('portal.packages')" variant="{{ $canBuyMore ? 'outline' : 'primary' }}" size="sm" wire:navigate>
            {{ __('Upgrade plan') }}
        </x-ui.button>
    @else
        <x-ui.button :href="route('portal.packages')" size="sm" wire:navigate>
            {{ __('Upgrade plan') }}
        </x-ui.button>
        @if ($canBuyMore)
            <x-ui.button :href="route('portal.credits')" variant="outline" size="sm" wire:navigate>
                {{ __('Buy more credits') }}
            </x-ui.button>
        @endif
    @endif
</div>
