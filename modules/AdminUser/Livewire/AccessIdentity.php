<?php

namespace Modules\AdminUser\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\AdminSettings\Support\OptionStore;

#[Title('Authentication Rules')]
class AccessIdentity extends Component
{
    protected OptionStore $options;

    public string $auth_landing_page_status = '1';
    public string $auth_signup_page_status = '1';
    public string $auth_activation_email_new_user_status = '0';
    public string $auth_welcome_email_new_user_status = '0';
    public string $auth_user_change_email_status = '0';
    public string $auth_user_change_username_status = '0';
    public string $auth_two_factor_authentication_status = '1';

    public string $auth_facebook_login_status = '0';
    public string $auth_facebook_login_app_id = '';
    public string $auth_facebook_login_app_secret = '';
    public string $auth_facebook_login_app_version = 'v22.0';

    public string $auth_google_login_status = '0';
    public string $auth_google_login_client_id = '';
    public string $auth_google_login_client_secret = '';

    public string $auth_x_login_status = '0';
    public string $auth_x_login_client_id = '';
    public string $auth_x_login_client_secret = '';

    public function boot(OptionStore $options): void
    {
        $this->options = $options;
    }

    public function mount(): void
    {
        foreach ($this->stateKeys() as $key => $default) {
            $this->{$key} = (string) $this->options->get($key, $default);
        }
    }

    public function save(): void
    {
        $validated = $this->validate([
            'auth_landing_page_status' => ['required', 'in:0,1'],
            'auth_signup_page_status' => ['required', 'in:0,1'],
            'auth_activation_email_new_user_status' => ['required', 'in:0,1'],
            'auth_welcome_email_new_user_status' => ['required', 'in:0,1'],
            'auth_user_change_email_status' => ['required', 'in:0,1'],
            'auth_user_change_username_status' => ['required', 'in:0,1'],
            'auth_two_factor_authentication_status' => ['required', 'in:0,1'],

            'auth_facebook_login_status' => ['required', 'in:0,1'],
            'auth_facebook_login_app_id' => ['nullable', 'string', 'max:255'],
            'auth_facebook_login_app_secret' => ['nullable', 'string', 'max:255'],
            'auth_facebook_login_app_version' => ['nullable', 'string', 'max:50'],

            'auth_google_login_status' => ['required', 'in:0,1'],
            'auth_google_login_client_id' => ['nullable', 'string', 'max:255'],
            'auth_google_login_client_secret' => ['nullable', 'string', 'max:255'],

            'auth_x_login_status' => ['required', 'in:0,1'],
            'auth_x_login_client_id' => ['nullable', 'string', 'max:255'],
            'auth_x_login_client_secret' => ['nullable', 'string', 'max:255'],
        ]);

        foreach ($validated as $key => $value) {
            $this->options->set($key, $value);
        }

        sync_fortify_features_from_settings();

        $this->dispatch('settings-saved');
    }

    /**
     * @return array<string, string>
     */
    protected function stateKeys(): array
    {
        return [
            'auth_landing_page_status' => '1',
            'auth_signup_page_status' => '1',
            'auth_activation_email_new_user_status' => '0',
            'auth_welcome_email_new_user_status' => '0',
            'auth_user_change_email_status' => '0',
            'auth_user_change_username_status' => '0',
            'auth_two_factor_authentication_status' => '1',
            'auth_facebook_login_status' => '0',
            'auth_facebook_login_app_id' => '',
            'auth_facebook_login_app_secret' => '',
            'auth_facebook_login_app_version' => 'v22.0',
            'auth_google_login_status' => '0',
            'auth_google_login_client_id' => '',
            'auth_google_login_client_secret' => '',
            'auth_x_login_status' => '0',
            'auth_x_login_client_id' => '',
            'auth_x_login_client_secret' => '',
        ];
    }

    /**
     * @return array<int, array{value:string,label:string}>
     */
    protected function toggleOptions(): array
    {
        return [
            ['value' => '1', 'label' => 'Enable'],
            ['value' => '0', 'label' => 'Disable'],
        ];
    }

    public function render(): View
    {
        return view('adminuser::livewire.settings.access-identity', [
            'toggleOptions' => $this->toggleOptions(),
            'facebookCallbackUrl' => url('auth/login/facebook/callback'),
            'googleCallbackUrl' => url('auth/login/google/callback'),
            'xCallbackUrl' => url('auth/login/x/callback'),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Authentication Rules'),
        ]);
    }
}
