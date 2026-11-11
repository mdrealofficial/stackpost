<?php

namespace Modules\AppChannelLinkedinPages\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\AppChannelLinkedinProfiles\Services\Linkedin\LinkedinApiException;
use Modules\AppChannelLinkedinProfiles\Services\Linkedin\LinkedinApiService;
use Modules\AppChannels\Models\SocialAccount;
use Modules\AppChannels\Support\ChannelPlanAccess;

class LinkedinPageConnectController extends Controller
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
            || ! $this->planAccess->canUseCapability($request->user(), 'linkedin_page')) {
            return redirect()
                ->route('portal.channels')
                ->with('channels.flash', [
                    'tone' => 'warning',
                    'message' => $isReconnect
                        ? __('Your current plan does not allow reconnecting LinkedIn pages.')
                        : __('Your current plan does not allow connecting LinkedIn pages.'),
                ]);
        }

        if (! $isReconnect && $this->planAccess->hasReachedLimit($request->user(), 'linkedin_page')) {
            return redirect()
                ->route('portal.channels')
                ->with('channels.flash', [
                    'tone' => 'warning',
                    'message' => __('Your current plan has reached the channel limit.'),
                ]);
        }

        try {
            $config = $this->linkedin->ensureReady('linkedin_page');
        } catch (LinkedinApiException $exception) {
            return redirect()
                ->route('portal.channels')
                ->with('channels.flash', [
                    'tone' => 'warning',
                    'message' => $exception->getMessage(),
                ]);
        }

        $oauthState = Str::random(40);

        $request->session()->put('linkedin_page_oauth', [
            'state' => $oauthState,
            'user_id' => auth()->id(),
            'return_to' => route('portal.channels', ['provider' => 'linkedin_page']),
            'reconnect_account_id' => $reconnectAccount?->id,
        ]);

        return redirect()->away($this->linkedin->buildAuthorizationUrl([
            'state' => $oauthState,
            'redirect_uri' => $config['callback_url'],
            'scope' => $config['permissions'],
        ], 'linkedin_page'));
    }

    public function callback(Request $request): RedirectResponse
    {
        $oauthContext = (array) $request->session()->get('linkedin_page_oauth', []);
        $isReconnect = filled($oauthContext['reconnect_account_id'] ?? null);

        if (! ($isReconnect ? $this->planAccess->canReconnect($request->user()) : $this->planAccess->canCreate($request->user()))
            || ! $this->planAccess->canUseCapability($request->user(), 'linkedin_page')) {
            return redirect()
                ->route('portal.channels')
                ->with('channels.flash', [
                    'tone' => 'warning',
                    'message' => $isReconnect
                        ? __('Your current plan does not allow reconnecting LinkedIn pages.')
                        : __('Your current plan does not allow connecting LinkedIn pages.'),
                ]);
        }

        $context = (array) $request->session()->pull('linkedin_page_oauth', []);

        if ($context === [] || ($context['user_id'] ?? null) !== auth()->id()) {
            return redirect()
                ->route('portal.channels')
                ->with('channels.flash', [
                    'tone' => 'danger',
                    'message' => __('The LinkedIn page connection session has expired. Please try again.'),
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
            $config = $this->linkedin->config('linkedin_page');
            $token = $this->linkedin->exchangeCodeForAccessToken($code, $config['callback_url'], 'linkedin_page');
            $pages = $this->linkedin->getCompanyPages((string) $token['access_token']);
        } catch (LinkedinApiException $exception) {
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
                    'message' => __('No LinkedIn organization pages were available for this account.'),
                ]);
        }

        $request->session()->put('linkedin_page_picker', [
            'provider_key' => 'linkedin_page',
            'permissions' => (string) ($token['scope'] ?: data_get($token, 'config.permissions', '')),
            'reconnect_url' => route('portal.channels.linkedin.pages.connect'),
            'reconnect_account_id' => $context['reconnect_account_id'] ?? null,
            'user_token' => (string) $token['access_token'],
            'token_payload' => $token['payload'] ?? [],
            'pages' => $pages->all(),
        ]);

        return redirect()->route('portal.channels.linkedin.pages.select');
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
            ->where('provider_key', 'linkedin_page')
            ->where('capability_key', 'linkedin_page')
            ->first();
    }
}
