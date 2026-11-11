<?php

namespace Modules\AppAutomation\Services\GoogleDrive;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\AppAutomation\Events\AutomationDriveFileChanged;
use Modules\AppAutomation\Models\AutomationConnection;
use Modules\AppAutomation\Models\AutomationDriveChannel;
use Modules\AppAutomation\Models\AutomationDriveFileEvent;
use Modules\AppAutomation\Models\AutomationDriveFolderWatch;
use Throwable;

class GoogleDriveWatchService
{
    public function __construct(
        protected GoogleDriveApiService $api,
        protected GoogleDriveConnectionService $connections,
    ) {}

    public function ensureChannel(AutomationConnection $connection, bool $force = false): AutomationDriveChannel
    {
        abort_unless(Schema::hasTable('automation_drive_channels'), 503, __('Google Drive watcher storage is not ready.'));

        $configuration = $this->api->ensureReady();
        $existing = AutomationDriveChannel::query()->where('connection_id', $connection->id)->first();
        $renewBefore = max(3600, (int) config('modules.appautomation.google_drive.renew_before_seconds', 86400));

        if (! $force && $existing?->status === 'active' && $existing->expires_at?->isAfter(now()->addSeconds($renewBefore))) {
            return $existing;
        }

        if (! $configuration['webhook_public']) {
            return AutomationDriveChannel::query()->updateOrCreate(
                ['connection_id' => $connection->id],
                [
                    'channel_id' => $existing?->channel_id ?: (string) Str::uuid(),
                    'resource_id' => $existing?->resource_id,
                    'resource_uri' => $existing?->resource_uri,
                    'token_hash' => $existing?->token_hash ?: hash('sha256', Str::random(64)),
                    'page_token' => $existing?->page_token ?: '',
                    'status' => 'blocked',
                    'last_error' => __('Google Drive push notifications require a public HTTPS webhook URL.'),
                    'expires_at' => $existing?->expires_at,
                ]
            );
        }

        $accessToken = $this->connections->accessToken($connection);
        $pageToken = trim((string) ($existing?->page_token ?? '')) ?: $this->api->startPageToken($accessToken);
        $channelId = (string) Str::uuid();
        $channelToken = Str::random(64);
        $ttl = min(604800, max(3600, (int) config('modules.appautomation.google_drive.watch_ttl_seconds', 518400)));
        $requestedExpiration = CarbonImmutable::now()->addSeconds($ttl);

        if ($existing?->resource_id) {
            try {
                $this->api->stopChannel($accessToken, (string) $existing->channel_id, (string) $existing->resource_id);
            } catch (GoogleDriveException) {
                // Expired channels are replaced even if Google no longer accepts the stop request.
            }
        }

        try {
            $response = $this->api->watchChanges($accessToken, $pageToken, [
                'id' => $channelId,
                'address' => $configuration['webhook_url'],
                'token' => $channelToken,
                'expiration' => (string) $requestedExpiration->getTimestampMs(),
            ]);
            $expirationMs = (int) ($response['expiration'] ?? 0);
            $expiresAt = $expirationMs > 0
                ? CarbonImmutable::createFromTimestampUTC((int) floor($expirationMs / 1000))
                : $requestedExpiration;

            return AutomationDriveChannel::query()->updateOrCreate(
                ['connection_id' => $connection->id],
                [
                    'channel_id' => $channelId,
                    'resource_id' => trim((string) ($response['resourceId'] ?? '')) ?: null,
                    'resource_uri' => trim((string) ($response['resourceUri'] ?? '')) ?: null,
                    'token_hash' => hash('sha256', $channelToken),
                    'page_token' => $pageToken,
                    'status' => 'active',
                    'expires_at' => $expiresAt,
                    'last_renewed_at' => now(),
                    'last_error' => null,
                    'metadata' => ['webhook_url' => $configuration['webhook_url']],
                ]
            );
        } catch (GoogleDriveException $exception) {
            AutomationDriveChannel::query()->updateOrCreate(
                ['connection_id' => $connection->id],
                [
                    'channel_id' => $channelId,
                    'token_hash' => hash('sha256', $channelToken),
                    'page_token' => $pageToken,
                    'status' => 'error',
                    'last_error' => $exception->getMessage(),
                ]
            );
            throw $exception;
        }
    }

    public function stopChannel(AutomationConnection $connection): void
    {
        $channel = AutomationDriveChannel::query()->where('connection_id', $connection->id)->first();
        if (! $channel) {
            return;
        }

        try {
            $accessToken = $this->connections->accessToken($connection);
            $this->api->stopChannel($accessToken, (string) $channel->channel_id, (string) $channel->resource_id);
        } finally {
            $channel->forceFill(['status' => 'stopped', 'expires_at' => now()])->save();
        }
    }

    public function receiveNotification(string $channelId, string $channelToken, ?string $resourceId, ?string $resourceState, ?int $messageNumber): ?AutomationDriveChannel
    {
        $channel = AutomationDriveChannel::query()->where('channel_id', $channelId)->first();
        if (! $channel || ! hash_equals((string) $channel->token_hash, hash('sha256', $channelToken))) {
            return null;
        }
        if ($channel->resource_id && $resourceId && ! hash_equals((string) $channel->resource_id, $resourceId)) {
            return null;
        }
        if ($messageNumber && $channel->last_message_number && $messageNumber <= $channel->last_message_number) {
            return null;
        }

        $channel->forceFill([
            'last_message_number' => $messageNumber ?: $channel->last_message_number,
            'last_notification_at' => now(),
            'metadata' => [...(array) $channel->metadata, 'last_resource_state' => $resourceState],
        ])->save();

        return $channel;
    }

