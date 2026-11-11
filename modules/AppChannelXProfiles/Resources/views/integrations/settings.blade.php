<div class="space-y-6">
    <x-ui.notice-card
        variant="info"
        icon="fa-brands fa-x-twitter"
        :title="__('X app setup')"
        :description="__('Create an X developer app, enable OAuth 2.0 with PKCE, then place the Client ID and Client Secret here. This provider config is shared by X-based AppChannel modules.')"
    />

    <x-ui.surface-card class="p-5">
        <x-ui.radio-group
            name="x-status"
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
            wire:model="providerState.client_id"
            type="text"
            :label="__('Client ID')"
            :help="__('Use the OAuth 2.0 Client ID from your X developer app.')"
            :error="$errors->first('providerState.client_id')"
        />

        <x-ui.input
            wire:model="providerState.client_secret"
            type="password"
            :label="__('Client Secret')"
            :help="__('Required for confidential clients and refresh-token exchange.')"
            :error="$errors->first('providerState.client_secret')"
        />

        <x-ui.input
            wire:model="providerState.callback_url"
            type="text"
            readonly
            :label="__('Callback URL')"
            :help="__('Use this exact URL in your X app callback settings.')"
            :error="$errors->first('providerState.callback_url')"
            style="background-color: rgba(var(--theme-border-color-rgb), 0.04);"
        />

        <div class="lg:col-span-2">
            <x-ui.textarea
                wire:model="providerState.permissions"
                rows="4"
                :label="__('Permissions')"
                :help="__('Keep scopes space-separated. The minimum practical set is tweet.read tweet.write users.read offline.access media.write.')"
                :error="$errors->first('providerState.permissions')"
            >{{ $providerState['permissions'] ?? 'tweet.read tweet.write tweet.moderate.write users.read follows.read follows.write offline.access space.read mute.read mute.write like.read like.write list.read list.write block.read block.write bookmark.read bookmark.write media.write users.email' }}</x-ui.textarea>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-ui.surface-card class="p-5" accent="warning" featured>
            <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Before you save') }}</p>
            <ul class="mt-3 space-y-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">
                <li>{{ __('Enable OAuth 2.0 and PKCE in the X Developer Portal.') }}</li>
                <li>{{ __('Whitelist the callback URL exactly as shown above.') }}</li>
                <li>{{ __('Make sure your app has the tweet and media scopes needed for publishing.') }}</li>
            </ul>
        </x-ui.surface-card>

        <x-ui.surface-card class="p-5" accent="primary" featured>
            <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Used by AppChannels') }}</p>
            <p class="mt-3 text-sm leading-7" style="color: var(--theme-muted-text-color);">
                {{ __('Once this provider is enabled, users can connect X profiles and reuse the shared OAuth credentials for reconnect and publishing flows.') }}
            </p>
        </x-ui.surface-card>
    </div>
</div>
