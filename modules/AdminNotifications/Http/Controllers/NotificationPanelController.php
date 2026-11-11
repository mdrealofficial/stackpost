<?php

namespace Modules\AdminNotifications\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\AdminNotifications\Services\NotificationService;

class NotificationPanelController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {
    }

    public function panel(): JsonResponse
    {
        return response()->json($this->payload());
    }

    public function markRead(Request $request, string $notificationKey): JsonResponse
    {
        $this->notificationService->markAsRead((int) auth()->id(), $notificationKey);

        return response()->json($this->payload());
    }

    public function markAllRead(): JsonResponse
    {
        $this->notificationService->markAllAsRead((int) auth()->id());

        return response()->json($this->payload());
    }

    public function archiveAll(): JsonResponse
    {
        $this->notificationService->archiveAll((int) auth()->id());

        return response()->json($this->payload());
    }

    protected function payload(): array
    {
        $payload = $this->notificationService->panelPayload((int) auth()->id(), 20);

        return [
            'status' => 1,
            'html' => view('adminnotifications::partials.panel-items', [
                'notifications' => $payload['notifications'],
                'unreadCount' => $payload['unreadCount'],
            ])->render(),
            'unread_count' => $payload['unreadCount'],
        ];
    }
}
