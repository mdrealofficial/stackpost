<?php

namespace Modules\AppFiles\Livewire;

use App\Support\Storage\StorageDriverManager;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\AdminSettings\Support\OptionStore;

#[Title('File Manager Settings')]
class Settings extends Component
{
    protected OptionStore $options;

    protected StorageDriverManager $storageDriverManager;

    public string $appfiles_disk = 'public';

    public string $appfiles_default_max_upload_mb = '20';

    public string $appfiles_allowed_extensions = '';

    public string $appfiles_upload_from_url = '1';

    public string $appfiles_google_drive = '0';

    public string $appfiles_google_drive_client_id = '';

    public string $appfiles_google_drive_client_secret = '';

    public string $appfiles_dropbox = '0';

    public string $appfiles_dropbox_app_key = '';

    public string $appfiles_dropbox_app_secret = '';

    public string $appfiles_onedrive = '0';

    public string $appfiles_onedrive_client_id = '';

    public string $appfiles_onedrive_client_secret = '';

    public string $appfiles_onedrive_tenant_id = '';

    public string $appfiles_adobe_express = '0';

    public string $appfiles_adobe_express_api_key = '';

    public string $appfiles_adobe_express_app_name = '';

    public string $appfiles_quick_delete_action = '1';

    public string $file_unsplash_status = '0';

    public string $file_unsplash_access_key = '';

    public string $file_pexels_status = '0';

    public string $file_pexels_api_key = '';

    public string $file_pixabay_status = '0';

    public string $file_pixabay_api_key = '';

    public string $appfiles_amazon_access_key_id = '';

    public string $appfiles_amazon_secret_access_key = '';

    public string $appfiles_amazon_default_region = '';

    public string $appfiles_amazon_bucket = '';

    public string $appfiles_amazon_endpoint = '';

    public string $appfiles_amazon_url = '';

    public string $appfiles_amazon_use_path_style_endpoint = '0';

    public string $appfiles_wasabi_access_key_id = '';

    public string $appfiles_wasabi_secret_access_key = '';

    public string $appfiles_wasabi_default_region = '';

    public string $appfiles_wasabi_bucket = '';

    public string $appfiles_wasabi_endpoint = '';

    public string $appfiles_wasabi_url = '';

    public string $appfiles_wasabi_use_path_style_endpoint = '0';

    public string $appfiles_contabo_access_key_id = '';

    public string $appfiles_contabo_secret_access_key = '';

    public string $appfiles_contabo_default_region = '';

    public string $appfiles_contabo_bucket = '';

    public string $appfiles_contabo_endpoint = '';

    public string $appfiles_contabo_url = '';

    public string $appfiles_contabo_use_path_style_endpoint = '1';

    public function boot(OptionStore $options, StorageDriverManager $storageDriverManager): void
    {
        $this->options = $options;
        $this->storageDriverManager = $storageDriverManager;
    }

    public function mount(): void
    {
        foreach ($this->defaults() as $key => $value) {
            $this->{$key} = (string) $this->options->get($key, $value);
        }

        if ($this->appfiles_adobe_express_api_key === '') {
            $this->appfiles_adobe_express_api_key = (string) $this->options->get('appfiles_adobe_express_client_id', '');
        }

        if ($this->appfiles_adobe_express_app_name === '') {
            $this->appfiles_adobe_express_app_name = (string) $this->options->get('appfiles_adobe_express_client_secret', '');
        }
    }

