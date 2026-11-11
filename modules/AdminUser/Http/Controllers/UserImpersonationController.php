<?php

namespace Modules\AdminUser\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\AdminUser\Models\User;

class UserImpersonationController extends Controller
{
    public function store(Request $request, User $user): RedirectResponse
    {
        /** @var User|null $actor */
        $actor = $request->user();

        abort_if($request->session()->has('impersonator_id'), 403);
        abort_unless($user->canBeImpersonatedBy($actor), 403);

        $request->session()->put('impersonator_id', $actor->getKey());
        $request->session()->put('impersonator_name', $actor->name);
        $request->session()->put('impersonator_redirect', route('admin-users.index'));

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        log_activity('admin.users.impersonation.start', 'Started viewing the application as another user.', [
            'subject' => $user,
            'causer_user_id' => $actor->getKey(),
            'metadata' => [
                'impersonator_id' => $actor->getKey(),
                'impersonator_username' => $actor->username,
                'target_user_id' => $user->getKey(),
                'target_username' => $user->username,
            ],
        ]);

        return redirect()
            ->route('portal.dashboard')
            ->with('status', __('You are now viewing the app as :user.', ['user' => $user->name]));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $impersonatorId = $request->session()->pull('impersonator_id');
        $impersonatorName = $request->session()->pull('impersonator_name');
        $redirectTo = $request->session()->pull('impersonator_redirect', route('admin-users.index'));

        abort_unless($impersonatorId, 403);

        /** @var User|null $currentUser */
        $currentUser = $request->user();
        $currentUserId = $currentUser?->getKey();
        $currentUsername = $currentUser?->username;

        $restored = Auth::guard('web')->loginUsingId($impersonatorId);

        if (! $restored) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/')
                ->with('error', __('The original admin account could not be restored.'));
        }

        $request->session()->regenerate();

        if ($currentUser) {
            log_activity('admin.users.impersonation.stop', 'Stopped viewing the application as another user.', [
                'subject' => $currentUser,
                'causer_user_id' => $impersonatorId,
                'metadata' => [
                    'impersonator_id' => $impersonatorId,
                    'target_user_id' => $currentUserId,
                    'target_username' => $currentUsername,
                ],
            ]);
        }

        return redirect()
            ->to($redirectTo)
            ->with('status', __('Returned to admin account: :name.', ['name' => $impersonatorName ?? 'admin']));
    }
}
