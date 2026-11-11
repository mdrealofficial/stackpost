<?php

namespace App\Http\Controllers\Auth;

use App\Notifications\WelcomeNewUserNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\AdminUser\Actions\Fortify\CreateNewUser;
use Modules\AdminUser\Models\User;

class SocialLoginController extends Controller
{
    public function redirect(Request $request, string $provider): RedirectResponse
    {
        $provider = $this->normalizeProvider($provider);
        $config = $this->providerConfig($provider);

        if (! $config['enabled']) {
            abort(404);
        }

        $state = Str::random(40);
        $context = [
            'provider' => $provider,
            'state' => $state,
        ];

        if ($provider === 'x') {
            $pkce = $this->generatePkcePair();
            $context['pkce_verifier'] = $pkce['verifier'];
            $url = $this->buildXAuthorizationUrl($config, $state, $pkce['challenge']);
        } elseif ($provider === 'google') {
            $url = $this->buildGoogleAuthorizationUrl($config, $state);
        } else {
            $url = $this->buildFacebookAuthorizationUrl($config, $state);
        }

        $request->session()->put('guest_social_oauth', $context);

        return redirect()->away($url);
    }

    public function callback(Request $request, string $provider, CreateNewUser $creator): RedirectResponse
    {
        $provider = $this->normalizeProvider($provider);
        $config = $this->providerConfig($provider);
        $context = (array) $request->session()->pull('guest_social_oauth', []);

        if (! $config['enabled']) {
            return $this->failedLogin(__('This social login provider is disabled.'));
        }

        if (($context['provider'] ?? null) !== $provider || ($context['state'] ?? null) !== $request->string('state')->toString()) {
            return $this->failedLogin(__('The social login session is invalid or has expired.'));
        }

        if ($request->filled('error')) {
            return $this->failedLogin($request->string('error_description')->toString() ?: __('Social authorization was cancelled.'));
        }

        $code = $request->string('code')->toString();
        if ($code === '') {
            return $this->failedLogin(__('Authorization code was not returned by the provider.'));
        }

        try {
            $profile = match ($provider) {
                'google' => $this->googleProfile($config, $code),
                'facebook' => $this->facebookProfile($config, $code),
                default => $this->xProfile($config, $code, (string) ($context['pkce_verifier'] ?? '')),
            };
        } catch (\Throwable $e) {
            return $this->failedLogin(__('Social login failed: :message', ['message' => $e->getMessage()]));
        }

        try {
            $user = $this->resolveOrCreateUser($profile, $provider, $creator);
        } catch (\Throwable $e) {
            return $this->failedLogin($e->getMessage());
        }

        $guard = app(StatefulGuard::class);
        $guard->login($user, true);
        $request->session()->regenerate();

        return redirect()->to(\Laravel\Fortify\Fortify::redirects('login') ?? config('fortify.home'));
    }

    protected function normalizeProvider(string $provider): string
    {
        $provider = Str::lower(trim($provider));

        if (! in_array($provider, ['google', 'facebook', 'x'], true)) {
            abort(404);
        }

        return $provider;
    }

    protected function providerConfig(string $provider): array
    {
        return match ($provider) {
            'google' => [
                'enabled' => (string) get_option('auth_google_login_status', '0') === '1',
                'client_id' => (string) get_option('auth_google_login_client_id', ''),
                'client_secret' => (string) get_option('auth_google_login_client_secret', ''),
                'callback' => url('auth/login/google/callback'),
            ],
            'facebook' => [
                'enabled' => (string) get_option('auth_facebook_login_status', '0') === '1',
                'client_id' => (string) get_option('auth_facebook_login_app_id', ''),
                'client_secret' => (string) get_option('auth_facebook_login_app_secret', ''),
                'graph_version' => (string) (get_option('auth_facebook_login_app_version', 'v22.0') ?: 'v22.0'),
                'callback' => url('auth/login/facebook/callback'),
            ],
            default => [
                'enabled' => (string) get_option('auth_x_login_status', '0') === '1',
                'client_id' => (string) get_option('auth_x_login_client_id', ''),
                'client_secret' => (string) get_option('auth_x_login_client_secret', ''),
                'callback' => url('auth/login/x/callback'),
            ],
        };
    }