    public function save(): void
    {
        $validated = $this->validate([
            'appfiles_disk' => ['required', 'in:public,amazon,wasabi,contabo'],
            'appfiles_default_max_upload_mb' => ['required', 'integer', 'min:1', 'max:1024'],
            'appfiles_allowed_extensions' => ['required', 'string', 'max:2000'],
            'appfiles_upload_from_url' => ['required', 'in:0,1'],
            'appfiles_google_drive' => ['required', 'in:0,1'],
            'appfiles_google_drive_client_id' => ['nullable', 'string', 'max:255'],
            'appfiles_google_drive_client_secret' => ['nullable', 'string', 'max:255'],
            'appfiles_dropbox' => ['required', 'in:0,1'],
            'appfiles_dropbox_app_key' => ['nullable', 'string', 'max:255'],
            'appfiles_dropbox_app_secret' => ['nullable', 'string', 'max:255'],
            'appfiles_onedrive' => ['required', 'in:0,1'],
            'appfiles_onedrive_client_id' => ['nullable', 'string', 'max:255'],
            'appfiles_onedrive_client_secret' => ['nullable', 'string', 'max:255'],
            'appfiles_onedrive_tenant_id' => ['nullable', 'string', 'max:255'],
            'appfiles_adobe_express' => ['required', 'in:0,1'],
            'appfiles_adobe_express_api_key' => ['nullable', 'string', 'max:255'],
            'appfiles_adobe_express_app_name' => ['nullable', 'string', 'max:255'],
            'appfiles_quick_delete_action' => ['required', 'in:0,1'],
            'file_unsplash_status' => ['required', 'in:0,1'],
            'file_unsplash_access_key' => ['nullable', 'string', 'max:255'],
            'file_pexels_status' => ['required', 'in:0,1'],
            'file_pexels_api_key' => ['nullable', 'string', 'max:255'],
            'file_pixabay_status' => ['required', 'in:0,1'],
            'file_pixabay_api_key' => ['nullable', 'string', 'max:255'],
            'appfiles_amazon_access_key_id' => ['nullable', 'string', 'max:255'],
            'appfiles_amazon_secret_access_key' => ['nullable', 'string', 'max:255'],
            'appfiles_amazon_default_region' => ['nullable', 'string', 'max:100'],
            'appfiles_amazon_bucket' => ['nullable', 'string', 'max:255'],
            'appfiles_amazon_endpoint' => ['nullable', 'string', 'max:255'],
            'appfiles_amazon_url' => ['nullable', 'string', 'max:255'],
            'appfiles_amazon_use_path_style_endpoint' => ['required', 'in:0,1'],
            'appfiles_wasabi_access_key_id' => ['nullable', 'string', 'max:255'],
            'appfiles_wasabi_secret_access_key' => ['nullable', 'string', 'max:255'],
            'appfiles_wasabi_default_region' => ['nullable', 'string', 'max:100'],
            'appfiles_wasabi_bucket' => ['nullable', 'string', 'max:255'],
            'appfiles_wasabi_endpoint' => ['nullable', 'string', 'max:255'],
            'appfiles_wasabi_url' => ['nullable', 'string', 'max:255'],
            'appfiles_wasabi_use_path_style_endpoint' => ['required', 'in:0,1'],
            'appfiles_contabo_access_key_id' => ['nullable', 'string', 'max:255'],
            'appfiles_contabo_secret_access_key' => ['nullable', 'string', 'max:255'],
            'appfiles_contabo_default_region' => ['nullable', 'string', 'max:100'],
            'appfiles_contabo_bucket' => ['nullable', 'string', 'max:255'],
            'appfiles_contabo_endpoint' => ['nullable', 'string', 'max:255'],
            'appfiles_contabo_url' => ['nullable', 'string', 'max:255'],
            'appfiles_contabo_use_path_style_endpoint' => ['required', 'in:0,1'],
        ]);

        foreach ($validated as $key => $value) {
            $this->options->set($key, $value);
        }

        $this->options->set('appfiles_adobe_express_client_id', $validated['appfiles_adobe_express_api_key'] ?? '');
        $this->options->set('appfiles_adobe_express_client_secret', $validated['appfiles_adobe_express_app_name'] ?? '');

        $this->dispatch('settings-saved');
    }

