<?php

namespace Modules\AdminUser\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\AdminUser\Models\User;

#[Title('Admin Users')]
class UserIndex extends Component
{
    use WithPagination;

    public array $selectedUserIds = [];

    #[Url(as: 'q', except: '')]
    public string $q = '';

    #[Url(as: 'status', except: 'all')]
    public string $status = 'all';

    #[Url(as: 'sort', except: 'created')]
    public string $sort = 'created';

    #[Url(as: 'direction', except: 'desc')]
    public string $direction = 'desc';

    public function updatedQ(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['q', 'status', 'sort', 'direction']);
        $this->resetPage();
    }

    public function sortBy(string $key): void
    {
        if ($this->sort === $key) {
            $this->direction = $this->direction === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sort = $key;
            $this->direction = 'asc';
        }

        $this->resetPage();
    }

    public function deleteUser(int $userId): void
    {
        $user = User::query()->find($userId);

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
            'subject_id' => $userId,
            'metadata' => $metadata,
        ]);

        $this->selectedUserIds = array_values(array_filter($this->selectedUserIds, fn ($id) => (int) $id !== $userId));
        session()->flash('status', __('User deleted successfully.'));
    }

    public function deleteSelectedUsers(): void
    {
        $selectedIds = collect($this->selectedUserIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($selectedIds->isEmpty()) {
            session()->flash('error', __('Select at least one account to delete.'));

            return;
        }

        $currentUserId = (int) auth()->id();
        $deletableIds = $selectedIds->reject(fn (int $id) => $id === $currentUserId)->values();

        if ($deletableIds->isEmpty()) {
            session()->flash('error', __('Select at least one other account to delete.'));

            return;
        }

        $deletedUsers = User::query()
            ->whereIn('id', $deletableIds)
            ->get(['id', 'username', 'email']);

        if ($deletedUsers->isEmpty()) {
            session()->flash('error', __('No matching users were available for deletion.'));

            return;
        }

        User::query()->whereIn('id', $deletedUsers->pluck('id'))->delete();

        log_activity('admin.users.bulk-delete', 'Deleted backend users in bulk.', [
            'metadata' => [
                'count' => $deletedUsers->count(),
                'usernames' => $deletedUsers->pluck('username')->filter()->values()->all(),
                'emails' => $deletedUsers->pluck('email')->filter()->values()->all(),
            ],
        ]);

        $this->selectedUserIds = [];
        session()->flash('status', __('Deleted :count users successfully.', ['count' => $deletedUsers->count()]));
    }

    public function render(): View
    {
        $filters = [
            'q' => trim($this->q),
            'status' => $this->status,
            'sort' => $this->sort,
            'direction' => $this->direction,
        ];

        $sortMap = [
            'user' => 'name',
            'access' => 'role_id',
            'plan' => 'plan_id',
            'contact' => 'email',
            'created' => 'created_at',
        ];

        $sortColumn = $sortMap[$filters['sort']] ?? $sortMap['created'];
        $sortDirection = $filters['direction'] === 'asc' ? 'asc' : 'desc';

        $query = User::query()
            ->with('role')
            ->with('plan');

        if ($filters['q'] !== '') {
            $query->where(function ($builder) use ($filters): void {
                $builder
                    ->where('name', 'like', '%'.$filters['q'].'%')
                    ->orWhere('username', 'like', '%'.$filters['q'].'%')
                    ->orWhere('email', 'like', '%'.$filters['q'].'%');
            });
        }

        if ($filters['status'] === 'ready') {
            $query
                ->whereNotNull('email')
                ->whereNotNull('role_id')
                ->whereNotNull('plan_id');
        } elseif ($filters['status'] === 'review') {
            $query->where(function ($builder): void {
                $builder
                    ->whereNull('email')
                    ->orWhereNull('role_id')
                    ->orWhereNull('plan_id');
            });
        }

        $users = $query
            ->orderBy($sortColumn, $sortDirection)
            ->orderBy('id', 'desc')
            ->paginate(15, ['id', 'name', 'username', 'email', 'locale', 'role_id', 'plan_id', 'plan_expires_at', 'created_at']);

        return view('adminuser::livewire.index', [
            'users' => $users,
            'filters' => $filters,
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Admin Users'),
        ]);
    }
}
