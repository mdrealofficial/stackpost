<?php

namespace App\Livewire\Auth;

use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\AdminUser\Models\User;

#[Title('Log in')]
class LoginPage extends Component
{
    public string $identifier = '';

    public string $password = '';

    public bool $remember = false;

    public string $captchaToken = '';

    public function mount(): void
    {
        if (! config('app.demo_mode')) {
            return;
        }

        $this->identifier = 'demo';
        $this->password = '123456789';
    }

    public function login()
    {
        $validated = $this->validate([
            'identifier' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if ($this->tooManyAttempts()) {
            $seconds = RateLimiter::availableIn($this->throttleKey());

                $this->addError('identifier', trans('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => (int) ceil($seconds / 60),
                ]));

            return null;
        }

        $login = Str::lower(trim($validated['identifier']));

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$login])
            ->orWhereRaw('LOWER(username) = ?', [$login])
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            RateLimiter::hit($this->throttleKey(), 60);
            $this->addError('identifier', trans('auth.failed'));

            return null;
        }

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

        $guard = app(StatefulGuard::class);
        $provider = $guard->getProvider();

        if (config('hashing.rehash_on_login', true) && method_exists($provider, 'rehashPasswordIfRequired')) {
            $provider->rehashPasswordIfRequired($user, ['password' => $validated['password']]);
        }

        if ($user->isSuperAdmin() && ! $user->email_verified_at) {
            $user->forceFill([
                'email_verified_at' => now(),
            ])->save();
        }

        RateLimiter::clear($this->throttleKey());

        if ($this->requiresTwoFactorChallenge($user)) {
            $intendedUrl = request()->session()->get(
                'url.intended',
                \Laravel\Fortify\Fortify::redirects('login') ?? config('fortify.home')
            );

            request()->session()->put([
                'login.id' => $user->getKey(),
                'login.remember' => $this->remember,
                'url.intended' => $intendedUrl,
            ]);

            \Laravel\Fortify\Events\TwoFactorAuthenticationChallenged::dispatch($user);

            return redirect()->route('two-factor.login');
        }

        $guard->login($user, $this->remember);
        request()->session()->regenerate();

        if ($redirect = $this->completeIntendedEmailVerification($user)) {
            return $redirect;
        }

        if ($this->intendedUrlIsRawFileRoute()) {
            request()->session()->forget('url.intended');
        }

        return redirect()->intended(\Laravel\Fortify\Fortify::redirects('login') ?? config('fortify.home'));
    }

    protected function intendedUrlIsRawFileRoute(): bool
    {
        $intendedUrl = (string) request()->session()->get('url.intended', '');

        if ($intendedUrl === '') {
            return false;
        }

        $path = parse_url($intendedUrl, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return false;
        }

        $prefix = trim((string) config('modules.appfiles.portal_route_prefix', 'portal/files'), '/');
        $path = '/'.trim($path, '/');

        return preg_match('#/(?:[^/]+/)*'.preg_quote($prefix, '#').'/[^/]+/(?:preview|download)$#', $path) === 1;
    }

    protected function completeIntendedEmailVerification(User $user)
    {
        $intendedUrl = (string) request()->session()->get('url.intended', '');

        if ($intendedUrl === '') {
            return null;
        }

        $parts = parse_url($intendedUrl);

        if (! is_array($parts)) {
            return null;
        }

        $path = '/'.ltrim((string) ($parts['path'] ?? ''), '/');

        if (! preg_match('~^/email/verify/([^/]+)/([^/?#]+)$~', $path, $matches)) {
            return null;
        }

        if ((string) $user->getKey() !== rawurldecode((string) $matches[1])) {
            return null;
        }

        if (! hash_equals(sha1($user->getEmailForVerification()), rawurldecode((string) $matches[2]))) {
            return null;
        }

        $query = (string) ($parts['query'] ?? '');

        if (! $this->hasValidEmailVerificationSignature($path, $query, $parts)) {
            return null;
        }

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        request()->session()->forget('url.intended');

        return redirect()->to(\Laravel\Fortify\Fortify::redirects('login') ?? config('fortify.home'));
    }

    protected function hasValidEmailVerificationSignature(string $path, string $query, array $parts): bool
    {
        if ($query === '' || ! str_contains($query, 'signature=')) {
            return false;
        }

        $relativeUrl = $path.'?'.$query;

        if (URL::hasValidSignature(HttpRequest::create($relativeUrl), false)) {
            return true;
        }

        foreach ($this->emailVerificationUrlCandidates($relativeUrl, $parts) as $candidate) {
            if (URL::hasValidSignature(HttpRequest::create($candidate), true)) {
                return true;
            }
        }

        return false;
    }

    protected function emailVerificationUrlCandidates(string $relativeUrl, array $parts): array
    {
        $candidates = [];

        if (isset($parts['scheme'], $parts['host'])) {
            $root = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
            $candidates[] = $root.$relativeUrl;
            $candidates[] = ($parts['scheme'] === 'https' ? 'http' : 'https').'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '').$relativeUrl;
        }

        $appUrl = rtrim((string) config('app.url'), '/');

        if ($appUrl !== '') {
            $candidates[] = $appUrl.$relativeUrl;

            if (Str::startsWith($appUrl, 'https://')) {
                $candidates[] = 'http://'.Str::after($appUrl, 'https://').$relativeUrl;
            } elseif (Str::startsWith($appUrl, 'http://')) {
                $candidates[] = 'https://'.Str::after($appUrl, 'http://').$relativeUrl;
            }
        }

        return array_values(array_unique($candidates));
    }

    protected function requiresTwoFactorChallenge(User $user): bool
    {
        if (! $user->two_factor_secret) {
            return false;
        }

        if (! in_array(\Laravel\Fortify\TwoFactorAuthenticatable::class, class_uses_recursive($user))) {
            return false;
        }

        if (! \Laravel\Fortify\Fortify::confirmsTwoFactorAuthentication()) {
            return true;
        }

        return ! is_null($user->two_factor_confirmed_at);
    }

    protected function tooManyAttempts(): bool
    {
        return RateLimiter::tooManyAttempts($this->throttleKey(), 5);
    }

    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->identifier).'|'.request()->ip());
    }

    public function render(): View
    {
        return view(theme_view('livewire.auth.login-page', 'guest'));
    }
}