    protected function buildGoogleAuthorizationUrl(array $config, string $state): string
    {
        $query = http_build_query([
            'client_id' => $config['client_id'],
            'redirect_uri' => $config['callback'],
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'prompt' => 'select_account',
        ]);

        return 'https://accounts.google.com/o/oauth2/v2/auth?'.$query;
    }

    protected function buildFacebookAuthorizationUrl(array $config, string $state): string
    {
        $query = http_build_query([
            'client_id' => $config['client_id'],
            'redirect_uri' => $config['callback'],
            'response_type' => 'code',
            'scope' => 'email,public_profile',
            'state' => $state,
        ]);

        return 'https://www.facebook.com/'.$config['graph_version'].'/dialog/oauth?'.$query;
    }

    protected function buildXAuthorizationUrl(array $config, string $state, string $codeChallenge): string
    {
        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $config['client_id'],
            'redirect_uri' => $config['callback'],
            'scope' => 'tweet.read users.read users.email',
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);

        return 'https://x.com/i/oauth2/authorize?'.$query;
    }

    protected function googleProfile(array $config, string $code): array
    {
        $tokenResponse = Http::timeout(30)->acceptJson()->asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $config['callback'],
        ]);

        $tokenPayload = (array) $tokenResponse->json();
        $accessToken = (string) data_get($tokenPayload, 'access_token', '');

        if (! $tokenResponse->successful() || $accessToken === '') {
            throw new \RuntimeException(__('Google token exchange failed.'));
        }

        $profileResponse = Http::timeout(30)
            ->acceptJson()
            ->withToken($accessToken)
            ->get('https://openidconnect.googleapis.com/v1/userinfo');

        $profile = (array) $profileResponse->json();

        if (! $profileResponse->successful() || blank($profile['sub'] ?? null)) {
            throw new \RuntimeException(__('Could not load Google profile data.'));
        }

        return [
            'id' => (string) ($profile['sub'] ?? ''),
            'name' => (string) ($profile['name'] ?? ''),
            'username' => (string) Str::slug((string) ($profile['name'] ?? ''), '-'),
            'email' => (string) ($profile['email'] ?? ''),
        ];
    }

    protected function facebookProfile(array $config, string $code): array
    {
        $tokenResponse = Http::timeout(30)->acceptJson()->get('https://graph.facebook.com/'.$config['graph_version'].'/oauth/access_token', [
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'redirect_uri' => $config['callback'],
            'code' => $code,
        ]);

        $tokenPayload = (array) $tokenResponse->json();
        $accessToken = (string) data_get($tokenPayload, 'access_token', '');

        if (! $tokenResponse->successful() || $accessToken === '') {
            throw new \RuntimeException(__('Facebook token exchange failed.'));
        }

        $profileResponse = Http::timeout(30)->acceptJson()->get('https://graph.facebook.com/'.$config['graph_version'].'/me', [
            'fields' => 'id,name,email',
            'access_token' => $accessToken,
        ]);

        $profile = (array) $profileResponse->json();

        if (! $profileResponse->successful() || blank($profile['id'] ?? null)) {
            throw new \RuntimeException(__('Could not load Facebook profile data.'));
        }

        return [
            'id' => (string) ($profile['id'] ?? ''),
            'name' => (string) ($profile['name'] ?? ''),
            'username' => (string) Str::slug((string) ($profile['name'] ?? ''), '-'),
            'email' => (string) ($profile['email'] ?? ''),
        ];
    }

    protected function xProfile(array $config, string $code, string $codeVerifier): array
    {
        if ($codeVerifier === '') {
            throw new \RuntimeException(__('X PKCE verifier is missing.'));
        }

        $tokenResponse = Http::timeout(30)
            ->acceptJson()
            ->asForm()
            ->withHeaders([
                'Authorization' => 'Basic '.base64_encode($config['client_id'].':'.$config['client_secret']),
            ])
            ->post('https://api.x.com/2/oauth2/token', [
                'code' => $code,
                'grant_type' => 'authorization_code',
                'client_id' => $config['client_id'],
                'redirect_uri' => $config['callback'],
                'code_verifier' => $codeVerifier,
            ]);

        $tokenPayload = (array) $tokenResponse->json();
        $accessToken = (string) data_get($tokenPayload, 'access_token', '');

        if (! $tokenResponse->successful() || $accessToken === '') {
            throw new \RuntimeException(__('X token exchange failed.'));
        }

        $profileResponse = Http::timeout(30)
            ->acceptJson()
            ->withToken($accessToken)
            ->get('https://api.x.com/2/users/me', [
                'user.fields' => 'id,name,username',
            ]);

        $payload = (array) $profileResponse->json();
        $profile = (array) data_get($payload, 'data', []);

        if (! $profileResponse->successful() || blank($profile['id'] ?? null)) {
            throw new \RuntimeException(__('Could not load X profile data.'));
        }

        return [
            'id' => (string) ($profile['id'] ?? ''),
            'name' => (string) ($profile['name'] ?? ''),
            'username' => (string) ($profile['username'] ?? ''),
            'email' => '',
        ];
    }

    protected function resolveOrCreateUser(array $profile, string $provider, CreateNewUser $creator): User
    {
        $providerId = trim((string) ($profile['id'] ?? ''));
        $email = Str::lower(trim((string) ($profile['email'] ?? '')));
        $name = trim((string) ($profile['name'] ?? ''));
        $preferredUsername = Str::lower(trim((string) ($profile['username'] ?? '')));

        if ($email === '') {
            if ($providerId === '') {
                throw new \RuntimeException(__('Could not resolve account identity from :provider.', ['provider' => Str::title($provider)]));
            }

            $email = "{$provider}_{$providerId}@oauth.invalid";
        }

        $existingUser = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($existingUser) {
            return $existingUser;
        }

        if (! auth_signup_enabled()) {
            throw new \RuntimeException(__('New account registration is disabled.'));
        }

        if ($name === '') {
            $name = Str::title(str_replace(['-', '_'], ' ', $preferredUsername));
        }

        if ($name === '') {
            $name = Str::title($provider).' User';
        }

        $usernameSeed = $preferredUsername !== ''
            ? $preferredUsername
            : Str::before($email, '@');

        $username = $this->uniqueUsername($usernameSeed !== '' ? $usernameSeed : $provider.'-user');
        $password = 'P@'.Str::random(24).'9aA';
        $timezone = (string) config('app.timezone', 'UTC');
        $timezones = timezone_options();

        if (! in_array($timezone, $timezones, true)) {
            $timezone = $timezones[0] ?? 'UTC';
        }

        $user = $creator->create([
            'name' => $name,
            'email' => $email,
            'username' => $username,
            'timezone' => $timezone,
            'accept_terms' => '1',
            'password' => $password,
            'password_confirmation' => $password,
        ]);

        $user->forceFill([
            'email_verified_at' => now(),
        ])->save();

        event(new Registered($user));

        if (auth_welcome_email_enabled()) {
            $user->notify(new WelcomeNewUserNotification);
        }

        return $user;
    }

    protected function uniqueUsername(string $seed): string
    {
        $base = Str::slug($seed, '-');
        $base = Str::lower($base);

        if ($base === '') {
            $base = 'user';
        }

        if (strlen($base) < 3) {
            $base = str_pad($base, 3, 'x');
        }

        $base = Str::limit($base, 40, '');
        $username = $base;
        $counter = 2;

        while (User::query()->where('username', $username)->exists()) {
            $suffix = '-'.$counter;
            $username = Str::limit($base, 50 - strlen($suffix), '').$suffix;
            $counter++;
        }

        return $username;
    }

    protected function generatePkcePair(): array
    {
        $verifier = rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        return [
            'verifier' => $verifier,
            'challenge' => $challenge,
        ];
    }

    protected function failedLogin(string $message): RedirectResponse
    {
        return redirect()
            ->route('login')
            ->withErrors(['identifier' => $message]);
    }
}
