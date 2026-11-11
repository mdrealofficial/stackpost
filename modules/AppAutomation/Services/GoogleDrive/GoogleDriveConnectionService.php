<?php

namespace Modules\AppAutomation\Services\GoogleDrive;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\AdminUser\Models\User;
use Modules\AppAutomation\Models\AutomationConnection;
use Modules\AppAutomation\Models\AutomationDriveFolderWatch;
use Modules\AppAutomation\Models\AutomationWorkflow;
use Modules\AppTeams\Support\TeamWorkspaceAccess;

class GoogleDriveConnectionService
{
    public function __construct(protected GoogleDriveApiService $api) {}

    public function connect(User $actor, array $token, array $identity): AutomationConnection
    {
        $ownerUserId = TeamWorkspaceAccess::workspaceOwnerUserId($actor);
        $teamId = TeamWorkspaceAccess::activeTeam($actor)?->id;
        $externalAccountId = trim((string) ($identity['id'] ?? $identity['sub'] ?? $identity['email'] ?? ''));
        $email = trim((string) ($identity['email'] ?? ''));

        if ($externalAccountId === '') {
            throw GoogleDriveException::requestFailed(__('Google account verification'), __('The account identifier was missing.'));
        }

        return DB::transaction(function () use ($actor, $ownerUserId, $teamId, $externalAccountId, $email, $token, $identity): AutomationConnection {
            $connection = AutomationConnection::query()
                ->where('owner_user_id', $ownerUserId)
                ->where('provider', GoogleDriveApiService::PROVIDER)
                ->where('external_account_id', $externalAccountId)
                ->when($teamId, fn (Builder $query) => $query->where('team_id', $teamId), fn (Builder $query) => $query->whereNull('team_id'))
                ->lockForUpdate()
                ->first() ?: new AutomationConnection();
            $existingCredentials = $connection->exists ? (array) $connection->credentials : [];
            $refreshToken = trim((string) ($token['refresh_token'] ?? $existingCredentials['refresh_token'] ?? ''));

            if ($refreshToken === '') {
                throw GoogleDriveException::requestFailed(
                    __('Google Drive authorization'),
                    __('Google did not return an offline refresh token. Remove the app from your Google account permissions, then connect again.')
                );
            }

            $expiresAt = $this->api->tokenExpiresAt($token);
            $scopes = collect(preg_split('/[\s,]+/', trim((string) ($token['scope'] ?? ''))) ?: [])
                ->filter()->values()->all();
            if (! in_array('https://www.googleapis.com/auth/drive.readonly', $scopes, true)) {
                throw GoogleDriveException::requestFailed(
                    __('Google Drive authorization'),
                    __('The required Drive read-only scope was not granted.')
                );
            }

            $connection->fill([
                'owner_user_id' => $ownerUserId,
                'created_by_user_id' => $connection->created_by_user_id ?: (int) $actor->id,
                'team_id' => $teamId,
                'provider' => GoogleDriveApiService::PROVIDER,
                'external_account_id' => $externalAccountId,
                'external_account_email' => $email !== '' ? $email : null,
                'label' => $email !== '' ? __('Google Drive · :email', ['email' => $email]) : __('Google Drive'),
                'status' => 'active',
                'credentials' => [
                    'access_token' => trim((string) ($token['access_token'] ?? '')),
                    'refresh_token' => $refreshToken,
                    'token_type' => trim((string) ($token['token_type'] ?? 'Bearer')),
                ],
                'scopes' => $scopes,
                'metadata' => [
                    ...(array) $connection->metadata,
                    'oauth_subject' => $externalAccountId,
                    'picture' => trim((string) ($identity['picture'] ?? '')),
                    'connected_by_user_id' => (int) $actor->id,
                ],
                'expires_at' => $expiresAt,
                'last_verified_at' => now(),
                'disconnected_at' => null,
            ])->save();

            return $connection->fresh();
        });
    }

