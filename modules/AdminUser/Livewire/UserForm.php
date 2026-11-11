<?php

namespace Modules\AdminUser\Livewire;

use App\Support\Storage\StorageDriverManager;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;
use Modules\AdminPlans\Models\AdminPlan;
use Modules\AdminPlans\Support\DefaultSignupPlanResolver;
use Modules\AdminUser\Models\AdminRole;
use Modules\AdminUser\Models\User;
use Modules\AdminUser\Support\PersonalTeamProvisioner;

class UserForm extends Component
{
    use WithFileUploads;

    protected StorageDriverManager $storageDriverManager;

    public ?int $userId = null;

    public array $form = [
        'name' => '',
        'username' => '',
        'email' => '',
        'locale' => '',
        'timezone' => '',
        'role_id' => '',
        'is_super_admin' => false,
        'plan_id' => '',
        'plan_expires_at' => '',
    ];

    public $avatar = null;

    public string $password = '';

    public string $password_confirmation = '';

    public function boot(StorageDriverManager $storageDriverManager): void
    {
        $this->storageDriverManager = $storageDriverManager;
    }

    public function mount(?User $user = null): void
    {
        $defaultTimezone = (string) config('app.timezone', 'UTC');

        if ($user?->exists) {
            $this->userId = (int) $user->id;
            $this->form = [
                'name' => (string) $user->name,
                'username' => (string) $user->username,
                'email' => (string) $user->email,
                'locale' => (string) ($user->locale ?? ''),
                'timezone' => (string) ($user->timezone ?: $defaultTimezone),
                'role_id' => $user->role_id ? (string) $user->role_id : '',
                'is_super_admin' => (bool) $user->is_super_admin,
                'plan_id' => $user->plan_id ? (string) $user->plan_id : '',
                'plan_expires_at' => $user->plan_expires_at?->format('Y-m-d') ?: '',
            ];
        } else {
            $this->form['timezone'] = $defaultTimezone;
        }
    }

    public function save()
    {
        $user = $this->userRecord();

        $validated = $this->validate($this->rules());
        $isSuperAdmin = (bool) $validated['form']['is_super_admin'];

        $defaultSignupPlan = null;
        $defaultSignupPlanResolver = app(DefaultSignupPlanResolver::class);
        if (! $user && ! filled($validated['form']['plan_id'])) {
            $defaultSignupPlan = $defaultSignupPlanResolver->resolve();
        }

        $payload = [
            'name' => $validated['form']['name'],
            'username' => $validated['form']['username'],
            'email' => $validated['form']['email'],
            'email_verified_at' => $user?->email_verified_at,
            'locale' => $validated['form']['locale'] ?: null,
            'timezone' => $validated['form']['timezone'] ?: null,
            'role_id' => $isSuperAdmin ? null : (filled($validated['form']['role_id']) ? (int) $validated['form']['role_id'] : null),
            'is_super_admin' => $isSuperAdmin,
            'plan_id' => filled($validated['form']['plan_id']) ? (int) $validated['form']['plan_id'] : $defaultSignupPlan?->id,
            'plan_expires_at' => $validated['form']['plan_expires_at'] ?: null,
        ];

        if (filled($validated['password'] ?? null)) {
            $payload['password'] = $validated['password'];
        }

        if (array_key_exists('plan_id', $payload)) {
            $payload['next_plan_id'] = null;
        }

        if (! $payload['plan_id']) {
            $payload['plan_started_at'] = null;
            $payload['plan_expires_at'] = null;
        } elseif (! $user) {
            $payload['plan_started_at'] = now();
        }

        if (! $user && $defaultSignupPlan) {
            $payload['plan_expires_at'] = $defaultSignupPlanResolver->expiresAt($defaultSignupPlan, $payload['plan_started_at']);
        }

        if (! $user) {
            $payload['email_verified_at'] = now();
        }

        if ($this->avatar && $user) {
            $payload = array_merge($payload, $this->storeAvatar($user));
        }

        if ($user) {
            $user->update($payload);

            log_activity('admin.users.update', 'Updated a backend user.', [
                'subject' => $user,
                'metadata' => [
                    'username' => $user->username,
                    'email' => $user->email,
                ],
            ]);

            session()->flash('status', __('User updated successfully.'));

            return;
        }

        $user = User::query()->create($payload);
        $this->userId = (int) $user->id;

        if ($this->avatar) {
            $user->update($this->storeAvatar($user));
        }

        $team = app(PersonalTeamProvisioner::class)->ensureForUser($user);

        log_activity('admin.users.create', 'Created a backend user.', [
            'subject' => $user,
            'metadata' => [
                'username' => $user->username,
                'email' => $user->email,
                'team' => $team->name,
                'plan' => $defaultSignupPlan?->name ?: ($user->plan?->name),
            ],
        ]);

        return redirect()
            ->route('admin-users.edit', $user)
            ->with('status', __('User created successfully.'));
    }

