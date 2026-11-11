<?php

namespace Modules\AppGroups\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\AppChannels\Models\SocialAccount;
use Modules\AppGroups\Models\AccountGroup;
use Modules\AppTeams\Support\TeamWorkspaceAccess;

#[Title('Groups')]
class GroupWorkspace extends Component
{
    use WithPagination;

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }
    public function updatingSourceFilter(): void { $this->resetPage(); }

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'status')]
    public string $statusFilter = '';

    public ?int $editingId = null;

    public bool $editorOpen = false;

    public string $name = '';

    public string $description = '';

    public string $color = '#2563eb';

    public string $groupStatus = 'active';

    public array $accountIds = [];

    public function mount(Request $request): void
    {
        abort_unless($this->groupsEnabled(), 404);

        $editId = $request->integer('edit');
        if ($editId > 0) {
            $this->editGroup($editId);
        }
    }

    public function save(): void
    {
        abort_unless(\Modules\AppTeams\Support\PortalLibraryAccess::canManage('channel.manage'), 403);
        abort_unless($this->groupsEnabled(), 404);

        $user = auth()->user();
        $workspaceOwnerUserId = $this->workspaceOwnerUserId();
        $accessibleAccountIds = $this->accessibleAccountIds();
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'color' => ['required', 'string', 'max:24'],
            'groupStatus' => ['required', Rule::in(['active', 'inactive'])],
            'accountIds' => ['nullable', 'array'],
            'accountIds.*' => [
                'integer',
                Rule::in($accessibleAccountIds),
            ],
        ], [], [
            'groupStatus' => __('status'),
            'accountIds' => __('accounts'),
        ]);

        $payload = [
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug($validated['name'], $workspaceOwnerUserId, $this->editingId),
            'description' => trim((string) ($validated['description'] ?? '')) ?: null,
            'color' => $validated['color'],
            'status' => $validated['groupStatus'],
        ];

        if ($this->editingId) {
            $group = $this->groupQuery()->findOrFail($this->editingId);
            $group->update($payload);
            $group->accounts()->sync(array_values($validated['accountIds'] ?? []));

            $this->dispatch('app-toast', type: 'success', message: __('Group updated successfully.'));
        } else {
            $group = AccountGroup::query()->create($payload + [
                'owner_user_id' => $workspaceOwnerUserId,
                'team_id' => $this->currentTeamId($user),
            ]);

            $group->accounts()->sync(array_values($validated['accountIds'] ?? []));

            $this->dispatch('app-toast', type: 'success', message: __('Group created successfully.'));
        }

        $this->editorOpen = false;
        $this->resetEditor();
    }

    public function editGroup(int $groupId): void
    {
        abort_unless(\Modules\AppTeams\Support\PortalLibraryAccess::canManage('channel.manage'), 403);
        abort_unless($this->groupsEnabled(), 404);

        $group = $this->groupQuery()->with('accounts')->findOrFail($groupId);

        $this->editingId = (int) $group->id;
        $this->name = (string) $group->name;
        $this->description = (string) ($group->description ?? '');
        $this->color = (string) ($group->color ?: '#2563eb');
        $this->groupStatus = in_array($group->status, ['active', 'inactive'], true) ? (string) $group->status : 'inactive';
        $this->accountIds = $group->accounts->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->editorOpen = true;

        $this->resetErrorBag();
        $this->resetValidation();
    }

    public function deleteGroup(int $groupId): void
    {
        abort_unless(\Modules\AppTeams\Support\PortalLibraryAccess::canManage('channel.manage'), 403);
        abort_unless($this->groupsEnabled(), 404);

        $group = $this->groupQuery()->findOrFail($groupId);
        $wasEditing = $this->editingId === (int) $group->id;
        $group->delete();

        if ($wasEditing) {
            $this->resetEditor();
        }

        $this->dispatch('app-toast', type: 'success', message: __('Group deleted successfully.'));
    }

    public function resetEditor(): void
    {
        $this->editorOpen = false;
        $this->editingId = null;
        $this->name = '';
        $this->description = '';
        $this->color = '#2563eb';
        $this->groupStatus = 'active';
        $this->accountIds = [];
        $this->resetErrorBag();
        $this->resetValidation();
    }

    public function resetFilters(): void
    {
        $this->resetPage();
        $this->search = '';
        $this->statusFilter = '';
    }

    public function openCreateEditor(): void
    {
        abort_unless(\Modules\AppTeams\Support\PortalLibraryAccess::canManage('channel.manage'), 403);
        $this->resetEditor();
        $this->editorOpen = true;
    }

    public function render(): View
    {
        abort_unless($this->groupsEnabled(), 404);

        $baseQuery = $this->groupQuery()->withCount('accounts')->with('accounts');

        $groups = (clone $baseQuery)
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($nested): void {
                    $nested
                        ->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('description', 'like', '%'.$this->search.'%')
                        ->orWhere('slug', 'like', '%'.$this->search.'%');
                });
            })
            ->when(in_array($this->statusFilter, ['active', 'inactive'], true), function ($query): void {
                if ($this->statusFilter === 'active') {
                    $query->where('status', 'active');

                    return;
                }

                $query->whereIn('status', ['inactive', 'draft', 'archived']);
            })
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(18);

        $accounts = SocialAccount::query()
            ->whereIn('id', $this->accessibleAccountIds())
            ->where('is_active', true)
            ->orderBy('display_name')
            ->get();

        return view('appgroups::livewire.index', [
            'groups' => $groups,
            'accounts' => $accounts,
            'canManage' => \Modules\AppTeams\Support\PortalLibraryAccess::canManage('channel.manage'),
            'stats' => [
                'total' => (clone $baseQuery)->count(),
                'active' => (clone $baseQuery)->where('status', 'active')->count(),
                'inactive' => (clone $baseQuery)->whereIn('status', ['inactive', 'draft', 'archived'])->count(),
                'reach' => (clone $baseQuery)->get()->flatMap(fn ($group) => $group->accounts->pluck('id'))->unique()->count(),
            ],
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Groups'),
        ]);
    }

    protected function groupQuery()
    {
        return \Modules\AppTeams\Support\PortalLibraryAccess::query(AccountGroup::query())->whereDoesntHave('accounts', fn ($q) => $q->whereNotIn('social_accounts.id', $this->accessibleAccountIds()));
    }

    protected function currentTeamId($user): ?int
    {
        return TeamWorkspaceAccess::activeTeam($user)?->id;
    }

    protected function workspaceOwnerUserId(): int
    {
        return TeamWorkspaceAccess::workspaceOwnerUserId(auth()->user());
    }

    protected function accessibleAccountIds(): array
    {
        return TeamWorkspaceAccess::accessibleAccountsQuery(auth()->user())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    protected function uniqueSlug(string $value, int $userId, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($value) ?: 'group';
        $slug = $baseSlug;
        $counter = 2;

        while (AccountGroup::query()
            ->ownedBy($userId)
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $baseSlug.'-'.$counter++;
        }

        return $slug;
    }

    protected function groupsEnabled(): bool
    {
        $user = auth()->user();
        $team = TeamWorkspaceAccess::activeTeam($user);
        $planOwner = $team?->owner ?: $user;

        return ! $planOwner?->plan || $planOwner->hasPlanFeature('groups');
    }
}
