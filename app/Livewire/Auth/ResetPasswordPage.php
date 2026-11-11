<?php

namespace App\Livewire\Auth;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\AdminUser\Actions\Fortify\ResetUserPassword;

#[Title('Reset password')]
class ResetPasswordPage extends Component
{
    use PasswordValidationRules;

    public string $token = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $captchaToken = '';

    public function mount(string $token = '', string $email = ''): void
    {
        $this->token = $token;
        $this->email = $email;
    }

    public function resetPassword()
    {
        $validated = $this->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => $this->passwordRules(),
            'password_confirmation' => ['required', 'string'],
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

        $status = Password::broker(config('fortify.passwords'))->reset(
            $validated,
            function ($user) use ($validated): void {
                app(ResetUserPassword::class)->reset($user, $validated);
                app(\Laravel\Fortify\Actions\CompletePasswordReset::class)(
                    app(StatefulGuard::class),
                    $user,
                );
            }
        );

        $this->captchaToken = '';
        $this->dispatch('captcha-reset');

        if ($status !== Password::PASSWORD_RESET) {
            $this->addError('email', __($status));

            return null;
        }

        return redirect()->route('login')->with('status', __($status));
    }

    public function render(): View
    {
        return view(theme_view('livewire.auth.reset-password-page', 'guest'));
    }
}
