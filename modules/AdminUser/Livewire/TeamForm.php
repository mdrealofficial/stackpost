<?php

namespace Modules\AdminUser\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Component;
use Modules\AdminUser\Models\Team;
use Modules\AdminUser\Models\User;

class TeamForm extends Component
{
    public ?int $teamId = null;

    public array $form = [
        'name' => '',
        'description' => '',
    ];

    public array $memberForm = [
        'user_id' => '',
        'role' => 'member',
    ];

    public function mount(?Team $team = null): void
    {
        abort_unless($team?->exists, 404);

        $this->teamId = (int) $team->id;
        $this->form = [
            'name' => (string) $team->name,
            'description' => (string) ($team->description ?? ''),
        ];
    }

    public function save()
    {
        $team = $this->teamRecord();

        $validated = $this->validate([
            'form.name' => ['required', 'string', 'max:255'],
            'form.description' => ['nullable', 'string'],
        ]);

        $payload = [
            'name' => $validated['form']['name'],
            'description' => $validated['form']['description'] ?: null,
            'slug' => $this->uniqueSlug($validated['form']['name']),
        ];

        if ($team) {
            $team->update($payload);

            log_activity('admin.teams.update', 'Updated a team.', [
                'subject' => $team,
                'metadata' => [
                    'team' => $team->name,
                    'owner_user_id' => $team->owner_user_id,
                ],
            ]);

            session()->flash('status', __('Team updated successfully.'));

            return;
        }

        return;
    }

    public function addMember(): void
    {
        $team = $this->teamRecord();

        if (! $team) {
            return;
        }

        $validated = $this->validate([
            'memberForm.user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'memberForm.role' => ['required', 'string', 'max:50'],
        ]);

        $userId = (int) $validated['memberForm']['user_id'];

        if ($team->members()->where('users.id', $userId)->exists()) {
            $this->addError('memberForm.user_id', __('This user is already a member of the selected team.'));

            return;
        }

        $team->members()->attach($userId, ['role' => $validated['memberForm']['role']]);

        $member = User::query()->find($userId);

        log_activity('admin.teams.add_member', 'Added a member to a team.', [
            'subject' => $team,
            'metadata' => [
                'team' => $team->name,
                'member' => $member?->username,
                'role' => $validated['memberForm']['role'],
            ],
        ]);

        $this->memberForm = ['user_id' => '', 'role' => 'member'];
        session()->flash('status', __('Member added successfully.'));
    }

    public function removeMember(int $userId): void
    {
        $team = $this->teamRecord();
        $user = User::query()->find($userId);

        if (! $team || ! $user) {
            return;
        }

        if ((int) $team->owner_user_id === (int) $user->id) {
            session()->flash('error', __('The team owner cannot be removed from members.'));

            return;
        }

        $team->members()->detach($user->id);

        log_activity('admin.teams.remove_member', 'Removed a member from a team.', [
            'subject' => $team,
            'metadata' => [
                'team' => $team->name,
                'member' => $user->username,
            ],
        ]);

        session()->flash('status', __('Member removed successfully.'));
    }

    public function render(): View
    {
        $team = $this->teamRecord();

        if ($team) {
            $team->load(['owner', 'members']);
        }

        return view('adminuser::livewire.team-form', [
            'team' => $team ?? new Team(),
            'isEditing' => $team !== null,
            'availableUsers' => $this->eligibleMembers(),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Edit team'),
        ]);
    }

    protected function teamRecord(): ?Team
    {
        return $this->teamId ? Team::query()->find($this->teamId) : null;
    }

    protected function uniqueSlug(string $value): string
    {
        $baseSlug = Str::slug($value);
        $slug = $baseSlug !== '' ? $baseSlug : 'team';
        $counter = 2;

        while (
            Team::query()
                ->where('slug', $slug)
                ->when($this->teamId, fn ($query) => $query->whereKeyNot($this->teamId))
                ->exists()
        ) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    protected function eligibleMembers()
    {
        return User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'username', 'email']);
    }
}
