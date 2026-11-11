<?php

namespace Modules\AdminNotifications\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\AdminNotifications\Models\Notification;
use Modules\AdminNotifications\Models\NotificationManual;
use Modules\AdminNotifications\Models\NotificationManualState;
use Modules\AdminNotifications\Services\NotificationService;
use Modules\AdminUser\Models\User;

class AdminNotificationController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {
    }

    public function index(Request $request): View
    {
        $query = NotificationManual::query()
            ->with(['creator:id,name'])
            ->withCount('notifications')
            ->when($request->filled('q'), function ($builder) use ($request): void {
                $search = trim((string) $request->string('q'));

                $builder->where(function ($nested) use ($search): void {
                    $nested
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('message', 'like', "%{$search}%")
                        ->orWhere('url', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $notifications = $query->paginate(15)->withQueryString();

        return view('adminnotifications::index', [
            'notifications' => $notifications,
            'filters' => [
                'q' => $request->string('q')->toString(),
            ],
            'summary' => [
                'total' => NotificationManual::query()->count(),
                'sent_today' => NotificationManual::query()->whereDate('created_at', now()->toDateString())->count(),
                'recipients' => Notification::query()->where('source', 'manual')->count() + (NotificationManual::query()->where('is_global', true)->count() * User::query()->count()),
            ],
            'selectedUserOptions' => $this->selectedUserOptions($request->session()->getOldInput('user_ids', [])),
        ]);
    }

    public function create(): View
    {
        return view('adminnotifications::create', $this->formData(new NotificationManual()));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateNotification($request);

        $manual = $this->notificationService->sendManual(
            $validated['user_ids'],
            $validated['title'] ?? null,
            $validated['message'],
            $validated['url'] ?? null,
            auth()->id(),
            'news',
            (bool) $validated['is_global'],
        );

        log_activity('admin.notifications.create', 'Created a manual notification.', [
            'subject_type' => NotificationManual::class,
            'subject_id' => $manual->id,
            'metadata' => [
                'title' => $manual->title,
                'recipients' => $manual->recipientsCount(),
                'is_global' => $manual->is_global,
            ],
        ]);

        $redirectRoute = $request->input('_notification_modal') === 'create'
            ? route('admin-notifications.index')
            : route('admin-notifications.edit', $manual);

        return redirect($redirectRoute)->with('status', __('Notification sent successfully.'));
    }

    public function edit(NotificationManual $notification): View
    {
        $notification->loadCount('notifications');

        return view('adminnotifications::edit', $this->formData($notification));
    }

    public function update(Request $request, NotificationManual $notification): RedirectResponse
    {
        $validated = $this->validateNotification($request, true);

        $notification->update([
            'title' => $validated['title'] ?? null,
            'message' => $validated['message'],
            'url' => $validated['url'] ?? null,
            'type' => 'news',
            'updated_at' => now(),
        ]);

        Notification::query()
            ->where('source', 'manual')
            ->where('mid', $notification->id)
            ->update([
                'url' => $validated['url'] ?? null,
                'updated_at' => now(),
            ]);

        log_activity('admin.notifications.update', 'Updated a manual notification.', [
            'subject_type' => NotificationManual::class,
            'subject_id' => $notification->id,
            'metadata' => [
                'title' => $notification->title,
            ],
        ]);

        $redirectRoute = $request->input('_notification_modal') === 'edit'
            ? route('admin-notifications.index')
            : route('admin-notifications.edit', $notification);

        return redirect($redirectRoute)->with('status', __('Notification updated successfully.'));
    }

    public function destroy(NotificationManual $notification): RedirectResponse
    {
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
            'subject_id' => $notification->id,
            'metadata' => $metadata,
        ]);

        return redirect()
            ->route('admin-notifications.index')
            ->with('status', __('Notification deleted successfully.'));
    }

    public function resend(NotificationManual $notification): RedirectResponse
    {
        $resent = $this->notificationService->resendManual($notification, auth()->id());

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

        return redirect()
            ->route('admin-notifications.index')
            ->with('status', __('Notification resent successfully.'));
    }

    public function searchUsers(Request $request): JsonResponse
    {
        $search = trim((string) $request->string('q'));

        $users = User::query()
            ->when($search !== '', function ($builder) use ($search): void {
                $builder->where(function ($nested) use ($search): void {
                    $nested
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'email']);

        return response()->json([
            'items' => $users->map(fn (User $user) => $this->userOption($user))->all(),
        ]);
    }

    protected function validateNotification(Request $request, bool $editing = false): array
    {
        $rules = [
            'title' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'url' => ['nullable', 'url', 'max:255'],
            'is_global' => ['nullable', 'boolean'],
        ];

        if (! $editing) {
            $rules['user_ids'] = ['nullable', 'array'];
            $rules['user_ids.*'] = ['integer', 'exists:users,id'];
        }

        $validated = $request->validate($rules);
        $validated['is_global'] = $request->boolean('is_global');
        $validated['user_ids'] = array_values(array_map('intval', $validated['user_ids'] ?? []));

        if (! $editing && ! $validated['is_global'] && $validated['user_ids'] === []) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'user_ids' => __('Select at least one user or enable global notification.'),
            ]);
        }

        return $validated;
    }

    protected function formData(NotificationManual $notification): array
    {
        return [
            'notification' => $notification,
            'selectedUserOptions' => $this->selectedUserOptions(request()->session()->getOldInput('user_ids', [])),
            'isEditing' => $notification->exists,
            'sentAt' => $notification->created_at instanceof Carbon ? $notification->created_at->format('Y-m-d H:i') : null,
            'recipientCount' => $notification->exists ? $notification->recipientsCount() : 0,
        ];
    }

    protected function selectedUserOptions(array $ids): array
    {
        $ids = collect($ids)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return User::query()
            ->whereIn('id', $ids->all())
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user) => $this->userOption($user))
            ->all();
    }

    protected function userOption(User $user): array
    {
        return [
            'key' => (string) $user->id,
            'label' => trim($user->name.' <'.$user->email.'>'),
        ];
    }
}
