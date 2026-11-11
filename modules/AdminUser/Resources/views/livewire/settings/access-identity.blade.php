<section class="w-full">
    <x-settings.layout :heading="__('Authentication Rules')" :subheading="__('Control sign-up, account changes, and social sign-in providers for the platform.')">
        <form wire:submit="save" class="my-6 w-full space-y-6">
            <x-theme.section-card
                :title="__('User onboarding & preferences')"
                :description="__('Set the baseline rules for landing, signup, welcome flows, and identity updates.')"
                body-class="space-y-5 p-6"
            >
                <x-ui.radio-group name="auth_landing_page_status" wire:model.live="auth_landing_page_status" :label="__('Landing page')" :options="$toggleOptions" :value="$auth_landing_page_status" />
                <x-ui.radio-group name="auth_signup_page_status" wire:model.live="auth_signup_page_status" :label="__('Signup page')" :options="$toggleOptions" :value="$auth_signup_page_status" />
                <x-ui.radio-group name="auth_activation_email_new_user_status" wire:model.live="auth_activation_email_new_user_status" :label="__('Activation email to new user')" :options="$toggleOptions" :value="$auth_activation_email_new_user_status" />
                <x-ui.radio-group name="auth_welcome_email_new_user_status" wire:model.live="auth_welcome_email_new_user_status" :label="__('Welcome email to new user')" :options="$toggleOptions" :value="$auth_welcome_email_new_user_status" />
                <x-ui.radio-group name="auth_user_change_email_status" wire:model.live="auth_user_change_email_status" :label="__('User can change email')" :options="$toggleOptions" :value="$auth_user_change_email_status" />
                <x-ui.radio-group name="auth_user_change_username_status" wire:model.live="auth_user_change_username_status" :label="__('User can change username')" :options="$toggleOptions" :value="$auth_user_change_username_status" />
                <x-ui.radio-group name="auth_two_factor_authentication_status" wire:model.live="auth_two_factor_authentication_status" :label="__('Two-factor authentication')" :options="$toggleOptions" :value="$auth_two_factor_authentication_status" />
            </x-theme.section-card>

            <x-theme.section-card
                :title="__('Facebook login')"
                :description="__('Configure Facebook as a social sign-in provider for guest authentication.')"
                body-class="space-y-5 p-6"
            >
                <x-ui.radio-group name="auth_facebook_login_status" wire:model.live="auth_facebook_login_status" :label="__('Status')" :options="$toggleOptions" :value="$auth_facebook_login_status" />

                <x-ui.alert
                    variant="info"
                    inline
                    :title="__('Callback URL')"
                    :description="$facebookCallbackUrl"
                >
                    <a href="https://developers.facebook.com/apps/create/" target="_blank" class="font-medium text-[var(--theme-accent)] hover:underline">
                        {{ __('Create Facebook app') }}
                    </a>
                </x-ui.alert>

                <div class="space-y-2.5">
                    <x-ui.label>{{ __('Facebook App ID') }}</x-ui.label>
                    <x-ui.input wire:model="auth_facebook_login_app_id" type="text" :error="$errors->first('auth_facebook_login_app_id')" />
                </div>
                <div class="space-y-2.5">
                    <x-ui.label>{{ __('Facebook App Secret') }}</x-ui.label>
                    <x-ui.input wire:model="auth_facebook_login_app_secret" type="text" :error="$errors->first('auth_facebook_login_app_secret')" />
                </div>
                <div class="space-y-2.5">
                    <x-ui.label>{{ __('Facebook App Version') }}</x-ui.label>
                    <x-ui.input wire:model="auth_facebook_login_app_version" type="text" :error="$errors->first('auth_facebook_login_app_version')" />
                </div>
            </x-theme.section-card>

            <x-theme.section-card
                :title="__('Google login')"
                :description="__('Configure Google as a social sign-in provider for guest authentication.')"
                body-class="space-y-5 p-6"
            >
                <x-ui.radio-group name="auth_google_login_status" wire:model.live="auth_google_login_status" :label="__('Status')" :options="$toggleOptions" :value="$auth_google_login_status" />

                <x-ui.alert
                    variant="info"
                    inline
                    :title="__('Callback URL')"
                    :description="$googleCallbackUrl"
                >
                    <a href="https://console.cloud.google.com/projectcreate" target="_blank" class="font-medium text-[var(--theme-accent)] hover:underline">
                        {{ __('Create Google app') }}
                    </a>
                </x-ui.alert>

                <div class="space-y-2.5">
                    <x-ui.label>{{ __('Google Client ID') }}</x-ui.label>
                    <x-ui.input wire:model="auth_google_login_client_id" type="text" :error="$errors->first('auth_google_login_client_id')" />
                </div>
                <div class="space-y-2.5">
                    <x-ui.label>{{ __('Google Client Secret') }}</x-ui.label>
                    <x-ui.input wire:model="auth_google_login_client_secret" type="text" :error="$errors->first('auth_google_login_client_secret')" />
                </div>
            </x-theme.section-card>

            <x-theme.section-card
                :title="__('X login')"
                :description="__('Configure X (Twitter) as a social sign-in provider for guest authentication.')"
                body-class="space-y-5 p-6"
            >
                <x-ui.radio-group name="auth_x_login_status" wire:model.live="auth_x_login_status" :label="__('Status')" :options="$toggleOptions" :value="$auth_x_login_status" />

                <x-ui.alert
                    variant="info"
                    inline
                    :title="__('Callback URL')"
                    :description="$xCallbackUrl"
                >
                    <a href="https://developer.twitter.com/en/portal/dashboard" target="_blank" class="font-medium text-[var(--theme-accent)] hover:underline">
                        {{ __('Create X app') }}
                    </a>
                </x-ui.alert>

                <div class="space-y-2.5">
                    <x-ui.label>{{ __('X Client ID') }}</x-ui.label>
                    <x-ui.input wire:model="auth_x_login_client_id" type="text" :error="$errors->first('auth_x_login_client_id')" />
                </div>
                <div class="space-y-2.5">
                    <x-ui.label>{{ __('X Client Secret') }}</x-ui.label>
                    <x-ui.input wire:model="auth_x_login_client_secret" type="text" :error="$errors->first('auth_x_login_client_secret')" />
                </div>
            </x-theme.section-card>

            <div class="flex items-center gap-4">
                <x-ui.button type="submit">{{ __('Save changes') }}</x-ui.button>

                <x-action-message class="text-emerald-600 dark:text-emerald-400" on="settings-saved">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </form>
    </x-settings.layout>
</section>
