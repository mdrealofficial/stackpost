<?php

namespace Modules\AppAutomation\Runtime\Handlers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Modules\AppAutomation\Contracts\AutomationNodeHandler;
use Modules\AppAutomation\Exceptions\AutomationNodeException;
use Modules\AppAutomation\Models\AutomationWorkflowRun;
use Modules\AppAutomation\Runtime\AutomationNodeContext;
use Modules\AppAutomation\Runtime\AutomationNodeResult;
use Modules\AppAutomation\Support\AutomationWorkflowWorkspaceGuard;
use Modules\AppChannels\Models\SocialAccount;
use Modules\AppFiles\Models\AppFile;
use Modules\AppPublishing\Models\PublishingCampaign;
use Modules\AppPublishing\Models\PublishingLabel;
use Modules\AppPublishing\Models\PublishingPost;
use Modules\AppPublishing\Support\PublishingNetworkConfigRegistry;
use Modules\AppPublishing\Support\PublishingNetworkOptionsRegistry;

class CreateDraftNodeHandler implements AutomationNodeHandler
{
    public function __construct(
        protected AutomationWorkflowWorkspaceGuard $guard,
        protected PublishingNetworkConfigRegistry $networkConfigs,
        protected PublishingNetworkOptionsRegistry $networkOptions,
    ) {}

