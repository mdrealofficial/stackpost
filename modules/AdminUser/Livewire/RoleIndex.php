<?php

namespace Modules\AdminUser\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\AdminUser\Models\AdminRole;
use Modules\AdminUser\Models\User;

#[Title('User Roles')]
class RoleIndex extends Component
{
    public function deleteRole(int $roleId)
    {
        $role = AdminRole::query()->find($roleId);

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
            'subject_id' => $roleId,
            'metadata' => $metadata,
        ]);

        session()->flash('status', __('User role deleted successfully.'));
    }

    public function render(): View
    {
        return view('adminuser::livewire.roles-index', [
            'roles' => AdminRole::query()->withCount('users')->latest()->get(),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('User Roles'),
        ]);
    }
}
