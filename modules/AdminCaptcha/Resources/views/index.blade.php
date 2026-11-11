<section class="w-full">
    <x-settings.layout :heading="__('Captcha Settings')" :subheading="__('Configure bot protection for authentication and guest-facing forms.')">
        <form wire:submit="save" class="my-6 w-full space-y-6">
            <x-theme.section-card
                :title="__('General configuration')"
                :description="__('Choose the captcha provider the platform should use by default.')"
                body-class="space-y-5 p-6"
            >
                <x-ui.select wire:model.live="captcha_type" :label="__('Captcha Type')">
                    @foreach ($captchaTypeOptions as $option)
                        <option value="{{ $option['value'] }}">{{ __($option['label']) }}</option>
                    @endforeach
                </x-ui.select>
            </x-theme.section-card>

            <x-theme.section-card
                :title="__('Google reCaptcha V2')"
                :description="__('Configure Google reCaptcha keys for guest authentication and public forms.')"
                body-class="space-y-5 p-6"
            >
                <x-ui.radio-group name="auth_google_recaptcha_status" wire:model.live="auth_google_recaptcha_status" :label="__('Status')" :options="$toggleOptions" :value="$auth_google_recaptcha_status" />

                <div class="space-y-2.5">
                    <x-ui.label>{{ __('Google site key') }}</x-ui.label>
                    <x-ui.input wire:model="auth_google_recaptcha_site_key" type="text" :error="$errors->first('auth_google_recaptcha_site_key')" />
                </div>

                <div class="space-y-2.5">
                    <x-ui.label>{{ __('Google secret key') }}</x-ui.label>
                    <x-ui.input wire:model="auth_google_recaptcha_secret_key" type="text" :error="$errors->first('auth_google_recaptcha_secret_key')" />
                </div>
            </x-theme.section-card>

            <x-theme.section-card
                :title="__('Cloudflare Turnstile')"
                :description="__('Configure Cloudflare Turnstile keys as an alternative captcha provider.')"
                body-class="space-y-5 p-6"
            >
                <x-ui.radio-group name="auth_cloudflare_turnstile_status" wire:model.live="auth_cloudflare_turnstile_status" :label="__('Status')" :options="$toggleOptions" :value="$auth_cloudflare_turnstile_status" />

                <div class="space-y-2.5">
                    <x-ui.label>{{ __('Cloudflare site key') }}</x-ui.label>
                    <x-ui.input wire:model="auth_cloudflare_turnstile_site_key" type="text" :error="$errors->first('auth_cloudflare_turnstile_site_key')" />
                </div>

                <div class="space-y-2.5">
                    <x-ui.label>{{ __('Cloudflare secret key') }}</x-ui.label>
                    <x-ui.input wire:model="auth_cloudflare_turnstile_secret_key" type="text" :error="$errors->first('auth_cloudflare_turnstile_secret_key')" />
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
