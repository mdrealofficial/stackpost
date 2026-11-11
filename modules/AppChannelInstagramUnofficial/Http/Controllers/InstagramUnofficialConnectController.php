<?php

namespace Modules\AppChannelInstagramUnofficial\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\AppChannels\Models\SocialAccount;
use Modules\AppChannels\Support\ChannelPlanAccess;
use Modules\AppChannelInstagramUnofficial\Services\InstagramUnofficialService;

class InstagramUnofficialConnectController extends Controller
{
    public function __construct(
        protected InstagramUnofficialService $instagram,
        protected ChannelPlanAccess $planAccess,
    ) {}

    public function show(Request $request)
    {
        $account = $this->resolveReconnectAccount($request);
        $isReconnect = $account !== null;

        if (! ($isReconnect ? $this->planAccess->canReconnect($request->user()) : $this->planAccess->canCreate($request->user()))
            || ! $this->planAccess->canUseCapability($request->user(), 'instagram_unofficial_profile')) {
            return redirect()->route('portal.channels')->with('channels.flash', [
                'tone' => 'warning',
                'message' => $isReconnect
                    ? __('Your current plan does not allow reconnecting Instagram Unofficial profiles.')
                    : __('Your current plan does not allow connecting Instagram Unofficial profiles.'),
            ]);
        }

        if (! $isReconnect && $this->planAccess->hasReachedLimit($request->user(), 'instagram_unofficial_profile')) {
            return redirect()->route('portal.channels')->with('channels.flash', [
                'tone' => 'warning',
                'message' => __('Your current plan has reached the channel limit.'),
            ]);
        }

        return response()->view('appchannelinstagramunofficial::connect.form', [
            'account' => $account,
            'isReconnect' => $isReconnect,
        ]);
    }

    public function process(Request $request): JsonResponse
    {
        $account = $this->resolveReconnectAccount($request);
        $isReconnect = $account !== null;

        if (! ($isReconnect ? $this->planAccess->canReconnect($request->user()) : $this->planAccess->canCreate($request->user()))
            || ! $this->planAccess->canUseCapability($request->user(), 'instagram_unofficial_profile')) {
            return response()->json([
                'status' => 0,
                'message' => __('Your current plan does not allow this Instagram Unofficial action.'),
            ], 403);
        }

        if (! $isReconnect && $this->planAccess->hasReachedLimit($request->user(), 'instagram_unofficial_profile')) {
            return response()->json([
                'status' => 0,
                'message' => __('Your current plan has reached the channel limit.'),
            ], 422);
        }

        $username = trim((string) $request->input('ig_username', ''));
        $password = trim((string) $request->input('ig_password', ''));
        $type = (int) $request->input('ig_type', 1);
        $verificationCode = trim((string) $request->input('ig_verification_code', ''));
        $securityCode = trim((string) $request->input('ig_security_code', ''));
        $options = json_decode((string) $request->input('ig_options', '{}'), true) ?: [];

        if ($username === '' || $password === '') {
            return response()->json([
                'status' => 0,
                'message' => __('Username and password are required.'),
            ], 422);
        }

        if ($options !== [] && $type !== 1) {
            if ($type === 2) {
                if ($verificationCode === '') {
                    return response()->json([
                        'status' => 0,
                        'message' => __('Verification code required.'),
                    ], 422);
                }

                $authData = (array) ($options['auth_data'] ?? []);
                $twoFactorIdentifier = (string) ($options['two_factor_identifier'] ?? '');
                $verificationMethod = (string) ($options['verification_method'] ?? '1');
                $this->instagram->setAuthData($authData);
                $response = $this->instagram->verifyTwoFactorCode($twoFactorIdentifier, $verificationCode, $verificationMethod);

                return $this->finalize($request, $response, $account);
            }

            if ($type === 3) {
                if ($securityCode === '') {
                    return response()->json([
                        'status' => 0,
                        'message' => __('Security code required.'),
                    ], 422);
                }

                return response()->json([
                    'status' => 0,
                    'message' => __('Challenge handling is not enabled in this port yet.'),
                ], 422);
            }
        }

        $response = $this->instagram->authenticate($username, $password);

        return $this->finalize($request, $response, $account);
    }