    protected function defaults(): array
    {
        return [
            'appfiles_disk' => (string) config('modules.appfiles.disk', 'public'),
            'appfiles_default_max_upload_mb' => (string) config('modules.appfiles.default_max_upload_mb', 20),
            'appfiles_allowed_extensions' => implode(', ', (array) config('modules.appfiles.allowed_extensions', [])),
            'appfiles_upload_from_url' => config('modules.appfiles.enable_upload_from_url', true) ? '1' : '0',
            'appfiles_google_drive' => config('modules.appfiles.enable_google_drive', false) ? '1' : '0',
            'appfiles_google_drive_client_id' => '',
            'appfiles_google_drive_client_secret' => '',
            'appfiles_dropbox' => config('modules.appfiles.enable_dropbox', false) ? '1' : '0',
            'appfiles_dropbox_app_key' => '',
            'appfiles_dropbox_app_secret' => '',
            'appfiles_onedrive' => config('modules.appfiles.enable_onedrive', false) ? '1' : '0',
            'appfiles_onedrive_client_id' => '',
            'appfiles_onedrive_client_secret' => '',
            'appfiles_onedrive_tenant_id' => '',
            'appfiles_adobe_express' => config('modules.appfiles.enable_adobe_express', false) ? '1' : '0',
            'appfiles_adobe_express_api_key' => '',
            'appfiles_adobe_express_app_name' => '',
            'appfiles_quick_delete_action' => config('modules.appfiles.enable_quick_delete_action', true) ? '1' : '0',
            'file_unsplash_status' => '0',
            'file_unsplash_access_key' => '',
            'file_pexels_status' => '0',
            'file_pexels_api_key' => '',
            'file_pixabay_status' => '0',
            'file_pixabay_api_key' => '',
            'appfiles_amazon_access_key_id' => '',
            'appfiles_amazon_secret_access_key' => '',
            'appfiles_amazon_default_region' => (string) env('AWS_DEFAULT_REGION', ''),
            'appfiles_amazon_bucket' => '',
            'appfiles_amazon_endpoint' => (string) env('AWS_ENDPOINT', ''),
            'appfiles_amazon_url' => (string) env('AWS_URL', ''),
            'appfiles_amazon_use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false) ? '1' : '0',
            'appfiles_wasabi_access_key_id' => '',
            'appfiles_wasabi_secret_access_key' => '',
            'appfiles_wasabi_default_region' => (string) env('WASABI_DEFAULT_REGION', 'us-east-1'),
            'appfiles_wasabi_bucket' => '',
            'appfiles_wasabi_endpoint' => (string) env('WASABI_ENDPOINT', ''),
            'appfiles_wasabi_url' => (string) env('WASABI_URL', ''),
            'appfiles_wasabi_use_path_style_endpoint' => env('WASABI_USE_PATH_STYLE_ENDPOINT', false) ? '1' : '0',
            'appfiles_contabo_access_key_id' => '',
            'appfiles_contabo_secret_access_key' => '',
            'appfiles_contabo_default_region' => (string) env('CONTABO_DEFAULT_REGION', 'eu-central-1'),
            'appfiles_contabo_bucket' => '',
            'appfiles_contabo_endpoint' => (string) env('CONTABO_ENDPOINT', ''),
            'appfiles_contabo_url' => (string) env('CONTABO_URL', ''),
            'appfiles_contabo_use_path_style_endpoint' => env('CONTABO_USE_PATH_STYLE_ENDPOINT', true) ? '1' : '0',
        ];
    }

    protected function toggleOptions(): array
    {
        return [
            ['value' => '1', 'label' => 'Enable'],
            ['value' => '0', 'label' => 'Disable'],
        ];
    }

    public function render(): View
    {
        return view('appfiles::settings', [
            'toggleOptions' => $this->toggleOptions(),
            'storageDiskOptions' => collect($this->storageDriverManager->diskOptions())
                ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
                ->values()
                ->all(),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('File Manager Settings'),
        ]);
    }
}