    public function pollChannel(AutomationDriveChannel $channel): array
    {
        $channel = AutomationDriveChannel::query()->with('connection')->findOrFail($channel->id);
        if (! $channel->connection || ! in_array($channel->status, ['active', 'polling', 'error'], true)) {
            return ['changes' => 0, 'events' => 0, 'has_more' => false];
        }

        $channel->forceFill(['status' => 'polling'])->save();
        $accessToken = $this->connections->accessToken($channel->connection);
        $pageToken = trim((string) $channel->page_token);
        if ($pageToken === '') {
            $channel->forceFill([
                'page_token' => $this->api->startPageToken($accessToken),
                'status' => 'active',
                'last_polled_at' => now(),
                'last_error' => null,
            ])->save();

            return ['changes' => 0, 'events' => 0, 'has_more' => false];
        }

        $changesSeen = 0;
        $eventsCreated = 0;
        $hasMore = false;
        $maxPages = max(1, min(100, (int) config('modules.appautomation.google_drive.max_change_pages_per_poll', 50)));

        try {
            for ($page = 0; $page < $maxPages; $page++) {
                $payload = $this->api->changes($accessToken, $pageToken);
                $changes = (array) ($payload['changes'] ?? []);
                $changesSeen += count($changes);
                $eventsCreated += $this->recordMatchingChanges($channel->connection, $changes, $pageToken);
                $nextPageToken = trim((string) ($payload['nextPageToken'] ?? ''));
                $newStartPageToken = trim((string) ($payload['newStartPageToken'] ?? ''));

                if ($nextPageToken !== '') {
                    $pageToken = $nextPageToken;
                    $hasMore = true;
                    continue;
                }
                if ($newStartPageToken !== '') {
                    $pageToken = $newStartPageToken;
                }
                $hasMore = false;
                break;
            }

            $channel->forceFill([
                'page_token' => $pageToken,
                'status' => 'active',
                'last_polled_at' => now(),
                'last_error' => null,
            ])->save();

            return ['changes' => $changesSeen, 'events' => $eventsCreated, 'has_more' => $hasMore];
        } catch (Throwable $exception) {
            $channel->forceFill([
                'status' => 'error',
                'last_polled_at' => now(),
                'last_error' => $exception->getMessage(),
            ])->save();
            throw $exception;
        }
    }

    protected function recordMatchingChanges(AutomationConnection $connection, array $changes, string $sourcePageToken): int
    {
        $watches = AutomationDriveFolderWatch::query()
            ->where('connection_id', $connection->id)
            ->where('status', 'active')
            ->get();
        $created = 0;

        foreach ($changes as $change) {
            $change = is_array($change) ? $change : [];
            $file = (array) ($change['file'] ?? []);
            if (($change['removed'] ?? false) || ($file['trashed'] ?? false) || blank($file['id'] ?? null)) {
                continue;
            }

            $parents = collect((array) ($file['parents'] ?? []))->map(fn ($id): string => (string) $id);
            foreach ($watches as $watch) {
                if (! $parents->contains((string) $watch->folder_id) || ! $this->accepts($watch, (string) ($file['mimeType'] ?? ''))) {
                    continue;
                }

                $externalEventId = 'google-drive:'.hash('sha256', implode(':', [
                    $watch->id,
                    (string) $file['id'],
                    (string) ($file['modifiedTime'] ?? $change['time'] ?? $sourcePageToken),
                ]));
                $eventRecord = AutomationDriveFileEvent::query()->firstOrCreate(
                    ['watch_id' => $watch->id, 'file_id' => (string) $file['id']],
                    [
                        'connection_id' => $connection->id,
                        'external_event_id' => $externalEventId,
                        'name' => trim((string) ($file['name'] ?? '')) ?: null,
                        'mime_type' => trim((string) ($file['mimeType'] ?? '')) ?: null,
                        'source_modified_at' => filled($file['modifiedTime'] ?? null) ? $file['modifiedTime'] : null,
                        'payload' => $file,
                    ]
                );

                if (! $eventRecord->wasRecentlyCreated && $eventRecord->dispatched_at) {
                    continue;
                }

                $eventPayload = [
                    'connection_id' => (int) $connection->id,
                    'owner_user_id' => (int) $connection->owner_user_id,
                    'team_id' => $connection->team_id ? (int) $connection->team_id : null,
                    'workflow_id' => (int) $watch->workflow_id,
                    'node_id' => (string) $watch->node_id,
                    'folder_id' => (string) $watch->folder_id,
                    'file_id' => (string) $file['id'],
                    'resource_key' => trim((string) ($file['resourceKey'] ?? '')),
                    'name' => trim((string) ($file['name'] ?? '')),
                    'mime_type' => trim((string) ($file['mimeType'] ?? '')),
                    'web_view_link' => trim((string) ($file['webViewLink'] ?? '')),
                    'size' => isset($file['size']) ? (int) $file['size'] : null,
                    'md5_checksum' => trim((string) ($file['md5Checksum'] ?? '')) ?: null,
                    'source_modified_at' => $file['modifiedTime'] ?? $change['time'] ?? null,
                    'external_event_id' => (string) $eventRecord->external_event_id,
                ];
                event(new AutomationDriveFileChanged($eventPayload));
                $eventRecord->forceFill(['dispatched_at' => now()])->save();
                $watch->forceFill(['last_matched_at' => now()])->save();
                $created++;
            }
        }

        return $created;
    }

    protected function accepts(AutomationDriveFolderWatch $watch, string $mimeType): bool
    {
        $types = collect((array) $watch->accepted_file_types);

        return ($types->contains('image') && str_starts_with($mimeType, 'image/'))
            || ($types->contains('video') && str_starts_with($mimeType, 'video/'))
            || ($types->contains('document') && (str_starts_with($mimeType, 'application/') || str_starts_with($mimeType, 'text/')));
    }
}
