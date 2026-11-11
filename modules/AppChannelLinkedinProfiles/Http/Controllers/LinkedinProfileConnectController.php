<?php

namespace Modules\AppChannelLinkedinProfiles\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\AppChannelLinkedinProfiles\Services\Linkedin\LinkedinApiException;
use Modules\AppChannelLinkedinProfiles\Services\Linkedin\LinkedinApiService;
use Modules\AppChannels\Models\SocialAccount;
use Modules\AppChannels\Support\ChannelPlanAccess;

class LinkedinProfileConnectController extends Controller
{
    public function __construct(
        protected LinkedinApiService $linkedin,
        protected ChannelPlanAccess $planAccess,
    ) {}

    public function redirect(Request $request): RedirectResponse
    {
        $reconnectAccount = $this->resolveReconnectAccount($request);
        $isReconnect = $reconnectAccount !== null;

        if (! ($isReconnect ? $this->planAccess->canReconnect($request->user()) : $this->planAccess->canCreate($request->user()))
            || ! $this->planAccess->canUseCapability($request->user(), 'linkedin_profile')) {
            return redirect()
                ->route('portal.channels')
                ->with('channels.flash', [
                    'tone' => 'warning',
                    'message' => $isReconnect
                        ? __('Your current plan does not allow reconnecting LinkedIn profiles.')
                        : __('Your current plan does not allow connecting LinkedIn profiles.'),
                ]);
        }

        if (! $isReconnect && $this->planAccess->hasReachedLimit($request->user(), 'linkedin_profile')) {
            return redirect()
                ->route('portal.channels')
                ->with('channels.flash', [
                    'tone' => 'warning',
                    'message' => __('Your current plan has reached the channel limit.'),
                ]);
        }

        try {
            $config = $this->linkedin->ensureReady('linkedin_profile');
        } catch (LinkedinApiException $exception) {
            return redirect()
                ->route('portal.channels')
                ->with('channels.flash', [
                    'tone' => 'warning',
                    'message' => $exception->getMessage(),
                ]);
        }

        $oauthState = Str::random(40);

        $request->session()->put('linkedin_profile_oauth', [
            'state' => $oauthState,
            'user_id' => auth()->id(),
            'return_to' => route('portal.channels', ['provider' => 'linkedin_profile']),
            'reconnect_account_id' => $reconnectAccount?->id,
        ]);

        return redirect()->away($this->linkedin->buildAuthorizationUrl([
            'state' => $oauthState,
            'redirect_uri' => $config['callback_url'],
            'scope' => $config['permissions'],
        ], 'linkedin_profile'));
    }

