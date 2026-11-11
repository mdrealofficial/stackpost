<?php

namespace Modules\AdminNotifications\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\AdminNotifications\Models\Notification;
use Modules\AdminNotifications\Models\NotificationManual;
use Modules\AdminNotifications\Models\NotificationManualState;
use Modules\AdminNotifications\Services\NotificationService;
use Modules\AdminUser\Models\User;

#[Title('Notifications')]
class NotificationIndex extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public ?NotificationManual $notification = null;

    public string $title = '';

    public string $message = '';

    public string $url = '';

    public bool $is_global = false;

    public string $userQuery = '';

    /** @var array<int, string> */
    public array $user_ids = [];

    public bool $isEditing = false;

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset('search');
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->dispatch('notification-modal-open');
    }

    public function openEditModal(int $notificationId): void
    {
        $this->resetErrorBag();
        $this->resetValidation();
        $this->notification = NotificationManual::query()->withCount('notifications')->findOrFail($notificationId);
        $this->isEditing = true;
        $this->title = (string) ($this->notification->title ?? '');
        $this->message = (string) $this->notification->message;
        $this->url = (string) ($this->notification->url ?? '');
        $this->is_global = (bool) $this->notification->is_global;
        $this->user_ids = [];
        $this->userQuery = '';
        $this->dispatch('notification-modal-open');
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
            'user_ids.*' => ['integer', Rule::exists('users', 'id')],
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
            $this->dispatch('notification-modal-close');

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

        $this->statusMessage = __('Notification created successfully.');
        $this->dispatch('notification-modal-close');
        $this->resetForm();

        return null;
    }

    public function resend(int $notificationId, NotificationService $notificationService): void
    {
        $notification = NotificationManual::query()->findOrFail($notificationId);
        $resent = $notificationService->resendManual($notification, auth()->id());

        log_activity('admin.notifications.resend', 'Resent a manual notification.', [
            'subject_type' => NotificationManual::class,
            'subject_id' => $resent->id,
            'metadata' => [
                'source_notification_id' => $notification->id,
                'title' => $resent->title,
                'recipients' => $resent->recipientsCount(),
                'is_global' => $resent->is_global,
            ],
        ]);

        $this->statusMessage = __('Notification resent successfully.');
    }

    public function delete(int $notificationId): void
    {
        $notification = NotificationManual::query()->findOrFail($notificationId);
        $metadata = [
            'title' => $notification->title,
            'recipients' => $notification->notifications()->count(),
        ];

        Notification::query()
            ->where('source', 'manual')
            ->where('mid', $notification->id)
            ->delete();

        NotificationManualState::query()
            ->where('notification_manual_id', $notification->id)
            ->delete();

        $notification->delete();

        log_activity('admin.notifications.delete', 'Deleted a manual notification.', [
            'subject_type' => NotificationManual::class,
            'subject_id' => $notificationId,
            'metadata' => $metadata,
        ]);

        $this->statusMessage = __('Notification deleted successfully.');
        $this->resetPageIfNeeded();
    }

    public function render(): View
    {
        $notifications = $this->baseQuery()->paginate(15);
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

        return view('adminnotifications::livewire.index', [
            'notifications' => $notifications,
            'summary' => [
                'total' => NotificationManual::query()->count(),
                'sent_today' => NotificationManual::query()->whereDate('created_at', now()->toDateString())->count(),
                'recipients' => Notification::query()->where('source', 'manual')->count()
                    + (NotificationManual::query()->where('is_global', true)->count() * User::query()->count()),
            ],
            'selectedUsers' => $selectedUsers,
            'userResults' => $userResults,
            'recipientCount' => $this->notification?->recipientsCount() ?? 0,
            'sentAt' => $this->notification?->created_at?->format('Y-m-d H:i'),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Notifications'),
        ]);
    }

    protected function baseQuery()
    {
        return NotificationManual::query()
            ->with(['creator:id,name'])
            ->withCount('notifications')
            ->when($this->search !== '', function ($builder): void {
                $search = trim($this->search);

                $builder->where(function ($nested) use ($search): void {
                    $nested
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('message', 'like', "%{$search}%")
                        ->orWhere('url', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    protected function resetPageIfNeeded(): void
    {
        if (($this->paginators['page'] ?? 1) > 1 && $this->baseQuery()->paginate(15, ['*'], 'page', $this->getPage())->isEmpty()) {
            $this->previousPage();
        }
    }

    protected function resetForm(): void
    {
        $this->resetErrorBag();
        $this->resetValidation();
        $this->notification = null;
        $this->isEditing = false;
        $this->title = '';
        $this->message = '';
        $this->url = '';
        $this->is_global = false;
        $this->userQuery = '';
        $this->user_ids = [];
    }
}