    public function handle(AutomationNodeContext $context): AutomationNodeResult
    {
        $actor = $this->guard->resolve($context, ['workflow.execute', 'post.create']);
        $payload = $context->payload();
        $accountIds = (array) $context->config('account_ids', $payload['account_ids'] ?? []);
        $accounts = $this->guard->publishingAccounts($actor, $accountIds);
        $campaignId = $this->campaignId($context, $payload);
        $labelIds = $this->labelIds($context, $payload);
        $media = $this->mediaFromPayload($context, $payload);
        $idempotencyKey = $context->idempotencyKey();

        $prepared = $accounts->map(function (SocialAccount $account) use ($payload, $media): array {
            $providerKey = strtolower(trim((string) $account->provider_key));
            $caption = $this->captionForProvider($payload, $providerKey);
            if ($caption === '' && $media === []) {
                throw AutomationNodeException::permanent(__(':account has neither a generated caption nor an imported media file.', [
                    'account' => $account->display_name ?: $account->username ?: Str::headline($providerKey),
                ]));
            }

            $config = $this->networkConfigs->get($providerKey, [
                'label' => Str::headline($providerKey),
                'default_post_to' => 'feed',
                'post_to_options' => [['key' => 'feed', 'label' => __('Feed')]],
            ]);
            $resolved = $this->networkOptions->resolve(
                $providerKey,
                (array) data_get($payload, 'network_options.'.$providerKey, []),
                $config,
            );

            return [
                'account' => $account,
                'caption' => $caption,
                'options' => (array) ($resolved['options'] ?? []),
                'media_type' => $media === [] ? 'text' : $this->mediaType($media, (string) ($resolved['media_type'] ?? '')),
            ];
        });

        $posts = DB::transaction(function () use (
            $context,
            $actor,
            $prepared,
            $media,
            $campaignId,
            $labelIds,
            $idempotencyKey,
            $payload,
        ) {
            AutomationWorkflowRun::query()->whereKey($context->run->id)->lockForUpdate()->firstOrFail();
            $now = now()->timestamp;

            return $prepared->map(function (array $item) use (
                $context,
                $actor,
                $media,
                $campaignId,
                $labelIds,
                $idempotencyKey,
                $payload,
                $now,
            ): PublishingPost {
                /** @var SocialAccount $account */
                $account = $item['account'];
                $existing = PublishingPost::query()
                    ->where('user_id', $context->ownerUserId())
                    ->where('team_id', $context->teamId())
                    ->where('account_id', $account->id)
                    ->where('custom_data_1', $idempotencyKey)
                    ->where('custom_data_3', 'automation-workflow')
                    ->first();

                if ($existing) {
                    return $existing;
                }

                $caption = (string) $item['caption'];
                $timezone = $this->timezoneFor($actor['owner'], (string) data_get($payload, 'timezone', ''));

                return PublishingPost::query()->create([
                    'id_secure' => Str::random(32),
                    'user_id' => (int) $actor['owner']->id,
                    'team_id' => $actor['team']?->id,
                    'campaign' => $campaignId,
                    'labels' => $labelIds,
                    'account_id' => (int) $account->id,
                    'social_network' => (string) $account->provider_key,
                    'category' => (string) ($account->capability_key ?: $account->category ?: $account->provider_key),
                    'module' => (string) $account->provider_key,
                    'function' => 'post',
                    'api_type' => 1,
                    'type' => $media === [] ? 'text' : 'media',
                    'method' => 'automation',
                    'query_id' => null,
                    'data' => [
                        'title' => trim((string) Str::limit($caption !== '' ? $caption : (string) data_get($media, '0.name', __('Imported media')), 46, '...')),
                        'caption' => $caption,
                        'media_type' => (string) $item['media_type'],
                        'medias' => $media,
                        'ai' => [
                            'caption_variants' => array_values((array) ($payload['variants'] ?? [])),
                            'source' => (string) (data_get($payload, 'source.provider') ?: ($payload['generated_source'] ?? 'automation')),
                        ],
                        'options' => [
                            ...$item['options'],
                            'schedule_mode' => 'draft',
                            'schedule_slot_index' => 0,
                            'timezone' => $timezone,
                            'campaign_id' => $campaignId,
                            'label_ids' => $labelIds,
                            'automation' => [
                                'workflow_id' => (int) $context->run->workflow_id,
                                'run_id' => (int) $context->run->id,
                                'step_id' => (int) $context->step->id,
                                'node_id' => (string) $context->step->node_id,
                                'requested_by_user_id' => (int) $actor['requester']->id,
                                'idempotency_key' => $idempotencyKey,
                            ],
                        ],
                    ],
                    'time_post' => null,
                    'delay' => 0,
                    'repost_frequency' => 0,
                    'repost_until' => null,
                    'result' => null,
                    'tmp' => null,
                    'custom_data_1' => $idempotencyKey,
                    'custom_data_2' => 'workflow:'.(int) $context->run->workflow_id.':run:'.(int) $context->run->id,
                    'custom_data_3' => 'automation-workflow',
                    'status' => PublishingPost::STATUS_DRAFT,
                    'changed' => $now,
                    'created' => $now,
                ]);
            })->values();
        });

        return AutomationNodeResult::success([
            ...$payload,
            'post_ids' => $posts->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            'posts' => $posts->map(fn (PublishingPost $post): array => [
                'id' => (int) $post->id,
                'id_secure' => (string) $post->id_secure,
                'account_id' => (int) $post->account_id,
                'provider' => (string) $post->social_network,
                'status' => 'draft',
            ])->all(),
            'campaign_id' => $campaignId,
            'label_ids' => $labelIds,
            'drafts_created' => $posts->count(),
        ]);
    }

    protected function campaignId(AutomationNodeContext $context, array $payload): ?int
    {
        $requested = (int) $context->config('campaign_id', $payload['campaign_id'] ?? 0);
        if ($requested <= 0) {
            return null;
        }

        $id = PublishingCampaign::query()->ownedBy($context->ownerUserId())->whereKey($requested)->value('id');
        if (! $id) {
            throw AutomationNodeException::permanent(__('The selected campaign does not belong to this workflow workspace.'));
        }

        return (int) $id;
    }

    /** @return array<int, int> */
    protected function labelIds(AutomationNodeContext $context, array $payload): array
    {
        $requested = collect((array) $context->config('label_ids', $payload['label_ids'] ?? []))
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();
        if ($requested->isEmpty()) {
            return [];
        }

        $ids = PublishingLabel::query()
            ->ownedBy($context->ownerUserId())
            ->whereIn('id', $requested->all())
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values();
        if ($ids->count() !== $requested->count()) {
            throw AutomationNodeException::permanent(__('One or more selected labels do not belong to this workflow workspace.'));
        }

        return $ids->all();
    }

