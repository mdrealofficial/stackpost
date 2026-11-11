<section class="w-full">
    <x-settings.layout :heading="__('Google Analytics')" :subheading="__('Configure GA4 tracking for the public site and optionally for the logged-in app area.')">
        <form wire:submit="save" class="my-6 w-full space-y-6">
            <x-theme.section-card
                :title="__('Tracking status')"
                :description="__('Turn Google Analytics on or off globally. Tracking only loads when a valid GA4 measurement ID is configured.')"
                body-class="space-y-5 p-6"
            >
                <x-ui.field :label="__('Google Analytics status')" :error="$errors->first('google_analytics_status')">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-ui.radio name="google-analytics-status" value="1" wire:model="google_analytics_status" :label="__('Enable')" />
                        <x-ui.radio name="google-analytics-status" value="0" wire:model="google_analytics_status" :label="__('Disable')" />
                    </div>
                </x-ui.field>

                <x-ui.input
                    wire:model.defer="google_analytics_measurement_id"
                    :label="__('Measurement ID')"
                    :error="$errors->first('google_analytics_measurement_id')"
                    :help="__('Example: G-ABC123XYZ9')"
                    placeholder="G-XXXXXXXXXX"
                />
            </x-theme.section-card>

            <x-theme.section-card
                :title="__('Tracking scope')"
                :description="__('Choose where the analytics snippet should be injected.')"
                body-class="space-y-5 p-6"
            >
                <x-ui.field :label="__('Guest site')" :error="$errors->first('google_analytics_track_guest')">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-ui.radio name="google-analytics-track-guest" value="1" wire:model="google_analytics_track_guest" :label="__('Track guest pages')" />
                        <x-ui.radio name="google-analytics-track-guest" value="0" wire:model="google_analytics_track_guest" :label="__('Do not track')" />
                    </div>
                </x-ui.field>

                <x-ui.field :label="__('Logged-in app')" :error="$errors->first('google_analytics_track_app')">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-ui.radio name="google-analytics-track-app" value="1" wire:model="google_analytics_track_app" :label="__('Track app pages')" />
                        <x-ui.radio name="google-analytics-track-app" value="0" wire:model="google_analytics_track_app" :label="__('Do not track')" />
                    </div>
                </x-ui.field>
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
