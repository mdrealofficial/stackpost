<section class="w-full">
    <x-settings.layout :heading="__('File Manager Settings')" :subheading="__('Control upload limits, allowed file types, storage disk, and which action menu integrations are exposed in the file workspace.')">
        <form wire:submit="save" class="space-y-6">
            <x-theme.section-card
                :title="__('Storage policy')"
                :description="__('Set the shared storage driver and upload rules used by the admin and user file manager.')"
                body-class="space-y-5 p-6"
            >
                <div class="grid gap-4 md:grid-cols-2">
                    <x-ui.select wire:model="appfiles_disk" name="appfiles_disk" :label="__('Storage driver')" :error="$errors->first('appfiles_disk')">
                        @foreach ($storageDiskOptions as $option)
                            <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.input wire:model="appfiles_default_max_upload_mb" name="appfiles_default_max_upload_mb" type="number" :label="__('Default max upload (MB)')" :value="$appfiles_default_max_upload_mb" :error="$errors->first('appfiles_default_max_upload_mb')" />
                </div>

                <x-ui.textarea wire:model="appfiles_allowed_extensions" name="appfiles_allowed_extensions" :label="__('Allowed extensions')" :error="$errors->first('appfiles_allowed_extensions')">{{ $appfiles_allowed_extensions }}</x-ui.textarea>

                <div class="rounded-[1rem] border px-4 py-4 text-sm" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-border-color-rgb), 0.03); color: var(--theme-muted-text-color);">
                    {{ __('Use a comma-separated list such as: jpg, jpeg, png, pdf, docx, xlsx, zip') }}
                </div>
            </x-theme.section-card>

            <x-theme.section-card
                :title="__('S3-Compatible Providers')"
                :description="__('Configure the credentials and endpoint details used when the shared storage driver is Amazon S3, Wasabi, or Contabo.')"
                body-class="space-y-5 p-6"
            >
                <div class="grid gap-6">
                    <x-theme.section-card :title="__('Amazon S3')" body-class="space-y-4 p-5">
                        <x-ui.input wire:model="appfiles_amazon_access_key_id" name="appfiles_amazon_access_key_id" :label="__('Access Key ID')" :error="$errors->first('appfiles_amazon_access_key_id')" />
                        <x-ui.input wire:model="appfiles_amazon_secret_access_key" name="appfiles_amazon_secret_access_key" type="password" :label="__('Secret Access Key')" :error="$errors->first('appfiles_amazon_secret_access_key')" />
                        <x-ui.input wire:model="appfiles_amazon_default_region" name="appfiles_amazon_default_region" :label="__('Region')" :error="$errors->first('appfiles_amazon_default_region')" />
                        <x-ui.input wire:model="appfiles_amazon_bucket" name="appfiles_amazon_bucket" :label="__('Bucket')" :error="$errors->first('appfiles_amazon_bucket')" />
                        <x-ui.input wire:model="appfiles_amazon_endpoint" name="appfiles_amazon_endpoint" :label="__('Endpoint')" :error="$errors->first('appfiles_amazon_endpoint')" />
                        <x-ui.input wire:model="appfiles_amazon_url" name="appfiles_amazon_url" :label="__('Public URL')" :error="$errors->first('appfiles_amazon_url')" />
                        <x-ui.radio-group name="appfiles_amazon_use_path_style_endpoint" wire:model.live="appfiles_amazon_use_path_style_endpoint" :label="__('Path-style endpoint')" :options="$toggleOptions" :value="$appfiles_amazon_use_path_style_endpoint" />
                    </x-theme.section-card>

                    <x-theme.section-card :title="__('Wasabi')" body-class="space-y-4 p-5">
                        <x-ui.input wire:model="appfiles_wasabi_access_key_id" name="appfiles_wasabi_access_key_id" :label="__('Access Key ID')" :error="$errors->first('appfiles_wasabi_access_key_id')" />
                        <x-ui.input wire:model="appfiles_wasabi_secret_access_key" name="appfiles_wasabi_secret_access_key" type="password" :label="__('Secret Access Key')" :error="$errors->first('appfiles_wasabi_secret_access_key')" />
                        <x-ui.input wire:model="appfiles_wasabi_default_region" name="appfiles_wasabi_default_region" :label="__('Region')" :error="$errors->first('appfiles_wasabi_default_region')" />
                        <x-ui.input wire:model="appfiles_wasabi_bucket" name="appfiles_wasabi_bucket" :label="__('Bucket')" :error="$errors->first('appfiles_wasabi_bucket')" />
                        <x-ui.input wire:model="appfiles_wasabi_endpoint" name="appfiles_wasabi_endpoint" :label="__('Endpoint')" :error="$errors->first('appfiles_wasabi_endpoint')" />
                        <x-ui.input wire:model="appfiles_wasabi_url" name="appfiles_wasabi_url" :label="__('Public URL')" :error="$errors->first('appfiles_wasabi_url')" />
                        <x-ui.radio-group name="appfiles_wasabi_use_path_style_endpoint" wire:model.live="appfiles_wasabi_use_path_style_endpoint" :label="__('Path-style endpoint')" :options="$toggleOptions" :value="$appfiles_wasabi_use_path_style_endpoint" />
                    </x-theme.section-card>

                    <x-theme.section-card :title="__('Contabo')" body-class="space-y-4 p-5">
                        <x-ui.input wire:model="appfiles_contabo_access_key_id" name="appfiles_contabo_access_key_id" :label="__('Access Key ID')" :error="$errors->first('appfiles_contabo_access_key_id')" />
                        <x-ui.input wire:model="appfiles_contabo_secret_access_key" name="appfiles_contabo_secret_access_key" type="password" :label="__('Secret Access Key')" :error="$errors->first('appfiles_contabo_secret_access_key')" />
                        <x-ui.input wire:model="appfiles_contabo_default_region" name="appfiles_contabo_default_region" :label="__('Region')" :error="$errors->first('appfiles_contabo_default_region')" />
                        <x-ui.input wire:model="appfiles_contabo_bucket" name="appfiles_contabo_bucket" :label="__('Bucket')" :error="$errors->first('appfiles_contabo_bucket')" />
                        <x-ui.input wire:model="appfiles_contabo_endpoint" name="appfiles_contabo_endpoint" :label="__('Endpoint')" :error="$errors->first('appfiles_contabo_endpoint')" />
                        <x-ui.input wire:model="appfiles_contabo_url" name="appfiles_contabo_url" :label="__('Public URL')" :error="$errors->first('appfiles_contabo_url')" />
                        <x-ui.radio-group name="appfiles_contabo_use_path_style_endpoint" wire:model.live="appfiles_contabo_use_path_style_endpoint" :label="__('Path-style endpoint')" :options="$toggleOptions" :value="$appfiles_contabo_use_path_style_endpoint" />
                    </x-theme.section-card>
                </div>
            </x-theme.section-card>

            <x-theme.section-card
                :title="__('Action menu integrations')"
                :description="__('Choose which file actions appear in the Files workspace dropdown.')"
                body-class="space-y-5 p-6"
            >
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="rounded-[1rem] border p-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-border-color-rgb), 0.03);">
                        <x-ui.radio-group name="appfiles_upload_from_url" wire:model.live="appfiles_upload_from_url" :label="__('Upload From URL')" :options="$toggleOptions" :value="$appfiles_upload_from_url" />
                    </div>

                    <div class="rounded-[1rem] border p-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-border-color-rgb), 0.03);">
                        <x-ui.radio-group name="appfiles_quick_delete_action" wire:model.live="appfiles_quick_delete_action" :label="__('Quick delete action')" :options="$toggleOptions" :value="$appfiles_quick_delete_action" />
                    </div>
                </div>
            </x-theme.section-card>

            <x-theme.section-card
                :title="__('Provider connections')"
                :description="__('Configure external picker and creative provider credentials used by the file workspace.')"
                body-class="space-y-5 p-6"
            >
                <div class="grid gap-6 md:grid-cols-2">
                    <x-theme.section-card
                        :title="__('Google Drive')"
                        :description="__('Configure Google Picker for selecting and importing Drive files.')"
                        body-class="space-y-4 p-5"
                    >
                        <x-ui.radio-group name="appfiles_google_drive" wire:model.live="appfiles_google_drive" :options="$toggleOptions" :value="$appfiles_google_drive" />
                        <div class="grid gap-4">
                            <x-ui.input wire:model="appfiles_google_drive_client_id" name="appfiles_google_drive_client_id" :label="__('Client ID')" :value="$appfiles_google_drive_client_id" :error="$errors->first('appfiles_google_drive_client_id')" />
                            <x-ui.input wire:model="appfiles_google_drive_client_secret" name="appfiles_google_drive_client_secret" type="password" :label="__('Browser API Key')" :value="$appfiles_google_drive_client_secret" :error="$errors->first('appfiles_google_drive_client_secret')" />
                        </div>
                        <p class="text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Google Picker needs an OAuth Client ID and a Browser API key. Native Docs, Sheets, and Slides are not imported yet.') }}</p>
                    </x-theme.section-card>

                    <x-theme.section-card
                        :title="__('Dropbox')"
                        :description="__('Configure Dropbox Chooser for direct file selection from Dropbox.')"
                        body-class="space-y-4 p-5"
                    >
                        <x-ui.radio-group name="appfiles_dropbox" wire:model.live="appfiles_dropbox" :options="$toggleOptions" :value="$appfiles_dropbox" />
                        <div class="grid gap-4">
                            <x-ui.input wire:model="appfiles_dropbox_app_key" name="appfiles_dropbox_app_key" :label="__('App Key')" :value="$appfiles_dropbox_app_key" :error="$errors->first('appfiles_dropbox_app_key')" />
                            <x-ui.input wire:model="appfiles_dropbox_app_secret" name="appfiles_dropbox_app_secret" type="password" :label="__('App Secret')" :value="$appfiles_dropbox_app_secret" :error="$errors->first('appfiles_dropbox_app_secret')" />
                        </div>
                    </x-theme.section-card>

                    <x-theme.section-card
                        :title="__('OneDrive')"
                        :description="__('Configure the legacy OneDrive picker used to download and import files.')"
                        body-class="space-y-4 p-5"
                    >
                        <x-ui.radio-group name="appfiles_onedrive" wire:model.live="appfiles_onedrive" :options="$toggleOptions" :value="$appfiles_onedrive" />
                        <div class="grid gap-4">
                            <x-ui.input wire:model="appfiles_onedrive_client_id" name="appfiles_onedrive_client_id" :label="__('API Key')" :value="$appfiles_onedrive_client_id" :error="$errors->first('appfiles_onedrive_client_id')" />
                        </div>
                        <p class="text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('OneDrive Picker uses the app API key/client id only. Client Secret and Tenant ID are not required for this legacy picker flow.') }}</p>
                    </x-theme.section-card>

                    <x-theme.section-card
                        :title="__('Create for Adobe Express')"
                        :description="__('Configure Adobe Express connection settings used by future creative actions.')"
                        body-class="space-y-4 p-5"
                    >
                        <x-ui.radio-group name="appfiles_adobe_express" wire:model.live="appfiles_adobe_express" :options="$toggleOptions" :value="$appfiles_adobe_express" />
                        <div class="grid gap-4">
                            <x-ui.input wire:model="appfiles_adobe_express_api_key" name="appfiles_adobe_express_api_key" :label="__('API Key')" :value="$appfiles_adobe_express_api_key" :error="$errors->first('appfiles_adobe_express_api_key')" />
                            <x-ui.input wire:model="appfiles_adobe_express_app_name" name="appfiles_adobe_express_app_name" :label="__('App Name')" :value="$appfiles_adobe_express_app_name" :error="$errors->first('appfiles_adobe_express_app_name')" />
                        </div>
                        <p class="text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Adobe Express SDK uses the public API Key and App Name. The second field is used as App Name for the embedded editor launcher.') }}</p>
                    </x-theme.section-card>
                </div>
            </x-theme.section-card>

            <x-theme.section-card
                :title="__('Search Media Online')"
                :description="__('Configure the old stock media providers for searching online images and videos directly in the picker.')"
                body-class="space-y-5 p-6"
            >
                <div class="grid gap-6 md:grid-cols-3">
                    <x-theme.section-card :title="__('Unsplash')" body-class="space-y-4 p-5">
                        <x-ui.radio-group name="file_unsplash_status" wire:model.live="file_unsplash_status" :options="$toggleOptions" :value="$file_unsplash_status" />
                        <x-ui.input wire:model="file_unsplash_access_key" name="file_unsplash_access_key" :label="__('Access Key')" :value="$file_unsplash_access_key" :error="$errors->first('file_unsplash_access_key')" />
                    </x-theme.section-card>

                    <x-theme.section-card :title="__('Pexels')" body-class="space-y-4 p-5">
                        <x-ui.radio-group name="file_pexels_status" wire:model.live="file_pexels_status" :options="$toggleOptions" :value="$file_pexels_status" />
                        <x-ui.input wire:model="file_pexels_api_key" name="file_pexels_api_key" :label="__('API Key')" :value="$file_pexels_api_key" :error="$errors->first('file_pexels_api_key')" />
                    </x-theme.section-card>

                    <x-theme.section-card :title="__('Pixabay')" body-class="space-y-4 p-5">
                        <x-ui.radio-group name="file_pixabay_status" wire:model.live="file_pixabay_status" :options="$toggleOptions" :value="$file_pixabay_status" />
                        <x-ui.input wire:model="file_pixabay_api_key" name="file_pixabay_api_key" :label="__('API Key')" :value="$file_pixabay_api_key" :error="$errors->first('file_pixabay_api_key')" />
                    </x-theme.section-card>
                </div>
            </x-theme.section-card>

            <div class="flex justify-end">
                <x-ui.button type="submit">
                    {{ __('Save settings') }}
                </x-ui.button>
            </div>
        </form>
    </x-settings.layout>
</section>
