<?php

namespace Modules\AppChannelXProfiles\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\AppChannels\Models\SocialAccount;
use Modules\AppChannels\Support\ChannelPlanAccess;
use Modules\AppChannelXProfiles\Services\X\XApiException;
use Modules\AppChannelXProfiles\Services\X\XApiService;

class XProfileConnectController extends Controller
{
    public function __construct(
        protected XApiService $x,
        protected ChannelPlanAccess $planAccess,
    ) {}

    public function redirect(Request $request): RedirectResponse
    {
        $reconnectAccount = $this->resolveReconnectAccount($request);
        $isReconnect = $reconnectAccount !== null;

        if (! ($isReconnect ? $this->planAccess->canReconnect($request->user()) : $this->planAccess->canCreate($request->user()))
            || ! $this->planAccess->canUseCapability($request->user(), 'x_profile')) {
            return redirect()
                ->route('portal.channels')
                ->with('channels.flash', [
                    'tone' => 'warning',
                    'message' => $isReconnect
                        ? __('Your current plan does not allow reconnecting X profiles.')
                        : __('Your current plan does not allow connecting X profiles.'),
                ]);
        }

        if (! $isReconnect && $this->planAccess->hasReachedLimit($request->user(), 'x_profile')) {
            return redirect()
                ->route('portal.channels')
                ->with('channels.flash', [
                    'tone' => 'warning',
                    'message' => __('Your current plan has reached the channel limit.'),
                ]);
        }

        try {
            $config = $this->x->ensureReady('x');
        } catch (XApiException $exception) {
            return redirect()
                ->route('portal.channels')
                ->with('channels.flash', [
                    'tone' => 'warning',
                    'message' => $exception->getMessage(),
                ]);
        }

        $oauthState = Str::random(40);
        $pkce = $this->x->generatePkcePair();

        $request->session()->put('x_profile_oauth', [
            'state' => $oauthState,
            'pkce_verifier' => $pkce['verifier'],
            'user_id' => auth()->id(),
            'capability_key' => 'x_profile',
            'provider_key' => 'x',
            'return_to' => route('portal.channels', ['provider' => 'x']),
            'reconnect_account_id' => $reconnectAccount?->id,
            'reconnect_external_id' => $reconnectAccount?->external_id,
        ]);

        return redirect()->away($this->x->buildAuthorizationUrl([
            'state' => $oauthState,
            'redirect_uri' => $config['callback_url'],
            'scope' => $config['permissions'],
            'pkce' => $pkce,
        ], 'x'));
    }

