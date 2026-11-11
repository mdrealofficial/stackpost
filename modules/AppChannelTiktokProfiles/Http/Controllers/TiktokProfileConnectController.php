<?php

namespace Modules\AppChannelTiktokProfiles\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\AppChannelTiktokProfiles\Services\Tiktok\TiktokApiException;
use Modules\AppChannelTiktokProfiles\Services\Tiktok\TiktokApiService;
use Modules\AppChannels\Models\SocialAccount;
use Modules\AppChannels\Support\ChannelPlanAccess;
use Modules\AppTeams\Support\TeamWorkspaceAccess;

class TiktokProfileConnectController extends Controller
{
    public function __construct(
        protected TiktokApiService $tiktok,
        protected ChannelPlanAccess $planAccess,
    ) {}

    public function redirect(Request $request): RedirectResponse
    {
        $reconnectAccount = $this->resolveReconnectAccount($request);
        $isReconnect = $reconnectAccount !== null;

        if (! ($isReconnect ? $this->planAccess->canReconnect($request->user()) : $this->planAccess->canCreate($request->user()))
            || ! $this->planAccess->canUseCapability($request->user(), 'tiktok_profile')) {
            return redirect()
                ->route('portal.channels')
                ->with('channels.flash', [
                    'tone' => 'warning',
                    'message' => $isReconnect
                        ? __('Your current plan does not allow reconnecting TikTok profiles.')
                        : __('Your current plan does not allow connecting TikTok profiles.'),
                ]);
        }

        if (! $isReconnect && $this->planAccess->hasReachedLimit($request->user(), 'tiktok_profile')) {
            return redirect()
                ->route('portal.channels')
                ->with('channels.flash', [
                    'tone' => 'warning',
                    'message' => __('Your current plan has reached the channel limit.'),
                ]);
        }

        try {
            $config = $this->tiktok->ensureReady('tiktok');
        } catch (TiktokApiException $exception) {
            return redirect()
                ->route('portal.channels')
                ->with('channels.flash', [
                    'tone' => 'warning',
                    'message' => $exception->getMessage(),
                ]);
        }

        $oauthState = Str::random(40);

        $request->session()->put('tiktok_profile_oauth', [
            'state' => $oauthState,
            'user_id' => auth()->id(),
            'capability_key' => 'tiktok_profile',
            'provider_key' => 'tiktok',
            'return_to' => route('portal.channels', ['provider' => 'tiktok']),
            'reconnect_account_id' => $reconnectAccount?->id,
            'reconnect_external_id' => $reconnectAccount?->external_id,
        ]);

        return redirect()->away($this->tiktok->buildAuthorizationUrl([
            'state' => $oauthState,
            'redirect_uri' => $config['callback_url'],
            'scope' => $config['permissions'],
        ], 'tiktok'));
    }

