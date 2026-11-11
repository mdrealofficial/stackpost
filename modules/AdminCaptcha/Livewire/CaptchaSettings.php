<?php

namespace Modules\AdminCaptcha\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\AdminSettings\Support\OptionStore;

#[Title('Captcha Settings')]
class CaptchaSettings extends Component
{
    protected OptionStore $options;

    public string $captcha_type = 'disable';

    public string $auth_google_recaptcha_status = '1';
    public string $auth_google_recaptcha_site_key = '';
    public string $auth_google_recaptcha_secret_key = '';

    public string $auth_cloudflare_turnstile_status = '1';
    public string $auth_cloudflare_turnstile_site_key = '';
    public string $auth_cloudflare_turnstile_secret_key = '';

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
            'captcha_type' => ['required', 'in:disable,recaptcha,turnstile'],
            'auth_google_recaptcha_status' => ['required', 'in:0,1'],
            'auth_google_recaptcha_site_key' => ['nullable', 'string', 'max:255'],
            'auth_google_recaptcha_secret_key' => ['nullable', 'string', 'max:255'],
            'auth_cloudflare_turnstile_status' => ['required', 'in:0,1'],
            'auth_cloudflare_turnstile_site_key' => ['nullable', 'string', 'max:255'],
            'auth_cloudflare_turnstile_secret_key' => ['nullable', 'string', 'max:255'],
        ]);

        foreach ($validated as $key => $value) {
            $this->options->set($key, $value);
        }

        $this->dispatch('settings-saved');
    }

    /**
     * @return array<string, string>
     */
    protected function stateKeys(): array
    {
        return [
            'captcha_type' => 'disable',
            'auth_google_recaptcha_status' => '1',
            'auth_google_recaptcha_site_key' => '',
            'auth_google_recaptcha_secret_key' => '',
            'auth_cloudflare_turnstile_status' => '1',
            'auth_cloudflare_turnstile_site_key' => '',
            'auth_cloudflare_turnstile_secret_key' => '',
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

    /**
     * @return array<int, array{value:string,label:string}>
     */
    protected function captchaTypeOptions(): array
    {
        return [
            ['value' => 'disable', 'label' => 'Disable'],
            ['value' => 'recaptcha', 'label' => 'Google reCaptcha V2'],
            ['value' => 'turnstile', 'label' => 'Cloudflare Turnstile'],
        ];
    }

    public function render(): View
    {
        return view('admincaptcha::index', [
            'toggleOptions' => $this->toggleOptions(),
            'captchaTypeOptions' => $this->captchaTypeOptions(),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Captcha Settings'),
        ]);
    }
}