    public function deleteUser()
    {
        $user = $this->userRecord();

        if (! $user) {
            return;
        }

        if ((int) auth()->id() === (int) $user->id) {
            session()->flash('error', __('You cannot delete the account currently signed in.'));

            return;
        }

        $metadata = [
            'username' => $user->username,
            'email' => $user->email,
        ];

        $user->delete();

        log_activity('admin.users.delete', 'Deleted a backend user.', [
            'subject_type' => User::class,
            'subject_id' => $this->userId,
            'metadata' => $metadata,
        ]);

        return redirect()
            ->route('admin-users.index')
            ->with('status', __('User deleted successfully.'));
    }

    public function render(): View
    {
        $user = $this->userRecord();
        $languages = function_exists('available_languages') ? available_languages() : collect();
        $defaultLocale = function_exists('locale_manager') ? locale_manager()->defaultCode() : (string) config('app.fallback_locale', 'en');
        $activeTimezone = (string) ($this->form['timezone'] ?: ($user?->timezone ?: config('app.timezone', 'UTC')));

        if ($languages->isEmpty()) {
            $languages = collect([
                (object) [
                    'code' => $defaultLocale,
                    'name' => strtoupper($defaultLocale),
                    'native_name' => strtoupper($defaultLocale),
                ],
            ]);
        }

        return view('adminuser::livewire.user-form', [
            'user' => $user ?? new User(),
            'isEditing' => $user !== null,
            'authUser' => auth()->user(),
            'roles' => AdminRole::query()->orderBy('name')->get(['id', 'name']),
            'plans' => AdminPlan::query()->where('status', true)->orderBy('position')->orderBy('name')->get(['id', 'name']),
            'languages' => $languages,
            'defaultLocale' => $defaultLocale,
            'timezoneOptions' => timezone_select_options(),
            'activeTimezoneLabel' => timezone_label($activeTimezone),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => $user ? __('Edit user') : __('Create user'),
        ]);
    }

    protected function userRecord(): ?User
    {
        return $this->userId ? User::query()->find($this->userId) : null;
    }

    protected function rules(): array
    {
        $passwordRules = $this->userId
            ? ['nullable', 'string', 'min:8', 'same:password_confirmation']
            : ['required', 'string', 'min:8', 'same:password_confirmation'];

        return [
            'form.name' => ['required', 'string', 'max:255'],
            'form.username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($this->userId)],
            'form.email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->userId)],
            'form.locale' => ['nullable', 'string', 'max:10'],
            'form.timezone' => ['required', 'string', 'timezone:all', Rule::in(timezone_options())],
            'form.role_id' => ['nullable', 'integer', Rule::exists('admin_roles', 'id')],
            'form.is_super_admin' => ['required', 'boolean'],
            'form.plan_id' => ['nullable', 'integer', Rule::exists('plans', 'id')],
            'form.plan_expires_at' => ['nullable', 'date'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'password' => $passwordRules,
            'password_confirmation' => $this->userId ? ['nullable', 'string', 'min:8'] : ['required', 'string', 'min:8'],
        ];
    }

    public function updatedFormIsSuperAdmin($value): void
    {
        if (filter_var($value, FILTER_VALIDATE_BOOL)) {
            $this->form['role_id'] = '';
        }
    }

    protected function storeAvatar(?User $user = null): array
    {
        $targetUser = $user ?? $this->userRecord();

        if (! $targetUser || ! $this->avatar) {
            return [
                'avatar_path' => (string) ($targetUser?->avatar_path ?? ''),
                'avatar_disk' => (string) ($targetUser?->avatar_disk ?? 'public'),
            ];
        }

        $disk = $this->resolveAvatarDisk();
        $directory = 'avatars/users/'.$targetUser->getKey();
        $filename = now()->format('YmdHis').'-'.str()->random(10).'.'.$this->avatar->guessExtension();
        $path = $this->avatar->storeAs($directory, $filename, $disk);

        $this->storageDriverManager->deleteIfPresent($targetUser->avatar_disk ?: 'public', $targetUser->avatar_path);

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