    public function accessToken(AutomationConnection $connection): string
    {
        if ($connection->provider !== GoogleDriveApiService::PROVIDER || $connection->status !== 'active' || $connection->disconnected_at) {
            throw GoogleDriveException::requestFailed(__('Google Drive access'), __('The workspace connection is not active.'));
        }

        $credentials = (array) $connection->credentials;
        $accessToken = trim((string) ($credentials['access_token'] ?? ''));
        if ($accessToken !== '' && (! $connection->expires_at || $connection->expires_at->isAfter(now()->addMinutes(2)))) {
            return $accessToken;
        }

        return DB::transaction(function () use ($connection): string {
            $locked = AutomationConnection::query()->lockForUpdate()->findOrFail($connection->id);
            $credentials = (array) $locked->credentials;
            $accessToken = trim((string) ($credentials['access_token'] ?? ''));

            if ($accessToken !== '' && (! $locked->expires_at || $locked->expires_at->isAfter(now()->addMinutes(2)))) {
                return $accessToken;
            }

            $refreshToken = trim((string) ($credentials['refresh_token'] ?? ''));
            if ($refreshToken === '') {
                $locked->forceFill(['status' => 'reauthorization_required'])->save();
                throw GoogleDriveException::requestFailed(__('Google Drive access'), __('The offline refresh token is missing. Reconnect this account.'));
            }

            try {
                $token = $this->api->refreshToken($refreshToken);
            } catch (GoogleDriveException $exception) {
                $locked->forceFill([
                    'status' => 'reauthorization_required',
                    'metadata' => [...(array) $locked->metadata, 'last_token_error' => $exception->getMessage()],
                ])->save();
                throw $exception;
            }

            $newAccessToken = trim((string) ($token['access_token'] ?? ''));
            $locked->forceFill([
                'credentials' => [
                    'access_token' => $newAccessToken,
                    'refresh_token' => $refreshToken,
                    'token_type' => trim((string) ($token['token_type'] ?? $credentials['token_type'] ?? 'Bearer')),
                ],
                'expires_at' => $this->api->tokenExpiresAt($token),
                'status' => 'active',
                'metadata' => collect((array) $locked->metadata)->except('last_token_error')->all(),
            ])->save();

            return $newAccessToken;
        });
    }

    public function verify(AutomationConnection $connection): AutomationConnection
    {
        $identity = $this->api->identity($this->accessToken($connection));
        $email = trim((string) ($identity['email'] ?? $connection->external_account_email ?? ''));

        $connection->forceFill([
            'external_account_email' => $email !== '' ? $email : null,
            'label' => $email !== '' ? __('Google Drive · :email', ['email' => $email]) : $connection->label,
            'last_verified_at' => now(),
            'status' => 'active',
            'metadata' => [
                ...(array) $connection->metadata,
                'picture' => trim((string) ($identity['picture'] ?? data_get($connection->metadata, 'picture', ''))),
            ],
        ])->save();

        return $connection->fresh();
    }

    public function ownedConnection(User $actor, int $connectionId): AutomationConnection
    {
        $ownerUserId = TeamWorkspaceAccess::workspaceOwnerUserId($actor);
        $teamId = TeamWorkspaceAccess::activeTeam($actor)?->id;

        return AutomationConnection::query()
            ->whereKey($connectionId)
            ->where('provider', GoogleDriveApiService::PROVIDER)
            ->where('owner_user_id', $ownerUserId)
            ->when($teamId, fn (Builder $query) => $query->where('team_id', $teamId), fn (Builder $query) => $query->whereNull('team_id'))
            ->firstOrFail();
    }

