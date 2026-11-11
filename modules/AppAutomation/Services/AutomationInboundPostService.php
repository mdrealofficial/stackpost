<?php

namespace Modules\AppAutomation\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\AdminUser\Models\User;
use Modules\AppAutomation\Support\AutomationWorkspaceContext;
use Modules\AppAutomation\Support\AutomationWorkspaceScope;
use Modules\AppChannels\Models\SocialAccount;
use Modules\AppFiles\Support\FileManager;
use Modules\AppPublishing\Models\PublishingCampaign;
use Modules\AppPublishing\Models\PublishingLabel;
use Modules\AppPublishing\Models\PublishingPost;
use Modules\AppPublishing\Support\PublishingContentValidationRegistry;
use Modules\AppPublishing\Support\PublishingMediaValidationRegistry;
use Modules\AppPublishing\Support\PublishingNetworkOptionsRegistry;
use RuntimeException;

class AutomationInboundPostService
{
    public function __construct(
        protected FileManager $files,
        protected PublishingContentValidationRegistry $contentValidators,
        protected PublishingMediaValidationRegistry $mediaValidators,
        protected PublishingNetworkOptionsRegistry $networkOptions,
        protected AutomationWorkspaceScope $workspaces,
    ) {}

    public function create(User $user, array $payload): Collection
    {
        return $this->createForWorkspace(
            new AutomationWorkspaceContext($user, null),
            $payload,
        );
    }

