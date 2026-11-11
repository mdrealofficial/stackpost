<?php

namespace Modules\AppChannelFacebookPages\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\AppChannelFacebookPages\Services\Facebook\FacebookApiException;
use Modules\AppChannelFacebookPages\Services\Facebook\FacebookApiService;
use Modules\AppChannels\Models\SocialAccount;
use Modules\AppChannels\Support\ChannelPlanAccess;

class FacebookPageConnectController extends Controller
{
    public function __construct(
        protected FacebookApiService $facebook,
        protected ChannelPlanAccess $planAccess,
    ) {}

    public function redirect(Request $request): RedirectResponse
    {
        $reconnectAccount = $this->resolveReconnectAccount($request);
        $isReconnect = $reconnectAccount !== null;

        if (! ($isReconnect ? $this->planAccess->canReconnect($request->user()) : $this->planAccess->canCreate($request->user()))
            || ! $this->planAccess->canUseCapability($request->user(), 'facebook_page')) {
            return redirect()
                ->route('portal.channels')
                ->with('channels.flash', [
                    'tone' => 'warning',
                    'message' => $isReconnect
                        ? __('Your current plan does not allow reconnecting Facebook pages.')
                        : __('Your current plan does not allow connecting Facebook pages.'),
                ]);
        }

        if (! $isReconnect && $this->planAccess->hasReachedLimit($request->user(), 'facebook_page')) {
            return redirect()
                ->route('portal.channels')
                ->with('channels.flash', [
                    'tone' => 'warning',
                    'message' => __('Your current plan has reached the channel limit.'),
                ]);
        }

        try {
            $config = $this->facebook->ensureReady('facebook');
        } catch (FacebookApiException $exception) {
            return redirect()
                ->route('portal.channels')
                ->with('channels.flash', [
                    'tone' => 'warning',
                    'message' => $exception->getMessage(),
                ]);
        }

        $oauthState = Str::random(40);

        $request->session()->put('facebook_page_oauth', [
            'state' => $oauthState,
            'user_id' => auth()->id(),
            'capability_key' => 'facebook_page',
            'provider_key' => 'facebook',
            'return_to' => route('portal.channels', ['provider' => 'facebook']),
            'reconnect_account_id' => $reconnectAccount?->id,
            'reconnect_external_id' => $reconnectAccount?->external_id,
        ]);

        return redirect()->away($this->facebook->buildAuthorizationUrl([
            'state' => $oauthState,
            'redirect_uri' => $config['callback_url'],
            'scope' => $config['permissions'],
        ], 'facebook'));
    }

    public function callback(Request $request): RedirectResponse
    {
        $oauthContext = (array) $request->session()->get('facebook_page_oauth', []);
        $isReconnect = filled($oauthContext['reconnect_account_id'] ?? null);

        if (! ($isReconnect ? $this->planAccess->canReconnect($request->user()) : $this->planAccess->canCreate($request->user()))
            || ! $this->planAccess->canUseCapability($request->user(), 'facebook_page')) {
            return redirect()
                ->route('portal.channels')
                ->with('channels.flash', [
                    'tone' => 'warning',
                    'message' => $isReconnect
                        ? __('Your current plan does not allow reconnecting Facebook pages.')
                        : __('Your current plan does not allow connecting Facebook pages.'),
                ]);
        }

        $context = (array) $request->session()->pull('facebook_page_oauth', []);

        if ($context === [] || ($context['user_id'] ?? null) !== auth()->id()) {
            return redirect()
                ->route('portal.channels')
                ->with('channels.flash', [
                    'tone' => 'danger',
                    'message' => __('The Facebook connection session has expired. Please try again.'),
                ]);
        }

        if (($context['state'] ?? null) !== $request->string('state')->toString()) {
            return redirect($context['return_to'] ?? route('portal.channels'))
                ->with('channels.flash', [
                    'tone' => 'danger',
                    'message' => __('The Facebook connection state was invalid. Please try again.'),
                ]);
        }

        if ($request->filled('error')) {
            return redirect($context['return_to'] ?? route('portal.channels'))
                ->with('channels.flash', [
                    'tone' => 'warning',
                    'message' => $request->string('error_message')->toString() ?: __('Facebook authorization was cancelled.'),
                ]);
        }

        $code = $request->string('code')->toString();

        if ($code === '') {
            return redirect($context['return_to'] ?? route('portal.channels'))
                ->with('channels.flash', [
                    'tone' => 'danger',
                    'message' => __('Facebook did not return an authorization code.'),
                ]);
        }

        try {
            $token = $this->facebook->exchangeCodeForAccessToken($code, [], 'facebook');
            $config = $token['config'];
            $pages = $this->facebook->getUserPages($token['access_token'], [], 'facebook');
        } catch (FacebookApiException $exception) {
            return redirect($context['return_to'] ?? route('portal.channels'))
                ->with('channels.flash', [
                    'tone' => 'danger',
                    'message' => $exception->getMessage(),
                ]);
        }

        if ($pages->isEmpty()) {
            return redirect($context['return_to'] ?? route('portal.channels'))
                ->with('channels.flash', [
                    'tone' => 'warning',
                    'message' => __('No Facebook pages were available for this account.'),
                ]);
        }

        $request->session()->put('facebook_page_picker', [
            'provider_key' => 'facebook',
            'capability_key' => 'facebook_page',
            'permissions' => (string) ($config['permissions'] ?? ''),
            'graph_version' => (string) ($config['graph_version'] ?? 'v25.0'),
            'reconnect_url' => route('portal.channels.facebook.pages.connect'),
            'reconnect_account_id' => $context['reconnect_account_id'] ?? null,
            'reconnect_external_id' => $context['reconnect_external_id'] ?? null,
            'user_token' => (string) $token['access_token'],
            'token_payload' => $token['payload'] ?? [],
            'pages' => $pages->all(),
        ]);

        return redirect()->route('portal.channels.facebook.pages.select');
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
            ->where('provider_key', 'facebook')
            ->where('capability_key', 'facebook_page')
            ->first();
    }
}
