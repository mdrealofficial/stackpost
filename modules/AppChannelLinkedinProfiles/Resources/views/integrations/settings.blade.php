<div class="space-y-6">
    <x-ui.notice-card
        variant="info"
        icon="fa-brands fa-linkedin"
        :title="__('LinkedIn profile app setup')"
        :description="__('Create a dedicated LinkedIn app for member profiles, then place that App ID and App Secret here. This config is used only for LinkedIn Profile connections.')"
    />

    <x-ui.surface-card class="p-5">
        <x-ui.radio-group
            name="linkedin-profile-status"
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
            wire:model="providerState.app_id"
            type="text"
            :label="__('App ID')"
            :help="__('Use the Client ID from your LinkedIn app that is approved for member profile access.')"
            :error="$errors->first('providerState.app_id')"
        />

        <x-ui.input
            wire:model="providerState.app_secret"
            type="password"
            :label="__('App Secret')"
            :help="__('Keep this private. It is used only for LinkedIn profile OAuth and publishing.')"
            :error="$errors->first('providerState.app_secret')"
        />

        <div class="lg:col-span-2">
            <x-ui.textarea
                wire:model="providerState.permissions"
                rows="4"
                :label="__('Permissions')"
                :help="__('Scopes used when connecting LinkedIn member profiles.')"
                :error="$errors->first('providerState.permissions')"
            >{{ $providerState['permissions'] ?? 'openid profile email w_member_social' }}</x-ui.textarea>
        </div>

        <x-ui.input
            wire:model="providerState.callback_url"
            type="text"
            readonly
            :label="__('Callback URL')"
            :help="__('Add this redirect URI to the LinkedIn app for member profile connections.')"
            :error="$errors->first('providerState.callback_url')"
            style="background-color: rgba(var(--theme-border-color-rgb), 0.04);"
        />
    </div>
</div>