    /** @return array<int, array<string, mixed>> */
    protected function mediaFromPayload(AutomationNodeContext $context, array $payload): array
    {
        $candidate = (array) ($payload['file'] ?? []);
        if ($candidate === []) {
            return [];
        }

        $fileId = (int) ($candidate['id'] ?? 0);
        $secureId = trim((string) ($candidate['idSecure'] ?? $candidate['id_secure'] ?? ''));
        $file = AppFile::query()
            ->where('owner_user_id', $context->ownerUserId())
            ->when(
                $context->teamId(),
                fn ($query, $teamId) => $query->where('team_id', $teamId),
                fn ($query) => $query->whereNull('team_id'),
            )
            ->where('is_folder', false)
            ->when($fileId > 0, fn ($query) => $query->whereKey($fileId), fn ($query) => $query->where('id_secure', $secureId))
            ->first();

        if (! $file || ($fileId <= 0 && $secureId === '')) {
            throw AutomationNodeException::permanent(__('The imported file is unavailable in this persisted workflow workspace.'));
        }

        $url = URL::signedRoute('portal.files.publish-preview', ['file' => $file]);

        return [[
            'id' => (int) $file->id,
            'id_secure' => (string) $file->id_secure,
            'idSecure' => (string) $file->id_secure,
            'name' => (string) $file->name,
            'url' => $url,
            'previewUrl' => $url,
            'embedUrl' => $url,
            'mimeType' => (string) $file->mime_type,
            'extension' => (string) $file->extension,
            'category' => (string) $file->category,
            'isImage' => (bool) $file->is_image,
            'sizeBytes' => (int) $file->size_bytes,
        ]];
    }

    protected function captionForProvider(array $payload, string $providerKey): string
    {
        $aliases = match ($providerKey) {
            'x' => ['x', 'twitter'],
            'twitter' => ['twitter', 'x'],
            default => [$providerKey],
        };
        $variants = collect((array) ($payload['variants'] ?? []));
        $variant = $variants->first(function ($item) use ($aliases): bool {
            if (! is_array($item)) {
                return false;
            }

            $platform = strtolower(trim((string) ($item['platform'] ?? $item['provider'] ?? $item['target'] ?? $item['network'] ?? '')));

            return in_array($platform, $aliases, true);
        });
        $variant ??= $variants->first(fn ($item): bool => is_array($item));
        $variant = is_array($variant) ? $variant : [];
        $caption = trim((string) ($variant['caption'] ?? $variant['content'] ?? $variant['text'] ?? $payload['caption'] ?? $payload['content'] ?? $payload['text'] ?? ''));
        $cta = trim((string) ($variant['cta'] ?? ''));
        $hashtags = collect((array) ($variant['hashtags'] ?? []))
            ->map(fn ($tag): string => '#'.ltrim(trim((string) $tag), '#'))
            ->filter(fn (string $tag): bool => $tag !== '#')
            ->unique()
            ->implode(' ');

        return trim(implode("\n\n", array_filter([$caption, $cta, $hashtags])));
    }

    protected function mediaType(array $media, string $providerDefault): string
    {
        return match ((string) data_get($media, '0.category')) {
            'video' => 'video',
            'audio' => 'audio',
            'image' => $providerDefault !== '' ? $providerDefault : 'image',
            default => $providerDefault !== '' ? $providerDefault : 'media',
        };
    }

    protected function timezoneFor($owner, string $requested): string
    {
        foreach ([$requested, (string) $owner->timezone, (string) config('app.timezone', 'UTC'), 'UTC'] as $timezone) {
            try {
                if (trim($timezone) !== '') {
                    new \DateTimeZone($timezone);

                    return $timezone;
                }
            } catch (\Throwable) {
            }
        }

        return 'UTC';
    }
}
