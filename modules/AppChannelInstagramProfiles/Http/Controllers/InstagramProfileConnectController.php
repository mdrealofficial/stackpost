<?php

namespace Modules\AppChannelInstagramProfiles\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\AppChannelInstagramProfiles\Services\Instagram\InstagramApiException;
use Modules\AppChannels\Models\SocialAccount;
use Modules\AppChannels\Support\ChannelPlanAccess;

class InstagramProfileConnectController extends Controller
{
    public function __construct(
        protected ChannelPlanAccess $planAccess,
    ) {}

    public function redirect(Request $request): RedirectResponse
    {
        $reconnectAccount = $this->resolveReconnectAccount($request);
        $isReconnect = $reconnectAccount !== null;

        if (! ($isReconnect ? $this->planAccess->canReconnect($request->user()) : $this->planAccess->canCreate($request->user()))
            || ! $this->planAccess->canUseCapability($request->user(), 'instagram_profile')) {
            return redirect()
                ->route('portal.channels')
                ->with('channels.flash', [
                    'tone' => 'warning',
                    'message' => $isReconnect
                        ? __('Your current plan does not allow reconnecting Instagram profiles.')
                        : __('Your current plan does not allow connecting Instagram profiles.'),
                ]);
        }

        if (! $isReconnect && $this->planAccess->hasReachedLimit($request->user(), 'instagram_profile')) {
            return redirect()
                ->route('portal.channels')
                ->with('channels.flash', [
                    'tone' => 'warning',
                    'message' => __('Your current plan has reached the channel limit.'),
                ]);
        }

        try {
            $config = \Instagram::ensureReady('instagram');
        } catch (InstagramApiException $exception) {
            return redirect()
                ->route('portal.channels')
                ->with('channels.flash', [
                    'tone' => 'warning',
                    'message' => $exception->getMessage(),
                ]);
        }

        $oauthState = Str::random(40);

        $request->session()->put('instagram_profile_oauth', [
            'state' => $oauthState,
            'user_id' => auth()->id(),
            'capability_key' => 'instagram_profile',
            'provider_key' => 'instagram',
            'return_to' => route('portal.channels', ['provider' => 'instagram']),
            'reconnect_account_id' => $reconnectAccount?->id,
            'reconnect_external_id' => $reconnectAccount?->external_id,
        ]);

        return redirect()->away(\Instagram::buildAuthorizationUrl([
            'state' => $oauthState,
            'redirect_uri' => $config['callback_url'],
            'scope' => $config['permissions'],
        ], 'instagram'));
    }

    public function callback(Request $request): RedirectResponse
    {
        $oauthContext = (array) $request->session()->get('instagram_profile_oauth', []);
        $isReconnect = filled($oauthContext['reconnect_account_id'] ?? null);

        if (! ($isReconnect ? $this->planAccess->canReconnect($request->user()) : $this->planAccess->canCreate($request->user()))
            || ! $this->planAccess->canUseCapability($request->user(), 'instagram_profile')) {
            return redirect()
                ->route('portal.channels')
                ->with('channels.flash', [
                    'tone' => 'warning',
                    'message' => $isReconnect
                        ? __('Your current plan does not allow reconnecting Instagram profiles.')
                        : __('Your current plan does not allow connecting Instagram profiles.'),
                ]);
        }

        $context = (array) $request->session()->pull('instagram_profile_oauth', []);

        if ($context === [] || ($context['user_id'] ?? null) !== auth()->id()) {
            return redirect()
                ->route('portal.channels')
                ->with('channels.flash', [
                    'tone' => 'danger',
                    'message' => __('The Instagram connection session has expired. Please try again.'),
                ]);
        }

        if (($context['state'] ?? null) !== $request->string('state')->toString()) {
            return redirect($context['return_to'] ?? route('portal.channels'))
                ->with('channels.flash', [
                    'tone' => 'danger',
                    'message' => __('The Instagram connection state was invalid. Please try again.'),
                ]);
        }

        if ($request->filled('error')) {
            return redirect($context['return_to'] ?? route('portal.channels'))
                ->with('channels.flash', [
                    'tone' => 'warning',
                    'message' => $request->string('error_message')->toString() ?: __('Instagram authorization was cancelled.'),
                ]);
        }

        $code = $request->string('code')->toString();

        if ($code === '') {
            return redirect($context['return_to'] ?? route('portal.channels'))
                ->with('channels.flash', [
                    'tone' => 'danger',
                    'message' => __('Instagram did not return an authorization code.'),
                ]);
        }

        try {
            $token = \Instagram::exchangeCodeForAccessToken($code, [], 'instagram');
            $config = $token['config'];
            $profiles = \Instagram::getConnectedProfiles((string) $token['access_token'], 'instagram');
        } catch (InstagramApiException $exception) {
            return redirect($context['return_to'] ?? route('portal.channels'))
                ->with('channels.flash', [
                    'tone' => 'danger',
                    'message' => $exception->getMessage(),
                ]);
        }

        if ($profiles->isEmpty()) {
            return redirect($context['return_to'] ?? route('portal.channels'))
                ->with('channels.flash', [
                    'tone' => 'warning',
                    'message' => __('No Instagram business or creator profiles were available for this account.'),
                ]);
        }

        $request->session()->put('instagram_profile_picker', [
            'provider_key' => 'instagram',
            'capability_key' => 'instagram_profile',
            'permissions' => (string) ($config['permissions'] ?? ''),
            'graph_version' => (string) ($config['graph_version'] ?? 'v25.0'),
            'reconnect_url' => route('portal.channels.instagram.profiles.connect'),
            'reconnect_account_id' => $context['reconnect_account_id'] ?? null,
            'reconnect_external_id' => $context['reconnect_external_id'] ?? null,
            'user_token' => (string) $token['access_token'],
            'token_payload' => $token['payload'] ?? [],
            'profiles' => $profiles->all(),
        ]);

        return redirect()->route('portal.channels.instagram.profiles.select');
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
            ->where('provider_key', 'instagram')
            ->where('capability_key', 'instagram_profile')
            ->first();
    }
}
