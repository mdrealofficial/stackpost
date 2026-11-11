<div class="space-y-6">
    <x-ui.notice-card
        variant="info"
        icon="fa-brands fa-facebook-f"
        :title="__('Facebook app setup')"
        :description="__('Create a Facebook app, enable the required product access, then place the App ID and App Secret here. This provider config is shared by every Facebook-based AppChannel module.')"
    />

    <x-ui.surface-card class="p-5">
        <x-ui.radio-group
            name="facebook-status"
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
            :help="__('Copy this from the basic settings screen of your Facebook app.')"
            :error="$errors->first('providerState.app_id')"
        />

        <x-ui.input
            wire:model="providerState.app_secret"
            type="password"
            :label="__('App Secret')"
            :help="__('Keep this private. AppChannels will reuse it when users connect Facebook pages.')"
            :error="$errors->first('providerState.app_secret')"
        />

        <x-ui.input
            wire:model="providerState.graph_version"
            type="text"
            :label="__('Graph version')"
            :help="__('Pin the Graph API version you want every Facebook page connection to use.')"
            :error="$errors->first('providerState.graph_version')"
        />

        <x-ui.input
            wire:model="providerState.callback_url"
            type="text"
            readonly
            :label="__('Callback URL')"
            :help="__('Paste this exact URL into the Valid OAuth Redirect URIs field in your Facebook app.')"
            :error="$errors->first('providerState.callback_url')"
            style="background-color: rgba(var(--theme-border-color-rgb), 0.04);"
        />

        <div class="lg:col-span-2">
            <x-ui.textarea
                wire:model="providerState.permissions"
                rows="5"
                :label="__('Permissions')"
                :help="__('Keep permissions comma-separated. Use only the scopes your Facebook review actually requires.')"
                :error="$errors->first('providerState.permissions')"
            >{{ $providerState['permissions'] ?? 'pages_read_engagement,pages_manage_posts,pages_show_list,business_management,pages_manage_metadata,pages_manage_engagement,pages_read_user_content,pages_messaging,read_insights' }}</x-ui.textarea>
        </div>

        <x-ui.input
            wire:model="providerState.webhook_verify_token"
            type="password"
            :label="__('Webhook verify token')"
            :help="__('Create a private token and enter the exact same value in Meta Messenger Webhooks.')"
            :error="$errors->first('providerState.webhook_verify_token')"
        />

        <x-ui.input
            wire:model="providerState.inbox_webhook_url"
            type="text"
            readonly
            :label="__('Inbox webhook URL')"
            :help="__('Paste this exact HTTPS URL into the Meta webhook Callback URL field.')"
            :error="$errors->first('providerState.inbox_webhook_url')"
            style="background-color: rgba(var(--theme-border-color-rgb), 0.04);"
        />
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-ui.surface-card class="p-5" accent="warning" featured>
            <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Before you save') }}</p>
            <ul class="mt-3 space-y-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">
                <li>{{ __('Make sure the Facebook app is in the correct mode for your team workflow.') }}</li>
                <li>{{ __('Confirm the callback URL is whitelisted exactly as shown above.') }}</li>
                <li>{{ __('Only request permissions you truly need for page publishing and reading.') }}</li>
            </ul>
        </x-ui.surface-card>

        <x-ui.surface-card class="p-5" accent="primary" featured>
            <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Used by AppChannels') }}</p>
            <p class="mt-3 text-sm leading-7" style="color: var(--theme-muted-text-color);">
                {{ __('Once this provider is enabled and has valid credentials, users can open Channels, click Add channels, and connect Facebook Page capabilities from the shared provider config.') }}
            </p>
        </x-ui.surface-card>
    </div>
</div>