    protected function finalize(Request $request, array $response, ?SocialAccount $account = null): JsonResponse
    {
        if (! (bool) ($response['status'] ?? false)) {
            return response()->json([
                'status' => 0,
                'message' => (string) ($response['error'] ?? __('Instagram login failed. Please try again.')),
            ], 422);
        }

        $data = (array) ($response['data'] ?? []);
        $options = (array) ($data['options'] ?? []);

        if (! empty($data['needs_challenge']) && ! empty($options['two_factor_identifier'])) {
            return response()->json([
                'status' => 0,
                'type' => '2FA',
                'message' => __('Two-factor authentication required.'),
                'options' => $options,
            ]);
        }

        if (! empty($data['needs_challenge']) && ! empty($options['api_path'])) {
            return response()->json([
                'status' => 0,
                'type' => 'challenge',
                'message' => __('Instagram challenge required.'),
                'options' => $options,
            ]);
        }

        $authData = (array) ($options['auth_data'] ?? []);
        $externalId = trim((string) ($data['profile_id'] ?? ''));
        $username = trim((string) ($data['username'] ?? ''));
        $displayName = trim((string) ($data['name'] ?? '')) ?: ($username !== '' ? $username : __('Instagram Profile'));

        if ($externalId === '') {
            return response()->json([
                'status' => 0,
                'message' => __('Instagram did not return a profile identifier.'),
            ], 422);
        }

        SocialAccount::query()->updateOrCreate(
            $account
                ? ['id' => $account->id]
                : [
                    'created_by_user_id' => \Modules\AppTeams\Support\TeamWorkspaceAccess::workspaceOwnerUserId(auth()->user()),
                    'provider_key' => 'instagram_unofficial',
                    'capability_key' => 'instagram_unofficial_profile',
                    'external_id' => $externalId,
                ],
            [
                'created_by_user_id' => \Modules\AppTeams\Support\TeamWorkspaceAccess::workspaceOwnerUserId(auth()->user()),
                'provider_key' => 'instagram_unofficial',
                'capability_key' => 'instagram_unofficial_profile',
                'external_id' => $externalId,
                'display_name' => $displayName,
                'username' => $username,
                'category' => 'Profile',
                'account_type' => 'manual',
                'profile_url' => $username !== '' ? 'https://www.instagram.com/'.$username : 'https://www.instagram.com/',
                'avatar_url' => (string) ($data['profile_pic'] ?? ''),
                'reconnect_url' => route('portal.channels.instagram-unofficial.connect'),
                'access_token' => json_encode($authData),
                'refresh_token' => '',
                'scopes' => '',
                'auth_data' => [
                    'source' => 'instagram_unofficial',
                    'session' => $authData,
                ],
                'metadata' => [
                    'provider' => 'instagram_unofficial',
                    'requires_unofficial_login' => true,
                ],
                'is_active' => true,
                'connected_at' => now(),
            ]
        );

        return response()->json([
            'status' => 1,
            'message' => $account
                ? __('Instagram Unofficial profile reconnected successfully.')
                : __('Instagram Unofficial profile connected successfully.'),
            'redirect' => route('portal.channels', ['provider' => 'instagram_unofficial']),
        ]);
    }

    protected function resolveReconnectAccount(Request $request): ?SocialAccount
    {
        if (! $request->boolean('reconnect') || $request->integer('account') <= 0) {
            return null;
        }

        return SocialAccount::query()
            ->whereKey($request->integer('account'))
            ->where('created_by_user_id', \Modules\AppTeams\Support\TeamWorkspaceAccess::workspaceOwnerUserId(auth()->user()))
            ->where('provider_key', 'instagram_unofficial')
            ->where('capability_key', 'instagram_unofficial_profile')
            ->first();
    }
}
