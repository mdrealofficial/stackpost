<?php

namespace Modules\AdminNotifications\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Modules\AdminNotifications\Models\Notification;
use Modules\AdminNotifications\Models\NotificationManual;
use Modules\AdminNotifications\Models\NotificationManualState;
use Modules\AdminNotifications\Services\NotificationService;
use Modules\AdminUser\Models\User;

class NotificationForm extends Component
{
    public ?NotificationManual $notification = null;

    public string $title = '';

    public string $message = '';

    public string $url = '';

    public bool $is_global = false;

    public string $userQuery = '';

    /** @var array<int, string> */
    public array $user_ids = [];

    public ?string $statusMessage = null;

    public bool $isEditing = false;

    public function mount(?NotificationManual $notification = null): void
    {
        $this->notification = $notification?->exists ? $notification->loadCount('notifications') : null;
        $this->isEditing = $this->notification !== null;

        if (! $this->notification) {
            return;
        }

        $this->title = (string) ($this->notification->title ?? '');
        $this->message = (string) $this->notification->message;
        $this->url = (string) ($this->notification->url ?? '');
        $this->is_global = (bool) $this->notification->is_global;
    }

    public function addUser(string $userId): void
    {
        if ($this->isEditing || $this->is_global) {
            return;
        }

        if (! in_array($userId, $this->user_ids, true)) {
            $this->user_ids[] = $userId;
        }

        $this->userQuery = '';
    }

    public function removeUser(string $userId): void
    {
        if ($this->isEditing) {
            return;
        }

        $this->user_ids = array_values(array_filter($this->user_ids, fn ($id) => $id !== $userId));
    }

    public function updatedIsGlobal(bool $value): void
    {
        if ($value) {
            $this->user_ids = [];
        }
    }

    public function save(NotificationService $notificationService): mixed
    {
        $validated = $this->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'url' => ['nullable', 'url', 'max:255'],
            'is_global' => ['boolean'],
            'user_ids' => [$this->isEditing ? 'nullable' : 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        if (! $this->isEditing && ! $validated['is_global'] && ($validated['user_ids'] ?? []) === []) {
            $this->addError('user_ids', __('Select at least one user or enable global notification.'));
            return null;
        }

        if ($this->notification) {
            $this->notification->update([
                'title' => $validated['title'] ?: null,
                'message' => $validated['message'],
                'url' => $validated['url'] ?: null,
                'type' => 'news',
                'updated_at' => now(),
            ]);

            Notification::query()
                ->where('source', 'manual')
                ->where('mid', $this->notification->id)
                ->update([
                    'url' => $validated['url'] ?: null,
                    'updated_at' => now(),
                ]);

            log_activity('admin.notifications.update', 'Updated a manual notification.', [
                'subject_type' => NotificationManual::class,
                'subject_id' => $this->notification->id,
                'metadata' => [
                    'title' => $this->notification->title,
                ],
            ]);

            $this->statusMessage = __('Notification updated successfully.');

            return null;
        }

        $this->notification = $notificationService->sendManual(
            array_values(array_map('intval', $validated['user_ids'] ?? [])),
            $validated['title'] ?: null,
            $validated['message'],
            $validated['url'] ?: null,
            auth()->id(),
            'news',
            (bool) $validated['is_global'],
        );
        $this->notification->loadCount('notifications');
        $this->isEditing = true;

        log_activity('admin.notifications.create', 'Created a manual notification.', [
            'subject_type' => NotificationManual::class,
            'subject_id' => $this->notification->id,
            'metadata' => [
                'title' => $this->notification->title,
                'recipients' => $this->notification->recipientsCount(),
                'is_global' => $this->notification->is_global,
            ],
        ]);

        return $this->redirect(route('admin-notifications.edit', $this->notification), navigate: true);
    }

    public function delete(): mixed
    {
        abort_unless($this->notification, 404);

        $notificationId = $this->notification->id;
        $metadata = [
            'title' => $this->notification->title,
            'recipients' => $this->notification->notifications()->count(),
        ];

        Notification::query()
            ->where('source', 'manual')
            ->where('mid', $this->notification->id)
            ->delete();

        NotificationManualState::query()
            ->where('notification_manual_id', $this->notification->id)
            ->delete();

        $this->notification->delete();

        log_activity('admin.notifications.delete', 'Deleted a manual notification.', [
            'subject_type' => NotificationManual::class,
            'subject_id' => $notificationId,
            'metadata' => $metadata,
        ]);

        return $this->redirect(route('admin-notifications.index'), navigate: true);
    }

    public function render(): View
    {
        $selectedUsers = $this->isEditing
            ? collect()
            : User::query()
                ->whereIn('id', array_map('intval', $this->user_ids))
                ->orderBy('name')
                ->get(['id', 'name', 'email']);

        $userResults = $this->isEditing || $this->is_global
            ? collect()
            : User::query()
                ->when(trim($this->userQuery) !== '', function ($builder): void {
                    $search = trim($this->userQuery);

                    $builder->where(function ($nested) use ($search): void {
                        $nested
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%");
                    });
                })
                ->when($this->user_ids !== [], fn ($builder) => $builder->whereNotIn('id', array_map('intval', $this->user_ids)))
                ->orderBy('name')
                ->limit(12)
                ->get(['id', 'name', 'email']);

        return view('adminnotifications::livewire.form', [
            'selectedUsers' => $selectedUsers,
            'userResults' => $userResults,
            'recipientCount' => $this->notification?->recipientsCount() ?? 0,
            'sentAt' => $this->notification?->created_at?->format('Y-m-d H:i'),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => $this->isEditing ? __('Edit notification') : __('Create notification'),
        ]);
    }
}
