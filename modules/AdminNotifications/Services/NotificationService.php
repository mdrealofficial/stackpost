<?php

namespace Modules\AdminNotifications\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\AdminNotifications\DataObjects\NotificationFeedItem;
use Modules\AdminNotifications\Models\Notification;
use Modules\AdminNotifications\Models\NotificationManual;
use Modules\AdminNotifications\Models\NotificationManualState;

class NotificationService
{
    public function sendAuto(int $userId, string $message, ?string $url = null, ?string $title = null, string $type = 'news'): Notification
    {
        return Notification::query()->create([
            'id_secure' => Str::random(32),
            'user_id' => $userId,
            'source' => 'auto',
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'url' => $url,
        ]);
    }

    public function sendManual(array $userIds, ?string $title, string $message, ?string $url = null, ?int $adminId = null, string $type = 'news', bool $isGlobal = false): NotificationManual
    {
        $manual = NotificationManual::query()->create([
            'id_secure' => Str::random(32),
            'title' => $title,
            'message' => $message,
            'url' => $url,
            'type' => $type,
            'created_by' => $adminId,
            'is_global' => $isGlobal,
        ]);

        if (! $isGlobal) {
            $timestamp = now();
            $notifications = collect(array_unique(array_map('intval', $userIds)))
                ->filter(fn (int $userId) => $userId > 0)
                ->map(fn (int $userId) => [
                    'id_secure' => Str::random(32),
                    'user_id' => $userId,
                    'source' => 'manual',
                    'mid' => $manual->id,
                    'type' => $type,
                    'title' => null,
                    'message' => null,
                    'url' => $url,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ])
                ->all();

            if ($notifications !== []) {
                Notification::query()->insert($notifications);
            }
        }

        return $manual;
    }

    public function resendManual(NotificationManual $manual, ?int $adminId = null): NotificationManual
    {
        $userIds = $manual->is_global
            ? []
            : $manual->notifications()
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

        return $this->sendManual(
            $userIds,
            $manual->title,
            (string) $manual->message,
            $manual->url,
            $adminId,
            (string) ($manual->type ?: 'news'),
            (bool) $manual->is_global,
        );
    }

    public function getLatest(int $userId, int $limit = 20): Collection
    {
        $personal = Notification::query()
            ->with('manual')
            ->where('user_id', $userId)
            ->where(function ($query): void {
                $query->where('source', 'auto')
                    ->orWhere(function ($nested): void {
                        $nested->where('source', 'manual')
                            ->whereHas('manual', fn ($manual) => $manual->where('is_global', false));
                    });
            })
            ->get()
            ->map(fn (Notification $notification) => new NotificationFeedItem(
                actionKey: 'n-'.$notification->id,
                title: $notification->resolvedTitle(),
                message: $notification->resolvedMessage(),
                url: $notification->url,
                read_at: $notification->read_at,
                created_at: $notification->created_at,
                source: $notification->source,
                isGlobal: false,
            ));

        $global = NotificationManual::query()
            ->where('is_global', true)
            ->with(['states' => fn ($query) => $query->where('user_id', $userId)])
            ->get()
            ->filter(function (NotificationManual $manual): bool {
                $state = $manual->states->first();

                return is_null($state?->archived_at);
            })
            ->map(function (NotificationManual $manual): NotificationFeedItem {
                /** @var NotificationManualState|null $state */
                $state = $manual->states->first();

                return new NotificationFeedItem(
                    actionKey: 'g-'.$manual->id,
                    title: (string) ($manual->title ?? ''),
                    message: (string) $manual->message,
                    url: $manual->url,
                    read_at: $state?->read_at,
                    created_at: $manual->created_at,
                    source: 'manual',
                    isGlobal: true,
                );
            });

        return $personal
            ->concat($global)
            ->sortByDesc(fn (NotificationFeedItem $item) => $item->created_at?->getTimestamp() ?? 0)
            ->take($limit)
            ->values();
    }

    public function markAsRead(int $userId, string $actionKey): bool
    {
        if (str_starts_with($actionKey, 'n-')) {
            $id = (int) substr($actionKey, 2);

            return Notification::query()
                ->where('user_id', $userId)
                ->whereKey($id)
                ->whereNull('read_at')
                ->update(['read_at' => now()]) > 0;
        }

        if (str_starts_with($actionKey, 'g-')) {
            $manualId = (int) substr($actionKey, 2);
            $state = NotificationManualState::query()->firstOrNew([
                'notification_manual_id' => $manualId,
                'user_id' => $userId,
            ]);

            if ($state->read_at !== null) {
                return false;
            }

            $state->read_at = now();
            $state->save();

            return true;
        }

        return false;
    }

    public function markAllAsRead(int $userId): int
    {
        $personalUpdated = Notification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $now = now();
        $globalIds = NotificationManual::query()
            ->where('is_global', true)
            ->pluck('id');

        foreach ($globalIds as $manualId) {
            NotificationManualState::query()->updateOrCreate(
                [
                    'notification_manual_id' => (int) $manualId,
                    'user_id' => $userId,
                ],
                [
                    'read_at' => $now,
                ]
            );
        }

        return $personalUpdated + $globalIds->count();
    }

    public function archiveAll(int $userId): int
    {
        $personalDeleted = Notification::query()
            ->where('user_id', $userId)
            ->delete();

        $now = now();
        $globalIds = NotificationManual::query()
            ->where('is_global', true)
            ->pluck('id');

        foreach ($globalIds as $manualId) {
            $existing = NotificationManualState::query()->firstOrNew([
                'notification_manual_id' => (int) $manualId,
                'user_id' => $userId,
            ]);

            if ($existing->read_at === null) {
                $existing->read_at = $now;
            }

            $existing->archived_at = $now;
            $existing->save();
        }

        return $personalDeleted + $globalIds->count();
    }

    public function countUnread(int $userId): int
    {
        $personalUnread = Notification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->where(function ($query): void {
                $query->where('source', 'auto')
                    ->orWhere(function ($nested): void {
                        $nested->where('source', 'manual')
                            ->whereHas('manual', fn ($manual) => $manual->where('is_global', false));
                    });
            })
            ->count();

        $globalUnread = NotificationManual::query()
            ->where('is_global', true)
            ->whereDoesntHave('states', function ($query) use ($userId): void {
                $query->where('user_id', $userId)
                    ->where(function ($state): void {
                        $state->whereNotNull('read_at')
                            ->orWhereNotNull('archived_at');
                    });
            })
            ->count();

        return $personalUnread + $globalUnread;
    }

    public function panelPayload(int $userId, int $limit = 20): array
    {
        $notifications = $this->getLatest($userId, $limit);

        return [
            'notifications' => $notifications,
            'unreadCount' => $this->countUnread($userId),
        ];
    }
}