    public function disconnect(AutomationConnection $connection): void
    {
        $credentials = (array) $connection->credentials;
        $token = trim((string) ($credentials['refresh_token'] ?? $credentials['access_token'] ?? ''));
        $sameGoogleGrantIsStillUsed = AutomationConnection::query()
            ->where('provider', GoogleDriveApiService::PROVIDER)
            ->where('external_account_id', $connection->external_account_id)
            ->where('id', '!=', $connection->id)
            ->where('status', 'active')
            ->whereNull('disconnected_at')
            ->exists();
        if (! $sameGoogleGrantIsStillUsed) {
            $this->api->revoke($token);
        }

        DB::transaction(function () use ($connection): void {
            if (Schema::hasTable('automation_drive_folder_watches')) {
                AutomationDriveFolderWatch::query()->where('connection_id', $connection->id)->update(['status' => 'paused']);
            }
            $connection->forceFill([
                'status' => 'disconnected',
                'credentials' => [],
                'expires_at' => null,
                'disconnected_at' => now(),
            ])->save();
        });
    }

    public function graphConnectionIssues(array $graph, User $actor): array
    {
        $nodes = collect((array) ($graph['nodes'] ?? []))->where('type', 'trigger_google_drive');
        $issues = [];

        foreach ($nodes as $node) {
            $connectionId = (int) data_get($node, 'config.connection_id', 0);
            if ($connectionId <= 0) {
                continue;
            }

            $connection = AutomationConnection::query()
                ->whereKey($connectionId)
                ->where('owner_user_id', TeamWorkspaceAccess::workspaceOwnerUserId($actor))
                ->where('provider', GoogleDriveApiService::PROVIDER)
                ->when(TeamWorkspaceAccess::activeTeam($actor)?->id, fn (Builder $query, $teamId) => $query->where('team_id', $teamId), fn (Builder $query) => $query->whereNull('team_id'))
                ->first();

            if (! $connection || $connection->status !== 'active' || $connection->disconnected_at) {
                $issues[] = __('Choose an active Google Drive connection that belongs to this workspace.');
            }
        }

        return array_values(array_unique($issues));
    }

    public function syncWorkflowFolderWatches(AutomationWorkflow $workflow, array $graph): void
    {
        if (! Schema::hasTable('automation_drive_folder_watches')) {
            return;
        }

        $keptNodeIds = [];
        foreach ((array) ($graph['nodes'] ?? []) as $node) {
            if (($node['type'] ?? null) !== 'trigger_google_drive') {
                continue;
            }

            $nodeId = trim((string) ($node['id'] ?? ''));
            $connectionId = (int) data_get($node, 'config.connection_id', 0);
            $folderId = trim((string) data_get($node, 'config.folder_id', ''));
            if ($nodeId === '' || $connectionId <= 0 || $folderId === '') {
                continue;
            }

            $connection = AutomationConnection::query()
                ->whereKey($connectionId)
                ->where('owner_user_id', $workflow->owner_user_id)
                ->where('provider', GoogleDriveApiService::PROVIDER)
                ->when($workflow->team_id, fn (Builder $query, $teamId) => $query->where('team_id', $teamId), fn (Builder $query) => $query->whereNull('team_id'))
                ->where('status', 'active')
                ->first();
            if (! $connection) {
                continue;
            }

            $types = collect(explode(',', (string) data_get($node, 'config.file_types', '')))
                ->map(fn (string $type): string => trim($type))
                ->intersect(['image', 'video', 'document'])
                ->values()->all();
            AutomationDriveFolderWatch::query()->updateOrCreate(
                ['workflow_id' => $workflow->id, 'node_id' => $nodeId],
                [
                    'connection_id' => $connection->id,
                    'folder_id' => $folderId,
                    'accepted_file_types' => $types,
                    'status' => match ((string) $workflow->status) {
                        'active' => 'active',
                        'paused' => 'paused',
                        default => 'draft',
                    },
                    'metadata' => ['workflow_revision' => (int) $workflow->current_revision],
                ]
            );
            $keptNodeIds[] = $nodeId;
        }

        AutomationDriveFolderWatch::query()
            ->where('workflow_id', $workflow->id)
            ->when($keptNodeIds !== [], fn (Builder $query) => $query->whereNotIn('node_id', $keptNodeIds))
            ->when($keptNodeIds === [], fn (Builder $query) => $query)
            ->delete();
    }
}
