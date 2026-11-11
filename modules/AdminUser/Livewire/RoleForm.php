<?php

namespace Modules\AdminUser\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Modules\AdminUser\Models\AdminRole;
use Modules\AdminUser\Models\User;
use Modules\AdminUser\Support\AdminPermissionCatalog;

class RoleForm extends Component
{
    public ?int $roleId = null;

    public array $form = [
        'name' => '',
        'slug' => '',
        'description' => '',
    ];

    public array $selectedPermissions = [];

    public function mount(?AdminRole $role = null): void
    {
        if ($role?->exists) {
            $this->roleId = (int) $role->id;
            $this->form = [
                'name' => (string) $role->name,
                'slug' => (string) $role->slug,
                'description' => (string) ($role->description ?? ''),
            ];
            $this->selectedPermissions = collect($role->permissions ?? [])->values()->all();
        }
    }

    public function save()
    {
        $role = $this->roleRecord();

        $validated = $this->validate([
            'form.name' => ['required', 'string', 'max:255', Rule::unique('admin_roles', 'name')->ignore($this->roleId)],
            'form.slug' => ['nullable', 'string', 'max:255', Rule::unique('admin_roles', 'slug')->ignore($this->roleId)],
            'form.description' => ['nullable', 'string'],
            'selectedPermissions' => ['nullable', 'array'],
            'selectedPermissions.*' => ['string'],
        ]);

        $payload = [
            'name' => $validated['form']['name'],
            'slug' => $this->uniqueSlug($validated['form']['slug'] ?: $validated['form']['name']),
            'description' => $validated['form']['description'] ?: null,
            'permissions' => array_values(array_unique($validated['selectedPermissions'] ?? [])),
        ];

        if ($role) {
            $role->update($payload);

            log_activity('admin.roles.update', 'Updated a user role.', [
                'subject' => $role,
                'metadata' => [
                    'role' => $role->name,
                    'permissions' => count($role->permissions ?? []),
                ],
            ]);

            session()->flash('status', __('User role updated successfully.'));

            return;
        }

        $role = AdminRole::query()->create($payload);
        $this->roleId = (int) $role->id;

        log_activity('admin.roles.create', 'Created a user role.', [
            'subject' => $role,
            'metadata' => [
                'role' => $role->name,
                'permissions' => count($role->permissions ?? []),
            ],
        ]);

        return redirect()
            ->route('admin-user-roles.edit', $role)
            ->with('status', __('User role created successfully.'));
    }

    public function deleteRole()
    {
        $role = $this->roleRecord();

        if (! $role) {
            return;
        }

        $metadata = [
            'role' => $role->name,
            'permissions' => count($role->permissions ?? []),
        ];

        User::query()->where('role_id', $role->id)->update(['role_id' => null]);
        $role->delete();

        log_activity('admin.roles.delete', 'Deleted a user role.', [
            'subject_type' => AdminRole::class,
            'subject_id' => $this->roleId,
            'metadata' => $metadata,
        ]);

        return redirect()
            ->route('admin-user-roles.index')
            ->with('status', __('User role deleted successfully.'));
    }

    public function render(): View
    {
        $role = $this->roleRecord();

        return view('adminuser::livewire.role-form', [
            'role' => $role ?? new AdminRole(),
            'isEditing' => $role !== null,
            'permissionGroups' => AdminPermissionCatalog::groups(),
            'users' => $role
                ? User::query()->where('role_id', $role->id)->orderBy('name')->get(['id', 'name', 'username', 'email'])
                : collect(),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => $role ? __('Edit user role') : __('Create user role'),
        ]);
    }

    protected function roleRecord(): ?AdminRole
    {
        return $this->roleId ? AdminRole::query()->find($this->roleId) : null;
    }

    protected function uniqueSlug(string $value): string
    {
        $baseSlug = Str::slug($value);
        $slug = $baseSlug !== '' ? $baseSlug : 'role';
        $counter = 2;

        while (
            AdminRole::query()
                ->where('slug', $slug)
                ->when($this->roleId, fn ($query) => $query->whereKeyNot($this->roleId))
                ->exists()
        ) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
