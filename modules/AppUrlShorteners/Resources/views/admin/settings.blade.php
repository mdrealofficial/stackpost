<section class="w-full">
    <x-settings.layout :heading="__('URL Shorteners')" :subheading="__('Configure the providers used to shorten links before they are scheduled or published.')">
        <form wire:submit="save" class="my-6 w-full space-y-6">
            <x-theme.section-card
                :title="__('General configuration')"
                :description="__('Choose the default provider that should be used when a module requests URL shortening.')"
                body-class="space-y-5 p-6"
            >
                <x-ui.select wire:model.defer="url_shorteners_platform" :label="__('Default URL Shortener Platform')" :error="$errors->first('url_shorteners_platform')">
                    <option value="0">{{ __('Disable') }}</option>
                    @foreach ($platforms as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </x-ui.select>
            </x-theme.section-card>

            <x-theme.section-card :title="__('Bitly')" :description="__('Use a Bitly token to generate short links.')" body-class="space-y-5 p-6">
                <x-ui.field :label="__('Status')" :error="$errors->first('bitly_status')">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-ui.radio name="bitly-status" value="1" wire:model="bitly_status" :label="__('Enable')" />
                        <x-ui.radio name="bitly-status" value="0" wire:model="bitly_status" :label="__('Disable')" />
                    </div>
                </x-ui.field>

                <x-ui.input wire:model.defer="bitly_api_key" :label="__('API Token')" :error="$errors->first('bitly_api_key')" />
            </x-theme.section-card>

            <x-theme.section-card :title="__('TinyURL')" :description="__('Use the TinyURL API to create shortened links.')" body-class="space-y-5 p-6">
                <x-ui.field :label="__('Status')" :error="$errors->first('tinyurl_status')">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-ui.radio name="tinyurl-status" value="1" wire:model="tinyurl_status" :label="__('Enable')" />
                        <x-ui.radio name="tinyurl-status" value="0" wire:model="tinyurl_status" :label="__('Disable')" />
                    </div>
                </x-ui.field>

                <x-ui.input wire:model.defer="tinyurl_api_key" :label="__('API Key')" :error="$errors->first('tinyurl_api_key')" />
            </x-theme.section-card>

            <x-theme.section-card :title="__('Rebrandly')" :description="__('Use Rebrandly when you want branded short links.')" body-class="space-y-5 p-6">
                <x-ui.field :label="__('Status')" :error="$errors->first('rebrandly_status')">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-ui.radio name="rebrandly-status" value="1" wire:model="rebrandly_status" :label="__('Enable')" />
                        <x-ui.radio name="rebrandly-status" value="0" wire:model="rebrandly_status" :label="__('Disable')" />
                    </div>
                </x-ui.field>

                <div class="grid gap-5 md:grid-cols-2">
                    <x-ui.input wire:model.defer="rebrandly_api_key" :label="__('API Key')" :error="$errors->first('rebrandly_api_key')" />
                    <x-ui.input wire:model.defer="rebrandly_domain" :label="__('Domain')" :error="$errors->first('rebrandly_domain')" />
                </div>
            </x-theme.section-card>

            <x-theme.section-card :title="__('Short.io')" :description="__('Use Short.io when you manage a custom short domain.')" body-class="space-y-5 p-6">
                <x-ui.field :label="__('Status')" :error="$errors->first('shortio_status')">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-ui.radio name="shortio-status" value="1" wire:model="shortio_status" :label="__('Enable')" />
                        <x-ui.radio name="shortio-status" value="0" wire:model="shortio_status" :label="__('Disable')" />
                    </div>
                </x-ui.field>

                <div class="grid gap-5 md:grid-cols-2">
                    <x-ui.input wire:model.defer="shortio_api_key" :label="__('API Key')" :error="$errors->first('shortio_api_key')" />
                    <x-ui.input wire:model.defer="shortio_domain" :label="__('Domain')" :error="$errors->first('shortio_domain')" :help="__('Example: go.example.com')" />
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
