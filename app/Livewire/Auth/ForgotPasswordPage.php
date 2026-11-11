<?php

namespace App\Livewire\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Forgot password')]
class ForgotPasswordPage extends Component
{
    public string $email = '';

    public string $captchaToken = '';

    public function sendResetLink()
    {
        $validated = $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        if (function_exists('captcha_enabled') && captcha_enabled()) {
            if (! captcha_verify_token(
                token: $this->captchaToken,
                host: request()->getHost(),
                ip: request()->ip(),
            )) {
                $this->addError('captchaToken', captcha_error_message());
                $this->captchaToken = '';
                $this->dispatch('captcha-reset');

                return null;
            }
        }

        $email = config('fortify.lowercase_usernames')
            ? Str::lower($validated['email'])
            : $validated['email'];

        $status = Password::broker(config('fortify.passwords'))->sendResetLink([
            \Laravel\Fortify\Fortify::email() => $email,
        ]);

        $this->captchaToken = '';
        $this->dispatch('captcha-reset');

        if ($status !== Password::RESET_LINK_SENT) {
            $this->addError('email', __($status));

            return null;
        }

        session()->flash('status', __($status));

        return null;
    }

    public function render(): View
    {
        return view(theme_view('livewire.auth.forgot-password-page', 'guest'));
    }
}
