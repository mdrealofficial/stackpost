<?php

namespace Modules\AppProfile\Http\Controllers;

use App\Concerns\PasswordValidationRules;
use App\Support\Storage\StorageDriverManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\AdminSettings\Support\OptionStore;
use Modules\AdminUser\Models\User;

class AppProfileController extends Controller
{
    use PasswordValidationRules;

    public function __construct(
        protected StorageDriverManager $storageDriverManager,
    ) {}

    public function index(Request $request): View
    {
        return view('appprofile::index', [
            'user' => $request->user(),
            'languages' => $this->languages(),
            'defaultLocale' => $this->defaultLocale(),
            'timezoneOptions' => timezone_select_options(),
            'profilePolicies' => $this->profilePolicies(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $policies = $this->profilePolicies();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => $policies['can_change_username']
                ? [
                    'required',
                    'string',
                    'min:3',
                    'max:50',
                    'alpha_dash',
                    Rule::unique('users', 'username')->ignore($user->id),
                ]
                : ['nullable'],
            'email' => $policies['can_change_email']
                ? [
                    'required',
                    'string',
                    'email',
                    'max:255',
                    Rule::unique('users', 'email')->ignore($user->id),
                ]
                : ['nullable'],
            'locale' => ['nullable', 'string', 'max:10'],
            'timezone' => ['nullable', 'timezone:all', Rule::in(timezone_options())],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        if (! $policies['can_change_username']) {
            $validated['username'] = $user->username;
        }

        if (! $policies['can_change_email']) {
            $validated['email'] = $user->email;
        }

        if ($user->email !== $validated['email']) {
            $validated['email_verified_at'] = null;
        }

        unset($validated['avatar']);

        if ($request->hasFile('avatar')) {
            $validated = array_merge($validated, $this->storeAvatar($user, $request));
        }

        $user->update($validated);

        log_activity('profile.update', 'Updated profile settings.', [
            'subject' => $user,
            'area' => 'user',
            'metadata' => [
                'username' => $user->username,
                'email' => $user->email,
                'locale' => $user->locale,
                'timezone' => $user->timezone,
            ],
        ]);

        return redirect()
            ->route('portal.profile')
            ->with('status', __('Profile updated successfully.'));
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => $this->currentPasswordRules(),
            'password' => $this->passwordRules(),
        ]);

        $user->update([
            'password' => $validated['password'],
        ]);

        log_activity('profile.password.update', 'Updated profile password.', [
            'subject' => $user,
            'area' => 'user',
        ]);

        return redirect()
            ->route('portal.profile')
            ->with('status', __('Password updated successfully.'));
    }

    protected function languages()
    {
        if (! function_exists('available_languages')) {
            return collect();
        }

        return available_languages();
    }

    protected function defaultLocale(): string
    {
        if (function_exists('locale_manager')) {
            return locale_manager()->defaultCode();
        }

        return (string) config('app.fallback_locale', 'en');
    }
    protected function profilePolicies(): array
    {
        /** @var OptionStore $options */
        $options = app(OptionStore::class);

        return [
            'can_change_email' => $options->get('auth_user_change_email_status', '0') === '1',
            'can_change_username' => $options->get('auth_user_change_username_status', '0') === '1',
        ];
    }

    protected function storeAvatar(User $user, Request $request): array
    {
        $file = $request->file('avatar');
        $disk = $this->resolveAvatarDisk();
        $directory = 'avatars/users/'.$user->getKey();
        $filename = now()->format('YmdHis').'-'.str()->random(10).'.'.$file->guessExtension();
        $path = $file->storeAs($directory, $filename, $disk);

        $this->storageDriverManager->deleteIfPresent($user->avatar_disk ?: 'public', $user->avatar_path);

        return [
            'avatar_path' => $path,
            'avatar_disk' => $disk,
        ];
    }

    protected function resolveAvatarDisk(): string
    {
        $disk = $this->storageDriverManager->activeDisk();

        try {
            $this->storageDriverManager->ensureDiskReadyForWrites($disk);

            return $disk;
        } catch (\Throwable) {
            return 'public';
        }
    }
}
