<?php

namespace Modules\AdminUser\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\AdminUser\Models\AuditLog;
use Modules\AdminUser\Models\User;

class AdminUserLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'user_search' => ['nullable', 'string', 'max:120'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $selectedUser = null;

        if ($filters['user_id'] ?? null) {
            $selectedUser = User::query()->find($filters['user_id']);
        }

        $logs = AuditLog::query()
            ->with('causer')
            ->when($filters['user_id'] ?? null, fn ($query, $userId) => $query->where('causer_user_id', $userId))
            ->when($filters['date_from'] ?? null, fn ($query, $dateFrom) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($filters['date_to'] ?? null, fn ($query, $dateTo) => $query->whereDate('created_at', '<=', $dateTo))
            ->latest()
            ->limit(100)
            ->get();

        $users = User::query()
            ->when(
                filled($filters['user_search'] ?? null),
                function ($query) use ($filters) {
                    $search = trim((string) $filters['user_search']);

                    $query->where(function ($userQuery) use ($search) {
                        $userQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
                },
                fn ($query) => $query->latest('id')
            )
            ->limit(20)
            ->get(['id', 'name', 'username', 'email']);

        if ($selectedUser && ! $users->contains('id', $selectedUser->id)) {
            $users->prepend($selectedUser);
        }

        return view('adminuser::logs.index', [
            'logs' => $logs,
            'users' => $users->unique('id')->values(),
            'filters' => [
                'user_id' => $filters['user_id'] ?? null,
                'user_search' => $filters['user_search'] ?? null,
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
            ],
        ]);
    }
}