    public function callback(Request $request): RedirectResponse
    {
        $oauthContext = (array) $request->session()->get('tiktok_profile_oauth', []);
        $isReconnect = filled($oauthContext['reconnect_account_id'] ?? null);

        if (! ($isReconnect ? $this->planAccess->canReconnect($request->user()) : $this->planAccess->canCreate($request->user()))
            || ! $this->planAccess->canUseCapability($request->user(), 'tiktok_profile')) {
            return redirect()
                ->route('portal.channels')
                ->with('channels.flash', [
                    'tone' => 'warning',
                    'message' => $isReconnect
                        ? __('Your current plan does not allow reconnecting TikTok profiles.')
                        : __('Your current plan does not allow connecting TikTok profiles.'),
                ]);
        }

        $context = (array) $request->session()->pull('tiktok_profile_oauth', []);

        if ($context === [] || ($context['user_id'] ?? null) !== auth()->id()) {
            return redirect()
                ->route('portal.channels')
                ->with('channels.flash', [
                    'tone' => 'danger',
                    'message' => __('The TikTok connection session has expired. Please try again.'),
                ]);
        }

        if (($context['state'] ?? null) !== $request->string('state')->toString()) {
            return redirect($context['return_to'] ?? route('portal.channels'))
                ->with('channels.flash', [
                    'tone' => 'danger',
                    'message' => __('The TikTok connection state was invalid. Please try again.'),
                ]);
        }

        if ($request->filled('error')) {
            return redirect($context['return_to'] ?? route('portal.channels'))
                ->with('channels.flash', [
                    'tone' => 'warning',
                    'message' => $request->string('error_description')->toString() ?: __('TikTok authorization was cancelled.'),
                ]);
        }

        $code = $request->string('code')->toString();

        if ($code === '') {
            return redirect($context['return_to'] ?? route('portal.channels'))
                ->with('channels.flash', [
                    'tone' => 'danger',
                    'message' => __('TikTok did not return an authorization code.'),
                ]);
        }

        try {
            $token = $this->tiktok->exchangeCodeForAccessToken($code, [], 'tiktok');
            $profile = $this->tiktok->getCurrentUser((string) $token['access_token'], 'tiktok');
        } catch (TiktokApiException $exception) {
            return redirect($context['return_to'] ?? route('portal.channels'))
                ->with('channels.flash', [
                    'tone' => 'danger',
                    'message' => $exception->getMessage(),
                ]);
        }

        $externalId = (string) ($token['open_id'] ?: data_get($profile, 'open_id', ''));
        $username = ltrim(trim((string) data_get($profile, 'username', '')), '@');
        $displayName = trim((string) data_get($profile, 'display_name', ''));
        $profileUrl = trim((string) data_get($profile, 'profile_deep_link', ''));
        $avatarUrl = trim((string) (data_get($profile, 'avatar_large_url') ?: data_get($profile, 'avatar_url') ?: ''));

        if ($externalId === '') {
            return redirect($context['return_to'] ?? route('portal.channels'))
                ->with('channels.flash', [
                    'tone' => 'danger',
                    'message' => __('TikTok did not return a profile identifier.'),
                ]);
        }

        if ($displayName === '') {
            $displayName = $username !== '' ? $username : __('TikTok Profile');
        }

        if ($profileUrl === '' && $username !== '') {
            $profileUrl = 'https://www.tiktok.com/@'.$username;
        }

        $reconnectAccountId = (int) ($context['reconnect_account_id'] ?? 0);

        SocialAccount::query()->updateOrCreate(
            $reconnectAccountId > 0
                ? ['id' => $reconnectAccountId]
                : [
                    'created_by_user_id' => \Modules\AppTeams\Support\TeamWorkspaceAccess::workspaceOwnerUserId(auth()->user()),
                    'provider_key' => 'tiktok',
                    'capability_key' => 'tiktok_profile',
                    'external_id' => $externalId,
                ],
            [
                'created_by_user_id' => \Modules\AppTeams\Support\TeamWorkspaceAccess::workspaceOwnerUserId(auth()->user()),
                'provider_key' => 'tiktok',
                'capability_key' => 'tiktok_profile',
                'external_id' => $externalId,
                'display_name' => $displayName,
                'username' => $username,
                'category' => 'Profile',
                'account_type' => 'oauth',
                'profile_url' => $profileUrl,
                'avatar_url' => $avatarUrl,
                'reconnect_url' => route('portal.channels.tiktok.profiles.connect'),
                'access_token' => (string) $token['access_token'],
                'refresh_token' => (string) ($token['refresh_token'] ?? ''),
                'scopes' => (string) ($token['scope'] ?: data_get($token, 'config.permissions', '')),
                'auth_data' => [
                    'source' => 'oauth',
                    'token_payload' => $token['payload'] ?? [],
                    'profile_payload' => $profile['payload'] ?? $profile,
                ],
                'metadata' => [
                    'provider' => 'tiktok',
                    'source' => 'oauth',
                    'open_id' => $externalId,
                    'is_verified' => (bool) data_get($profile, 'is_verified', false),
                    'stats' => [
                        'follower_count' => (int) data_get($profile, 'follower_count', 0),
                        'following_count' => (int) data_get($profile, 'following_count', 0),
                        'likes_count' => (int) data_get($profile, 'likes_count', 0),
                        'video_count' => (int) data_get($profile, 'video_count', 0),
                    ],
                ],
                'is_active' => true,
                'connected_at' => now(),
            ]
        );

        return redirect($context['return_to'] ?? route('portal.channels', ['provider' => 'tiktok']))
            ->with('channels.flash', [
                'tone' => 'success',
                'message' => $reconnectAccountId > 0
                    ? __('TikTok profile reconnected successfully.')
                    : __('TikTok profile connected successfully.'),
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
            ->where('provider_key', 'tiktok')
            ->where('capability_key', 'tiktok_profile')
            ->first();
    }

    public function creatorInfo(Request $request)
    {
        $accountIds = collect((array) $request->input('accounts', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();

        if ($accountIds->isEmpty()) {
            return response()->json([
                'status' => 0,
                'message' => __('Please select at least one TikTok profile.'),
            ]);
        }

        $accounts = TeamWorkspaceAccess::accessibleAccountsQuery($request->user())
            ->whereIn('id', $accountIds->all())
            ->where('provider_key', 'tiktok')
            ->where('capability_key', 'tiktok_profile')
            ->get();

        if ($accounts->isEmpty()) {
            return response()->json([
                'status' => 0,
                'message' => __('TikTok profile not found.'),
            ]);
        }

        $profiles = [];
        $errors = [];

        foreach ($accounts as $account) {
            try {
                $token = trim((string) $account->access_token);

                if ($token === '') {
                    $token = (string) ($this->tiktok->refreshAccountAccessToken($account)['access_token'] ?? '');
                }

                try {
                    $creator = $this->tiktok->queryCreatorInfo($token, 'tiktok');
                } catch (TiktokApiException $exception) {
                    if (! str_contains(strtolower($exception->getMessage()), 'access_token_invalid')) {
                        throw $exception;
                    }

                    $token = (string) ($this->tiktok->refreshAccountAccessToken($account)['access_token'] ?? '');
                    $creator = $this->tiktok->queryCreatorInfo($token, 'tiktok');
                }

                $profiles[] = [
                    'id' => (string) $account->id,
                    'name' => (string) $account->display_name,
                    'nickname' => (string) ($creator['creator_nickname'] ?? $account->display_name),
                    'username' => (string) ($creator['creator_username'] ?? $account->username),
                    'avatar' => (string) ($creator['creator_avatar_url'] ?? $account->avatar_url),
                    'privacy_level_options' => array_values((array) ($creator['privacy_level_options'] ?? [])),
                    'comment_disabled' => (bool) ($creator['comment_disabled'] ?? false),
                    'duet_disabled' => (bool) ($creator['duet_disabled'] ?? false),
                    'stitch_disabled' => (bool) ($creator['stitch_disabled'] ?? false),
                    'max_video_post_duration_sec' => (int) ($creator['max_video_post_duration_sec'] ?? 0),
                    'can_post' => $this->resolveCreatorCanPost($creator),
                ];
            } catch (\Throwable $exception) {
                $errors[] = $account->display_name.': '.$exception->getMessage();
            }
        }

        if ($profiles === []) {
            return response()->json([
                'status' => 0,
                'message' => $errors !== [] ? implode(' ', $errors) : __('Unable to load TikTok creator info.'),
            ]);
        }

        return response()->json([
            'status' => 1,
            'data' => [
                'profiles' => $profiles,
                'errors' => $errors,
            ],
        ]);
    }

    protected function resolveCreatorCanPost(array $creator): bool
    {
        foreach (['can_post', 'creator_can_post', 'postable', 'can_publish'] as $key) {
            if (array_key_exists($key, $creator)) {
                return (bool) $creator[$key];
            }
        }

        return true;
    }
}