    public function callback(Request $request): RedirectResponse
    {
        $oauthContext = (array) $request->session()->get('linkedin_profile_oauth', []);
        $isReconnect = filled($oauthContext['reconnect_account_id'] ?? null);

        if (! ($isReconnect ? $this->planAccess->canReconnect($request->user()) : $this->planAccess->canCreate($request->user()))
            || ! $this->planAccess->canUseCapability($request->user(), 'linkedin_profile')) {
            return redirect()
                ->route('portal.channels')
                ->with('channels.flash', [
                    'tone' => 'warning',
                    'message' => $isReconnect
                        ? __('Your current plan does not allow reconnecting LinkedIn profiles.')
                        : __('Your current plan does not allow connecting LinkedIn profiles.'),
                ]);
        }

        $context = (array) $request->session()->pull('linkedin_profile_oauth', []);

        if ($context === [] || ($context['user_id'] ?? null) !== auth()->id()) {
            return redirect()
                ->route('portal.channels')
                ->with('channels.flash', [
                    'tone' => 'danger',
                    'message' => __('The LinkedIn connection session has expired. Please try again.'),
                ]);
        }

        if (($context['state'] ?? null) !== $request->string('state')->toString()) {
            return redirect($context['return_to'] ?? route('portal.channels'))
                ->with('channels.flash', [
                    'tone' => 'danger',
                    'message' => __('The LinkedIn connection state was invalid. Please try again.'),
                ]);
        }

        if ($request->filled('error')) {
            return redirect($context['return_to'] ?? route('portal.channels'))
                ->with('channels.flash', [
                    'tone' => 'warning',
                    'message' => $request->string('error_description')->toString() ?: __('LinkedIn authorization was cancelled.'),
                ]);
        }

        $code = $request->string('code')->toString();

        if ($code === '') {
            return redirect($context['return_to'] ?? route('portal.channels'))
                ->with('channels.flash', [
                    'tone' => 'danger',
                    'message' => __('LinkedIn did not return an authorization code.'),
                ]);
        }

        try {
            $config = $this->linkedin->config('linkedin_profile');
            $token = $this->linkedin->exchangeCodeForAccessToken($code, $config['callback_url'], 'linkedin_profile');
            $profile = $this->linkedin->getPerson((string) $token['access_token']);
        } catch (LinkedinApiException $exception) {
            return redirect($context['return_to'] ?? route('portal.channels'))
                ->with('channels.flash', [
                    'tone' => 'danger',
                    'message' => $exception->getMessage(),
                ]);
        }

        $externalId = trim((string) ($profile['sub'] ?? ''));
        $displayName = trim((string) ($profile['name'] ?? ''));
        $avatarUrl = trim((string) ($profile['picture'] ?? ''));
        $email = trim((string) ($profile['email'] ?? ''));

        if ($externalId === '') {
            return redirect($context['return_to'] ?? route('portal.channels'))
                ->with('channels.flash', [
                    'tone' => 'danger',
                    'message' => __('LinkedIn did not return a valid member profile identifier.'),
                ]);
        }

        if ($displayName === '') {
            $displayName = __('LinkedIn Profile');
        }

        $accountId = (int) ($context['reconnect_account_id'] ?? 0);

        SocialAccount::query()->updateOrCreate(
            $accountId > 0
                ? ['id' => $accountId]
                : [
                    'created_by_user_id' => \Modules\AppTeams\Support\TeamWorkspaceAccess::workspaceOwnerUserId(auth()->user()),
                    'provider_key' => 'linkedin_profile',
                    'capability_key' => 'linkedin_profile',
                    'external_id' => $externalId,
                ],
            [
                'created_by_user_id' => \Modules\AppTeams\Support\TeamWorkspaceAccess::workspaceOwnerUserId(auth()->user()),
                'provider_key' => 'linkedin_profile',
                'capability_key' => 'linkedin_profile',
                'external_id' => $externalId,
                'display_name' => $displayName,
                'username' => Str::slug($displayName),
                'category' => 'Profile',
                'account_type' => 'oauth',
                'profile_url' => 'https://www.linkedin.com/',
                'avatar_url' => $avatarUrl,
                'reconnect_url' => route('portal.channels.linkedin.profiles.connect'),
                'access_token' => (string) $token['access_token'],
                'refresh_token' => null,
                'scopes' => (string) ($token['scope'] ?: data_get($token, 'config.permissions', '')),
                'auth_data' => [
                    'token_payload' => $token['payload'] ?? [],
                    'profile_payload' => $profile,
                ],
                'metadata' => [
                    'provider' => 'linkedin_profile',
                    'author_urn' => 'urn:li:person:'.$externalId,
                    'email' => $email,
                ],
                'is_active' => true,
                'connected_at' => now(),
            ]
        );

        return redirect($context['return_to'] ?? route('portal.channels', ['provider' => 'linkedin_profile']))
            ->with('channels.flash', [
                'tone' => 'success',
                'message' => $accountId > 0
                    ? __('LinkedIn profile reconnected successfully.')
                    : __('LinkedIn profile connected successfully.'),
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
            ->where('provider_key', 'linkedin_profile')
            ->where('capability_key', 'linkedin_profile')
            ->first();
    }
}
