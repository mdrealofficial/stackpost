<?php

namespace Modules\AdminSupport\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Component;
use Modules\AdminSupport\Models\SupportCategory;
use Modules\AdminSupport\Models\SupportLabel;
use Modules\AdminSupport\Models\SupportTicket;
use Modules\AdminSupport\Models\SupportType;
use Modules\AdminUser\Models\User;

class SupportCreate extends Component
{
    public string $uid = '';

    public string $userSearch = '';

    public string $cateId = '';

    public string $typeId = '';

    public string $title = '';

    public string $content = '';

    public string $status = '1';

    public string $pin = '0';

    public array $labelIds = [];

    public ?string $statusMessage = null;

    public function mount(): void
    {
        if ($this->uid !== '') {
            $this->syncUserSearchLabel();
        }
    }

    public function updatedUid(): void
    {
        $this->syncUserSearchLabel();
    }

    public function selectUser(int $userId): void
    {
        $user = User::query()->find($userId, ['id', 'name', 'username', 'email']);

        if (! $user) {
            return;
        }

        $this->uid = (string) $user->id;
        $this->userSearch = $this->formatUserLabel($user);
        $this->resetValidation('uid');
    }

    public function clearSelectedUser(): void
    {
        $this->uid = '';
        $this->userSearch = '';
    }

    public function save(): mixed
    {
        $validated = $this->validate([
            'uid' => ['required', 'integer', 'exists:users,id'],
            'cateId' => ['nullable', 'integer', 'exists:support_categories,id'],
            'typeId' => ['nullable', 'integer', 'exists:support_types,id'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:10000'],
            'status' => ['required', 'in:0,1,2'],
            'pin' => ['required', 'in:0,1'],
            'labelIds' => ['nullable', 'array'],
            'labelIds.*' => ['integer', 'exists:support_labels,id'],
        ]);

        $ticket = SupportTicket::query()->create([
            'id_secure' => Str::random(32),
            'uid' => (int) $validated['uid'],
            'open_by' => (int) auth()->id(),
            'cate_id' => $validated['cateId'] !== '' ? (int) $validated['cateId'] : null,
            'type_id' => $validated['typeId'] !== '' ? (int) $validated['typeId'] : null,
            'title' => trim($validated['title']),
            'content' => trim($validated['content']),
            'status' => (int) $validated['status'],
            'pin' => (bool) ((int) $validated['pin']),
            'user_read' => true,
            'admin_read' => false,
            'changed' => time(),
            'created' => time(),
        ]);

        $ticket->labels()->sync($validated['labelIds'] ?? []);

        log_activity('admin.support.create', 'Created a support ticket.', [
            'subject_type' => SupportTicket::class,
            'subject_id' => $ticket->id,
            'metadata' => [
                'ticket' => $ticket->id_secure,
                'user_id' => $ticket->uid,
            ],
        ]);

        return $this->redirect(route('admin-support.show', $ticket), navigate: true);
    }

    public function render(): View
    {
        $selectedUser = $this->uid !== ''
            ? User::query()->find($this->uid, ['id', 'name', 'username', 'email'])
            : null;

        $search = trim($this->userSearch);

        $userResults = collect();

        if ($search !== '') {
            $userResults = User::query()
                ->when($this->uid !== '', fn ($query) => $query->where('id', '!=', (int) $this->uid))
                ->where(function ($query) use ($search): void {
                    $query
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('username', 'like', '%'.$search.'%');
                })
                ->orderBy('name')
                ->limit(8)
                ->get(['id', 'name', 'username', 'email']);
        }

        return view('adminsupport::livewire.create', [
            'selectedUser' => $selectedUser,
            'userResults' => $userResults,
            'categories' => SupportCategory::query()->where('status', true)->orderBy('name')->get(),
            'types' => SupportType::query()->where('status', true)->orderBy('name')->get(),
            'labels' => SupportLabel::query()->where('status', true)->orderBy('name')->get(),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('New Ticket'),
        ]);
    }

    protected function syncUserSearchLabel(): void
    {
        if ($this->uid === '') {
            return;
        }

        $user = User::query()->find($this->uid, ['id', 'name', 'username', 'email']);

        if (! $user) {
            $this->uid = '';
            $this->userSearch = '';

            return;
        }

        $this->userSearch = $this->formatUserLabel($user);
    }

    protected function formatUserLabel(User $user): string
    {
        return trim($user->name.($user->email ? ' - '.$user->email : ''));
    }
}
