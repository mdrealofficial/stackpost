<?php

namespace App\Livewire\Auth;

use App\Notifications\WelcomeNewUserNotification;
use App\Concerns\PasswordValidationRules;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\AdminUser\Actions\Fortify\CreateNewUser;
use Modules\AdminUser\Concerns\ProfileValidationRules;

#[Title('Register')]
class RegisterPage extends Component
{
    use PasswordValidationRules;
    use ProfileValidationRules;

    public string $name = '';

    public string $email = '';

    public string $username = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $timezone = '';

    public bool $accept_terms = false;

    public string $captchaToken = '';

    public function mount(): void
    {
        abort_unless(auth_signup_enabled(), 404);

        $this->timezone = (string) config('app.timezone', 'UTC');
    }

    public function register(CreateNewUser $creator)
    {
        $validated = $this->validate($this->rules(), [], [
            'accept_terms' => __('terms and conditions'),
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

        $user = $creator->create([
            ...$validated,
            'username' => strtolower($validated['username']),
            'email' => strtolower($validated['email']),
            'accept_terms' => $validated['accept_terms'] ? '1' : '0',
        ]);

        if (! auth_activation_email_enabled()) {
            $user->forceFill([
                'email_verified_at' => now(),
            ])->save();
        }

        event(new Registered($user));

        if (auth_welcome_email_enabled()) {
            $user->notify(new WelcomeNewUserNotification);
        }

        Auth::guard(config('fortify.guard'))->login($user);
        request()->session()->regenerate();

        return redirect()->intended(\Laravel\Fortify\Fortify::redirects('register') ?? config('fortify.home'));
    }

    protected function rules(): array
    {
        return [
            ...$this->profileRules(),
            'timezone' => ['required', 'string', 'timezone:all', Rule::in(timezone_options())],
            'accept_terms' => ['accepted'],
            'password' => $this->passwordRules(),
            'password_confirmation' => ['required', 'string'],
        ];
    }

    public function render(): View
    {
        return view(theme_view('livewire.auth.register-page', 'guest'));
    }
}
