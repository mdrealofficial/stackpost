<?php

namespace Modules\AppAutomation\Runtime\Handlers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Modules\AdminUser\Models\User;
use Modules\AppAutomation\Contracts\AutomationNodeHandler;
use Modules\AppAutomation\Exceptions\AutomationNodeException;
use Modules\AppAutomation\Models\AutomationConnection;
use Modules\AppAutomation\Models\AutomationWorkflowArtifact;
use Modules\AppAutomation\Models\AutomationWorkflowRun;
use Modules\AppAutomation\Models\AutomationWorkflowStepRun;
use Modules\AppAutomation\Runtime\AutomationNodeContext;
use Modules\AppAutomation\Runtime\AutomationNodeResult;
use Modules\AppAutomation\Services\GoogleDrive\GoogleDriveConnectionService;
use Modules\AppFiles\Exceptions\FileImportLimitException;
use Modules\AppFiles\Models\AppFile;
use Modules\AppFiles\Support\FileManager;

class ImportDriveFileNodeHandler implements AutomationNodeHandler
{
    public function __construct(
        protected FileManager $files,
        protected GoogleDriveConnectionService $drive,
    ) {}

    public function handle(AutomationNodeContext $context): AutomationNodeResult
    {
        $source = $this->sourcePayload($context);
        $fileId = trim((string) ($source['file_id'] ?? ''));
        $connectionId = (int) ($source['connection_id'] ?? $this->triggerConnectionId($context));

        if ($fileId === '' || $connectionId <= 0) {
            throw AutomationNodeException::permanent(__('The Google Drive event is missing its file or connection identifier.'));
        }

        $connection = AutomationConnection::query()
            ->whereKey($connectionId)
            ->where('owner_user_id', $context->ownerUserId())
            ->where('provider', 'google_drive')
            ->where('status', 'active')
            ->when(
                $context->teamId(),
                fn ($query, $teamId) => $query->where('team_id', $teamId),
                fn ($query) => $query->whereNull('team_id'),
            )
            ->first();

        if (! $connection) {
            throw AutomationNodeException::permanent(__('The Google Drive connection is unavailable for this workspace.'));
        }

        $owner = User::query()->find($context->ownerUserId());
        if (! $owner) {
            throw AutomationNodeException::permanent(__('The workflow credit owner no longer exists.'));
        }

        $artifactKey = hash('sha256', $context->idempotencyKey().':drive-file:'.$fileId);
        $artifactNote = 'automation-artifact:'.$artifactKey;
        $reservation = $this->reserveArtifact($context, $fileId, $artifactKey, $artifactNote);
        $existing = $reservation->file;

        if ($existing) {
            return AutomationNodeResult::success([
                'file' => $this->serializeFile($existing),
                'source' => $source,
                'imported' => false,
                'artifact_key' => $artifactKey,
            ]);
        }

        try {
            $file = $this->importFile($owner, $connection, $fileId, $source, $artifactNote, $context);
            $this->completeArtifact($reservation, $file, $context);
        } catch (\Throwable $exception) {
            $this->failArtifact($reservation, $exception, $context);

            if ($exception instanceof AutomationNodeException) {
                throw $exception;
            }
            if ($exception instanceof FileImportLimitException) {
                throw AutomationNodeException::permanent($exception->getMessage(), $exception);
            }

            throw new AutomationNodeException(__('The Google Drive file import failed: :message', [
                'message' => Str::limit($exception->getMessage(), 500, ''),
            ]), true, $exception);
        }

        return AutomationNodeResult::success([
            'file' => $this->serializeFile($file),
            'source' => $source,
            'imported' => true,
            'artifact_key' => $artifactKey,
        ]);
    }

