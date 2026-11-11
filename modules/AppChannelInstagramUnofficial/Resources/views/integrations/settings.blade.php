<div class="space-y-6">
    <x-ui.notice-card
        variant="warning"
        icon="fa-brands fa-instagram"
        :title="__('Instagram Unofficial access')"
        :description="__('This module uses an unofficial direct-login flow with username and password instead of Meta Graph API. Enable it only if you understand the operational and account-security risks.')"
    />

    <x-ui.surface-card class="p-5">
        <x-ui.radio-group
            name="instagram-unofficial-status"
            wire:model.live="providerState.status"
            :label="__('Status')"
            :options="$statusOptions"
            :value="$providerState['status'] ?? '0'"
        />
        @error('providerState.status')
            <p class="mt-3 text-sm font-medium" style="color: var(--theme-danger-color);">{{ $message }}</p>
        @enderror
    </x-ui.surface-card>

    <x-ui.surface-card class="p-5" accent="warning" featured>
        <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Operational note') }}</p>
        <ul class="mt-3 space-y-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">
            <li>{{ __('Users sign in with Instagram credentials directly in the app.') }}</li>
            <li>{{ __('Instagram can trigger 2FA or challenge checks at any time.') }}</li>
            <li>{{ __('Story video and some advanced unofficial flows remain intentionally restricted in this port.') }}</li>
        </ul>
    </x-ui.surface-card>
</div>
