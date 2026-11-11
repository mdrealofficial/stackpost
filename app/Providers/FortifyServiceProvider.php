<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;
use Modules\AdminUser\Actions\Fortify\CreateNewUser;
use Modules\AdminUser\Actions\Fortify\ResetUserPassword;
use Modules\AdminUser\Models\User;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureAuthentication();
        $this->configureEmailVerificationUrls();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn () => view(theme_view('auth.login', 'guest')));
        Fortify::verifyEmailView(fn () => view(theme_view('auth.verify-email', 'guest')));
        Fortify::twoFactorChallengeView(fn () => view(theme_view('auth.two-factor-challenge', 'guest')));
        Fortify::confirmPasswordView(fn () => view(theme_view('auth.confirm-password', 'guest')));
        Fortify::registerView(fn () => view(theme_view('auth.register', 'guest')));
        Fortify::resetPasswordView(fn () => view(theme_view('auth.reset-password', 'guest')));
        Fortify::requestPasswordResetLinkView(fn () => view(theme_view('auth.forgot-password', 'guest')));
    }

    /**
     * Configure custom authentication.
     */
    private function configureAuthentication(): void
    {
        Fortify::authenticateUsing(function (Request $request): ?User {
            $login = Str::lower(trim((string) $request->input('login')));

            $user = User::query()
                ->whereRaw('LOWER(email) = ?', [$login])
                ->orWhereRaw('LOWER(username) = ?', [$login])
                ->first();

            if (! $user || ! Hash::check((string) $request->input('password'), $user->password)) {
                return null;
            }

            if (function_exists('captcha_enabled') && captcha_enabled()) {
                if (! $request->attributes->get('captcha_verified', false)) {
                    if (! captcha_verify($request)) {
                        throw ValidationException::withMessages([
                            'captcha_token' => [captcha_error_message()],
                        ]);
                    }

                    $request->attributes->set('captcha_verified', true);
                }
            }

            if ($user->isSuperAdmin() && ! $user->email_verified_at) {
                $user->forceFill([
                    'email_verified_at' => now(),
                ])->save();
            }

            return $user;
        });
    }

    /**
     * Generate verification links with a path-only signature so proxy, scheme,
     * and host differences cannot invalidate customer registration emails.
     */
    private function configureEmailVerificationUrls(): void
    {
        VerifyEmail::createUrlUsing(function (User $user): string {
            $path = URL::temporarySignedRoute(
                'verification.verify',
                Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
                [
                    'id' => $user->getKey(),
                    'hash' => sha1($user->getEmailForVerification()),
                ],
                false
            );

            return rtrim((string) config('app.url'), '/').$path;
        });
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower((string) $request->input('login')).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
