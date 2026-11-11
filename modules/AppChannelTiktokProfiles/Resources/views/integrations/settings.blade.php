<div class="space-y-6">
    <x-ui.notice-card
        variant="info"
        icon="fa-brands fa-tiktok"
        :title="__('TikTok app setup')"
        :description="__('Create a TikTok developer app, then place the Client Key and Client Secret here. This provider config is shared by TikTok-based AppChannel modules.')"
    />

    <x-ui.surface-card class="p-5">
        <x-ui.radio-group
            name="tiktok-status"
            wire:model.live="providerState.status"
            :label="__('Status')"
            :options="$statusOptions"
            :value="$providerState['status'] ?? '0'"
        />
        @error('providerState.status')
            <p class="mt-3 text-sm font-medium" style="color: var(--theme-danger-color);">{{ $message }}</p>
        @enderror
    </x-ui.surface-card>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-ui.input
            wire:model="providerState.client_key"
            type="text"
            :label="__('Client Key')"
            :help="__('Copy this from your TikTok developer app credentials.')"
            :error="$errors->first('providerState.client_key')"
        />

        <x-ui.input
            wire:model="providerState.client_secret"
            type="password"
            :label="__('Client Secret')"
            :help="__('Keep this private. It will be used by TikTok connection and publishing flows.')"
            :error="$errors->first('providerState.client_secret')"
        />

        <x-ui.input
            wire:model="providerState.callback_url"
            type="text"
            readonly
            :label="__('Callback URL')"
            :help="__('Use this exact URL in your TikTok Login Kit or content posting redirect settings.')"
            :error="$errors->first('providerState.callback_url')"
            style="background-color: rgba(var(--theme-border-color-rgb), 0.04);"
        />

        <div class="lg:col-span-2">
            <x-ui.textarea
                wire:model="providerState.permissions"
                rows="4"
                :label="__('Permissions')"
                :help="__('Keep scopes comma-separated. Adjust them to match the TikTok features your app is approved to use.')"
                :error="$errors->first('providerState.permissions')"
            >{{ $providerState['permissions'] ?? 'user.info.basic,user.info.profile,user.info.stats,video.list,video.publish' }}</x-ui.textarea>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-ui.surface-card class="p-5" accent="warning" featured>
            <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Before you save') }}</p>
            <ul class="mt-3 space-y-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">
                <li>{{ __('Make sure your TikTok app has the correct products and approved scopes enabled.') }}</li>
                <li>{{ __('Confirm the callback URL matches your TikTok app configuration exactly.') }}</li>
                <li>{{ __('If publishing is still using a stub publisher, these settings prepare the provider for the next API integration step.') }}</li>
            </ul>
        </x-ui.surface-card>

        <x-ui.surface-card class="p-5" accent="primary" featured>
            <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Used by AppChannels') }}</p>
            <p class="mt-3 text-sm leading-7" style="color: var(--theme-muted-text-color);">
                {{ __('Once this provider is enabled and fully wired to a TikTok API flow, users can connect TikTok profiles and reuse the shared provider credentials across channel capabilities.') }}
            </p>
        </x-ui.surface-card>
    </div>
</div>