    public function callback(Request $request): RedirectResponse
    {
        $oauthContext = (array) $request->session()->get('x_profile_oauth', []);
        $isReconnect = filled($oauthContext['reconnect_account_id'] ?? null);

        if (! ($isReconnect ? $this->planAccess->canReconnect($request->user()) : $this->planAccess->canCreate($request->user()))
            || ! $this->planAccess->canUseCapability($request->user(), 'x_profile')) {
            return redirect()
                ->route('portal.channels')
                ->with('channels.flash', [
                    'tone' => 'warning',
                    'message' => $isReconnect
                        ? __('Your current plan does not allow reconnecting X profiles.')
                        : __('Your current plan does not allow connecting X profiles.'),
                ]);
        }

        $context = (array) $request->session()->pull('x_profile_oauth', []);

        if ($context === [] || ($context['user_id'] ?? null) !== auth()->id()) {
            return redirect()
                ->route('portal.channels')
                ->with('channels.flash', [
                    'tone' => 'danger',
                    'message' => __('The X connection session has expired. Please try again.'),
                ]);
        }

        if (($context['state'] ?? null) !== $request->string('state')->toString()) {
            return redirect($context['return_to'] ?? route('portal.channels'))
                ->with('channels.flash', [
                    'tone' => 'danger',
                    'message' => __('The X connection state was invalid. Please try again.'),
                ]);
        }

        if ($request->filled('error')) {
            return redirect($context['return_to'] ?? route('portal.channels'))
                ->with('channels.flash', [
                    'tone' => 'warning',
                    'message' => $request->string('error_description')->toString() ?: __('X authorization was cancelled.'),
                ]);
        }

        $code = $request->string('code')->toString();
        $codeVerifier = (string) ($context['pkce_verifier'] ?? '');

        if ($code === '' || $codeVerifier === '') {
            return redirect($context['return_to'] ?? route('portal.channels'))
                ->with('channels.flash', [
                    'tone' => 'danger',
                    'message' => __('X did not return a valid authorization code.'),
                ]);
        }

        try {
            $token = $this->x->exchangeCodeForAccessToken($code, $codeVerifier, [], 'x');
            $profile = $this->x->getCurrentUser((string) $token['access_token'], 'x');
        } catch (XApiException $exception) {
            return redirect($context['return_to'] ?? route('portal.channels'))
                ->with('channels.flash', [
                    'tone' => 'danger',
                    'message' => $exception->getMessage(),
                ]);
        }

        $externalId = trim((string) data_get($profile, 'id', ''));
        $username = ltrim(trim((string) data_get($profile, 'username', '')), '@');
        $displayName = trim((string) data_get($profile, 'name', ''));
        $avatarUrl = trim((string) data_get($profile, 'profile_image_url', ''));
        $profileUrl = $username !== '' ? 'https://x.com/'.$username : 'https://x.com/';

        if ($externalId === '') {
            return redirect($context['return_to'] ?? route('portal.channels'))
                ->with('channels.flash', [
                    'tone' => 'danger',
                    'message' => __('X did not return a profile identifier.'),
                ]);
        }

        if ($displayName === '') {
            $displayName = $username !== '' ? $username : __('X Profile');
        }

        $reconnectAccountId = (int) ($context['reconnect_account_id'] ?? 0);

        SocialAccount::query()->updateOrCreate(
            $reconnectAccountId > 0
                ? ['id' => $reconnectAccountId]
                : [
                    'created_by_user_id' => \Modules\AppTeams\Support\TeamWorkspaceAccess::workspaceOwnerUserId(auth()->user()),
                    'provider_key' => 'x',
                    'capability_key' => 'x_profile',
                    'external_id' => $externalId,
                ],
            [
                'created_by_user_id' => \Modules\AppTeams\Support\TeamWorkspaceAccess::workspaceOwnerUserId(auth()->user()),
                'provider_key' => 'x',
                'capability_key' => 'x_profile',
                'external_id' => $externalId,
                'display_name' => $displayName,
                'username' => $username,
                'category' => 'Profile',
                'account_type' => 'oauth',
                'profile_url' => $profileUrl,
                'avatar_url' => $avatarUrl,
                'reconnect_url' => route('portal.channels.x.profiles.connect'),
                'access_token' => (string) $token['access_token'],
                'refresh_token' => (string) ($token['refresh_token'] ?? ''),
                'scopes' => (string) ($token['scope'] ?: data_get($token, 'config.permissions', '')),
                'auth_data' => [
                    'source' => 'oauth',
                    'token_payload' => $token['payload'] ?? [],
                    'profile_payload' => $profile['payload'] ?? $profile,
                    'refresh_token' => (string) ($token['refresh_token'] ?? ''),
                ],
                'metadata' => [
                    'provider' => 'x',
                    'source' => 'oauth',
                    'verified' => (bool) data_get($profile, 'verified', false),
                    'description' => (string) data_get($profile, 'description', ''),
                    'public_metrics' => (array) data_get($profile, 'public_metrics', []),
                ],
                'is_active' => true,
                'connected_at' => now(),
            ]
        );

        return redirect($context['return_to'] ?? route('portal.channels', ['provider' => 'x']))
            ->with('channels.flash', [
                'tone' => 'success',
                'message' => $reconnectAccountId > 0
                    ? __('X profile reconnected successfully.')
                    : __('X profile connected successfully.'),
            ]);
    }

    protected function resolveReconnectAccount(Request $request): ?SocialAccount
    {
        $accountId = $request->integer('account');

        if (! $request->boolean('reconnect') || $accountId <= 0) {
            return null;
        }

        return SocialAccount::query()
            ->whereKey($accountId)
            ->where('created_by_user_id', \Modules\AppTeams\Support\TeamWorkspaceAccess::workspaceOwnerUserId(auth()->user()))
            ->where('provider_key', 'x')
            ->where('capability_key', 'x_profile')
            ->first();
    }
}