    public function createForWorkspace(AutomationWorkspaceContext $workspace, array $payload): Collection
    {
        $workspaceOwnerUserId = $workspace->ownerUserId();
        $teamId = $workspace->teamId();
        $accountIds = collect((array) ($payload['account_ids'] ?? []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($accountIds->isEmpty()) {
            throw new RuntimeException('At least one account_id is required.');
        }

        $publishableCapabilityKeys = $this->workspaces->publishableCapabilityKeys($workspace);
        $accounts = $this->workspaces->accountsQuery($workspace)
            ->where(function ($query) use ($publishableCapabilityKeys): void {
                $query->whereIn('capability_key', $publishableCapabilityKeys)
                    ->orWhere(function ($fallbackQuery) use ($publishableCapabilityKeys): void {
                        $fallbackQuery->whereNull('capability_key')
                            ->whereIn('provider_key', $publishableCapabilityKeys);
                    });
            })
            ->whereIn('id', $accountIds->all())
            ->get()
            ->keyBy('id');

        if ($accounts->count() !== $accountIds->count()) {
            throw new RuntimeException('One or more selected automation channels are not available in this workspace.');
        }

        $caption = trim((string) ($payload['caption'] ?? ''));
        $this->validateSchedule($payload);
        $mediaItems = $this->importRemoteMedia($workspace, (array) ($payload['media_urls'] ?? []));
        $mode = $this->normalizeMode($payload['mode'] ?? 'scheduled');
        $nowTimestamp = now()->timestamp;
        $timePost = $this->resolveScheduleTimestamp($payload, $mode, $nowTimestamp);
        $status = match ($mode) {
            'draft' => PublishingPost::STATUS_DRAFT,
            'publish_now' => PublishingPost::STATUS_SCHEDULED,
            default => PublishingPost::STATUS_SCHEDULED,
        };
        $campaignId = $this->resolveCampaignId($workspaceOwnerUserId, $payload);
        $labelIds = $this->resolveLabelIds($workspaceOwnerUserId, $payload);

        return $accounts->values()->map(function (SocialAccount $account) use ($workspaceOwnerUserId, $teamId, $caption, $mediaItems, $payload, $timePost, $status, $campaignId, $labelIds, $nowTimestamp, $mode, $workspace) {
            $providerKey = (string) $account->provider_key;
            $providerOptions = $this->networkOptions->resolve($providerKey, (array) data_get($payload, 'network_options.'.$providerKey, []));
            $contentValidation = $this->contentValidators->validate($providerKey, [
                'caption' => $caption,
                'options' => $providerOptions,
                'media_items' => $mediaItems,
            ]);

            if ($contentValidation !== null) {
                throw new RuntimeException((string) ($contentValidation['message'] ?? 'The automation payload is missing required content.'));
            }

            $mediaError = $this->mediaValidators->validate($providerKey, [
                'caption' => $caption,
                'options' => $providerOptions,
                'media_items' => $mediaItems,
            ]);

            if ($mediaError !== null) {
                throw new RuntimeException($mediaError);
            }

            return PublishingPost::query()->create([
                'id_secure' => Str::random(32),
                'user_id' => $workspaceOwnerUserId,
                'team_id' => $teamId,
                'campaign' => $campaignId,
                'labels' => $labelIds,
                'account_id' => $account->id,
                'social_network' => $providerKey,
                'category' => (string) ($account->capability_key ?: $account->category ?: $providerKey),
                'module' => $providerKey,
                'function' => 'post',
                'api_type' => 1,
                'type' => $mediaItems === [] ? 'text' : 'media',
                'method' => 'automation_api',
                'query_id' => null,
                'data' => [
                    'title' => trim((string) Str::limit($caption, 46, '...')),
                    'caption' => $caption,
                    'media_type' => $mediaItems === [] ? 'text' : (string) ($payload['media_type'] ?? 'image'),
                    'medias' => $mediaItems,
                    'options' => [
                        ...$providerOptions,
                        'schedule_mode' => $mode === 'publish_now' ? 'immediately' : 'specific_days_times',
                        'timezone' => (string) ($payload['timezone'] ?? ($workspace->owner->timezone ?: config('app.timezone'))),
                        'campaign_id' => $campaignId,
                        'label_ids' => $labelIds,
                        'notes' => trim((string) ($payload['notes'] ?? '')),
                        'automation' => [
                            'source' => (string) ($payload['source'] ?? 'api'),
                            'external_reference' => (string) ($payload['external_reference'] ?? ''),
                        ],
                    ],
                ],
                'time_post' => $timePost,
                'delay' => 0,
                'repost_frequency' => 0,
                'repost_until' => null,
                'result' => null,
                'tmp' => null,
                'custom_data_1' => (string) ($payload['external_reference'] ?? ''),
                'custom_data_2' => (string) ($payload['source'] ?? 'api'),
                'custom_data_3' => 'automation-api',
                'status' => $status,
                'changed' => $nowTimestamp,
                'created' => $nowTimestamp,
            ]);
        });
    }

    protected function importRemoteMedia(AutomationWorkspaceContext $workspace, array $urls): array
    {
        return collect($urls)
            ->map(fn ($url) => trim((string) $url))
            ->filter()
            ->values()
            ->map(function (string $url) use ($workspace): array {
                $file = $this->files->storeRemoteFileInWorkspace(
                    $workspace->owner,
                    $workspace->teamId(),
                    $url,
                );
                $previewUrl = route('portal.files.preview', $file);

                return [
                    'id' => (int) $file->id,
                    'id_secure' => (string) $file->id_secure,
                    'name' => (string) $file->name,
                    'url' => $previewUrl,
                    'previewUrl' => $previewUrl,
                    'embedUrl' => $previewUrl,
                    'mimeType' => (string) $file->mime_type,
                    'extension' => (string) $file->extension,
                    'category' => (string) $file->category,
                    'isImage' => (bool) $file->is_image,
                ];
            })
            ->values()
            ->all();
    }

    protected function normalizeMode(mixed $mode): string
    {
        $mode = trim((string) $mode);

        return match ($mode) {
            'draft' => 'draft',
            'publish_now', 'now' => 'publish_now',
            default => 'scheduled',
        };
    }

    public function validateSchedule(array $payload): int
    {
        $mode = $this->normalizeMode($payload['mode'] ?? 'scheduled');
        if ($mode !== 'scheduled') return now()->timestamp;
        $value = trim((string) ($payload['schedule_at'] ?? ''));
        try {
            if ($value === '') throw new RuntimeException('Missing schedule');
            $time = Carbon::parse($value, $payload['timezone'] ?? config('app.timezone'));
            if (!$time->isFuture()) throw new RuntimeException('Past schedule');
            return $time->timestamp;
        } catch (\Throwable $e) {
            throw \Illuminate\Validation\ValidationException::withMessages(['schedule_at' => __('Scheduled posts require a valid date and time in the future, including a timezone or UTC offset.')]);
        }
    }

    protected function resolveScheduleTimestamp(array $payload, string $mode, int $fallback): int
    {
        return $mode === 'scheduled' ? $this->validateSchedule($payload) : $fallback;
    }

    protected function resolveCampaignId(int $workspaceOwnerUserId, array $payload): ?int
    {
        $campaignId = (int) ($payload['campaign_id'] ?? 0);

        if ($campaignId <= 0) {
            return null;
        }

        return PublishingCampaign::query()
            ->ownedBy($workspaceOwnerUserId)
            ->whereKey($campaignId)
            ->value('id');
    }

    protected function resolveLabelIds(int $workspaceOwnerUserId, array $payload): array
    {
        $labelIds = collect((array) ($payload['label_ids'] ?? []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values();

        if ($labelIds->isEmpty()) {
            return [];
        }

        return PublishingLabel::query()
            ->ownedBy($workspaceOwnerUserId)
            ->whereIn('id', $labelIds->all())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