    protected function reserveArtifact(
        AutomationNodeContext $context,
        string $fileId,
        string $artifactKey,
        string $artifactNote,
    ): AutomationWorkflowArtifact {
        return DB::transaction(function () use ($context, $fileId, $artifactKey, $artifactNote): AutomationWorkflowArtifact {
            $run = AutomationWorkflowRun::query()->whereKey($context->run->id)->lockForUpdate()->firstOrFail();
            $step = AutomationWorkflowStepRun::query()->whereKey($context->step->id)->lockForUpdate()->firstOrFail();
            $latestStepId = (int) AutomationWorkflowStepRun::query()
                ->where('run_id', $context->run->id)
                ->where('node_id', (string) $context->step->node_id)
                ->orderByDesc('attempt')
                ->lockForUpdate()
                ->value('id');
            if ((string) $run->status !== AutomationWorkflowRun::STATUS_RUNNING
                || blank($context->run->claim_token)
                || (string) $run->claim_token !== (string) $context->run->claim_token
                || (string) $step->status !== AutomationWorkflowStepRun::STATUS_RUNNING
                || blank($context->step->claim_token)
                || (string) $step->claim_token !== (string) $context->step->claim_token
                || $latestStepId !== (int) $step->id) {
                throw new AutomationNodeException(__('This worker no longer owns the Drive import node lease.'));
            }

            DB::table('automation_workflow_artifacts')->insertOrIgnore([
                'workflow_id' => (int) $context->run->workflow_id,
                'run_id' => (int) $context->run->id,
                'owner_user_id' => $context->ownerUserId(),
                'team_id' => $context->teamId(),
                'node_id' => (string) $context->step->node_id,
                'artifact_type' => 'google_drive_file',
                'artifact_key' => $artifactKey,
                'external_id' => $fileId,
                'status' => AutomationWorkflowArtifact::STATUS_RESERVED,
                'claim_token' => (string) $context->step->claim_token,
                'metadata' => json_encode(['artifact_note' => $artifactNote], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $artifact = AutomationWorkflowArtifact::query()
                ->where('artifact_key', $artifactKey)
                ->lockForUpdate()
                ->firstOrFail();
            if ((int) $artifact->run_id !== (int) $context->run->id
                || (string) $artifact->node_id !== (string) $context->step->node_id
                || (string) $artifact->external_id !== $fileId) {
                throw AutomationNodeException::permanent(__('The Drive artifact reservation does not match this workflow node.'));
            }

            if ($artifact->status === AutomationWorkflowArtifact::STATUS_READY && $artifact->app_file_id) {
                $file = AppFile::query()
                    ->whereKey($artifact->app_file_id)
                    ->where('owner_user_id', $context->ownerUserId())
                    ->where('team_id', $context->teamId())
                    ->where('is_folder', false)
                    ->first();
                if ($file) {
                    $artifact->setRelation('file', $file);

                    return $artifact;
                }
            }

            // Recover the narrow crash window after AppFile creation but before
            // the artifact row was marked ready. The durable reservation is the
            // concurrency authority; the note is only a recovery pointer.
            $recoveredFile = AppFile::query()
                ->where('owner_user_id', $context->ownerUserId())
                ->where('team_id', $context->teamId())
                ->where('note', $artifactNote)
                ->where('is_folder', false)
                ->first();
            if ($recoveredFile) {
                $artifact->forceFill([
                    'status' => AutomationWorkflowArtifact::STATUS_READY,
                    'app_file_id' => (int) $recoveredFile->id,
                    'claim_token' => null,
                    'error' => null,
                    'completed_at' => now(),
                ])->save();
                $artifact->setRelation('file', $recoveredFile);

                return $artifact;
            }

            $sameClaim = hash_equals((string) $artifact->claim_token, (string) $context->step->claim_token);
            $freshReservation = $artifact->status === AutomationWorkflowArtifact::STATUS_RESERVED
                && $artifact->updated_at?->greaterThan(now()->subMinutes(10));
            if (! $sameClaim && $freshReservation) {
                throw new AutomationNodeException(__('This Drive file is already being imported by another worker.'));
            }

            $artifact->forceFill([
                'status' => AutomationWorkflowArtifact::STATUS_RESERVED,
                'claim_token' => (string) $context->step->claim_token,
                'app_file_id' => null,
                'error' => null,
                'completed_at' => null,
                'metadata' => [
                    ...(array) $artifact->metadata,
                    'artifact_note' => $artifactNote,
                    'attempt' => (int) $context->step->attempt,
                ],
            ])->save();

            return $artifact->unsetRelation('file');
        });
    }

    protected function completeArtifact(
        AutomationWorkflowArtifact $artifact,
        AppFile $file,
        AutomationNodeContext $context,
    ): void {
        DB::transaction(function () use ($artifact, $file, $context): void {
            $locked = AutomationWorkflowArtifact::query()->whereKey($artifact->id)->lockForUpdate()->firstOrFail();
            if ((string) $locked->claim_token !== (string) $context->step->claim_token) {
                throw new AutomationNodeException(__('The Drive artifact lease changed before the import completed. The stored file will be recovered on retry.'));
            }

            $locked->forceFill([
                'status' => AutomationWorkflowArtifact::STATUS_READY,
                'app_file_id' => (int) $file->id,
                'claim_token' => null,
                'error' => null,
                'completed_at' => now(),
            ])->save();
        });
    }

    protected function failArtifact(
        AutomationWorkflowArtifact $artifact,
        \Throwable $exception,
        AutomationNodeContext $context,
    ): void {
        DB::transaction(function () use ($artifact, $exception, $context): void {
            $locked = AutomationWorkflowArtifact::query()->whereKey($artifact->id)->lockForUpdate()->first();
            if (! $locked || (string) $locked->claim_token !== (string) $context->step->claim_token) {
                return;
            }

            $locked->forceFill([
                'status' => AutomationWorkflowArtifact::STATUS_FAILED,
                'claim_token' => null,
                'error' => Str::limit($exception->getMessage(), 4000, ''),
            ])->save();
        });
    }

    protected function importFile(
        User $owner,
        AutomationConnection $connection,
        string $fileId,
        array $source,
        string $artifactNote,
        AutomationNodeContext $context,
    ): AppFile {
        $folder = AppFile::query()->firstOrCreate(
            [
                'owner_user_id' => $owner->id,
                'team_id' => $context->teamId(),
                'parent_id' => null,
                'is_folder' => true,
                'name' => __('Automation imports'),
            ],
            [
                'disk' => config('modules.appfiles.disk', config('filesystems.default', 'public')),
                'path' => null,
                'mime_type' => null,
                'extension' => null,
                'category' => 'folder',
                'size_bytes' => 0,
                'is_image' => false,
                'note' => __('Files imported by approved workspace workflows.'),
            ],
        );

        $file = $this->files->storeGoogleDriveFile(
            owner: $owner,
            fileId: $fileId,
            accessToken: $this->drive->accessToken($connection),
            parent: $folder,
            pickerDocument: [
                'name' => (string) ($source['name'] ?? ''),
                'resource_key' => (string) ($source['resource_key'] ?? ''),
                'note' => $artifactNote,
            ],
        );

        return $file->refresh();
    }

    protected function sourcePayload(AutomationNodeContext $context): array
    {
        $payload = $context->payload();

        return (array) ($payload['source'] ?? $payload['event'] ?? $payload);
    }

    protected function triggerConnectionId(AutomationNodeContext $context): int
    {
        $trigger = collect((array) ($context->definition['nodes'] ?? []))
            ->first(fn ($node): bool => is_array($node) && ($node['type'] ?? '') === 'trigger_google_drive');

        return (int) data_get($trigger, 'config.connection_id', 0);
    }

    protected function serializeFile(AppFile $file): array
    {
        $publicUrl = URL::signedRoute('portal.files.publish-preview', ['file' => $file]);

        return [
            'id' => (int) $file->id,
            'idSecure' => (string) $file->id_secure,
            'name' => (string) $file->name,
            'path' => ltrim((string) $file->path, '/'),
            'url' => $publicUrl,
            'previewUrl' => $publicUrl,
            'embedUrl' => $publicUrl,
            'category' => (string) $file->category,
            'mimeType' => (string) $file->mime_type,
            'extension' => (string) $file->extension,
            'isImage' => (bool) $file->is_image,
            'sizeBytes' => (int) $file->size_bytes,
        ];
    }
}
