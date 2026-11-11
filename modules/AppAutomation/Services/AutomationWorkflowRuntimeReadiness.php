<?php

namespace Modules\AppAutomation\Services;

use Modules\AdminUser\Models\Team;
use Modules\AdminUser\Models\User;
use Modules\AppAutomation\Models\AutomationConnection;
use Modules\AppAutomation\Models\AutomationWorkflow;
use Modules\AppAutomation\Services\GoogleDrive\GoogleDriveApiService;
use Modules\AppAutomation\Support\AutomationWorkflowGraph;
use Modules\AppAutomation\Support\AutomationWorkflowNodeCatalog;
use Modules\AppChannels\Models\SocialAccount;
use Modules\AppPublishing\Models\PublishingCampaign;
use Modules\AppPublishing\Models\PublishingLabel;
use Modules\AppTeams\Support\TeamWorkspaceAccess;
use RuntimeException;

class AutomationWorkflowRuntimeReadiness
{
    public function __construct(
        protected AutomationWorkflowHandlerRegistry $handlers,
        protected GoogleDriveApiService $drive,
        protected AutomationQueueReadiness $queue,
    ) {}

    public function issues(array $definition, int $ownerUserId, ?int $teamId, ?int $actorUserId): array
    {
        $definition = AutomationWorkflowGraph::sanitize($definition);
        $issues = AutomationWorkflowGraph::issues($definition);
        $owner = User::query()->with('plan')->find($ownerUserId);
        $actor = $actorUserId ? User::query()->find($actorUserId) : null;
        $team = $teamId ? Team::query()->with('members')->find($teamId) : null;

        if (! $owner) {
            return [__('The workflow workspace owner no longer exists.')];
        }
        if ($teamId && (! $team || (int) $team->owner_user_id !== (int) $owner->id)) {
            $issues[] = __('The workflow team no longer belongs to its saved workspace owner.');
        }
        if ($team && ! in_array('automation', TeamWorkspaceAccess::enabledModules($team), true)) {
            $issues[] = __('AI Workflows is disabled for this team workspace.');
        }
        if (! $actor || (! $team && (int) $actor->id !== (int) $owner->id)) {
            $issues[] = __('Choose an authorized workspace member to activate this workflow.');
        } elseif ($team && ! TeamWorkspaceAccess::hasPermission($actor, 'workflow.execute', $team)) {
            $issues[] = __('The activating member no longer has permission to run workflows.');
        }

        if ($owner->plan && ! $owner->canUsePlanFeature('automation')) {
            $issues[] = __('The current workspace plan does not include AI Workflows.');
        }

        $issues = [...$issues, ...$this->queue->issues()];

        $nodes = collect((array) ($definition['nodes'] ?? []));
        $unsupported = $nodes->pluck('type')
            ->filter(fn ($type): bool => ! $this->handlers->supports((string) $type))
            ->unique()->values();
        if ($unsupported->isNotEmpty()) {
            $issues[] = __('Runtime handlers are not available for: :nodes.', ['nodes' => $unsupported->implode(', ')]);
        }

        $providerGated = $nodes->filter(fn (array $node): bool => (string) data_get(
            AutomationWorkflowNodeCatalog::definitions(),
            ($node['type'] ?? '').'.runtime',
            'engine'
        ) === 'provider');
        if ($providerGated->isNotEmpty()) {
            $issues[] = __('Social comment, mention and DM workflows remain unavailable until a provider has verified inbound and reply connectors.');
        }
        if ($nodes->where('type', 'trigger_webhook')->isNotEmpty()) {
            $issues[] = __('The webhook trigger is not connected to the workflow dispatcher yet.');
        }

        if ($nodes->where('type', 'trigger_google_drive')->isNotEmpty()) {
            $issues = [...$issues, ...$this->driveIssues($nodes->all(), $owner, $team)];
        }
        if ($nodes->where('type', 'ai_write')->isNotEmpty()
            && $owner->plan
            && (! $owner->hasPlanFeature('ai_studio') || ! $owner->hasPlanFeature('ai_studio_caption_generator'))) {
            $issues[] = __('The current workspace plan does not include the AI caption writer used by this workflow.');
        }
        if ($nodes->where('type', 'create_draft')->isNotEmpty()) {
            $issues = [...$issues, ...$this->publishingIssues($nodes->all(), $owner, $team, $actor)];
            if (! $team && $nodes->where('type', 'approval')->isNotEmpty()) {
                $issues[] = __('Publishing approval workflows require a team workspace so an authorized reviewer can approve each draft.');
            }
        }

        return array_values(array_unique(array_filter($issues)));
    }

    public function assertRunnable(AutomationWorkflow $workflow, array $definition, ?int $requestedByUserId = null): void
    {
        $actorId = $requestedByUserId
            ?: (int) $workflow->activated_by_user_id
            ?: (int) $workflow->created_by_user_id
            ?: (int) $workflow->owner_user_id;
        $issues = $this->issues(
            $definition,
            (int) $workflow->owner_user_id,
            $workflow->team_id ? (int) $workflow->team_id : null,
            $actorId > 0 ? $actorId : null,
        );

        if ($issues !== []) {
            throw new RuntimeException((string) $issues[0]);
        }
    }

    protected function driveIssues(array $nodes, User $owner, ?Team $team): array
    {
        $issues = [];
        if ($owner->plan && (! $owner->hasPlanFeature('file_google_drive') || ! (bool) $owner->planLimit('workflow_google_drive', true))) {
            $issues[] = __('The current workspace plan does not include the Google Drive workflow trigger.');
        }

        $configuration = $this->drive->configuration();
        if (! $configuration['ready']) {
            $issues[] = __('Configure the Google Drive Automation OAuth client before activation.');
        } elseif (! $configuration['webhook_public']) {
            $issues[] = __('Google Drive background watching requires a public HTTPS webhook URL.');
        }

        foreach (collect($nodes)->where('type', 'trigger_google_drive') as $node) {
            $connectionId = (int) data_get($node, 'config.connection_id', 0);
            $connection = AutomationConnection::query()
                ->whereKey($connectionId)
                ->where('owner_user_id', $owner->id)
                ->where('provider', GoogleDriveApiService::PROVIDER)
                ->where('status', 'active')
                ->whereNull('disconnected_at')
                ->when($team, fn ($query, Team $team) => $query->where('team_id', $team->id), fn ($query) => $query->whereNull('team_id'))
                ->first();
            if (! $connection) {
                $issues[] = __('Choose an active Google Drive connection owned by this workspace.');
            }
        }

        return $issues;
    }

    protected function publishingIssues(array $nodes, User $owner, ?Team $team, ?User $actor): array
    {
        $issues = [];
        if ($owner->plan && ! $owner->hasPlanFeature('publishing')) {
            $issues[] = __('The current workspace plan does not include Publishing.');
        }
        if ($team && ! in_array('publishing', TeamWorkspaceAccess::enabledModules($team), true)) {
            $issues[] = __('Publishing is disabled for this team workspace.');
        }
        if ($actor && $team && ! TeamWorkspaceAccess::hasPermission($actor, 'post.create', $team)) {
            $issues[] = __('The activating member no longer has permission to create publishing drafts.');
        }

        $managedAccountIds = $actor ? TeamWorkspaceAccess::managedAccountIds($actor, $team) : [];
        $publishableKeys = publishable_channel_capability_keys($owner);
        foreach (collect($nodes)->where('type', 'create_draft') as $node) {
            $accountIds = collect((array) data_get($node, 'config.account_ids', []))->map(fn ($id): int => (int) $id)->filter()->unique();
            $validAccounts = SocialAccount::query()
                ->where('created_by_user_id', $owner->id)
                ->whereIn('id', $accountIds)
                ->where('is_active', true)
                ->when(is_array($managedAccountIds), fn ($query) => $query->whereIn('id', $managedAccountIds))
                ->where(function ($query) use ($publishableKeys): void {
                    $query->whereIn('capability_key', $publishableKeys)
                        ->orWhere(function ($fallback) use ($publishableKeys): void {
                            $fallback->where(function ($missingCapability): void {
                                $missingCapability->whereNull('capability_key')->orWhere('capability_key', '');
                            })->whereIn('provider_key', $publishableKeys);
                        });
                })
                ->count();
            if ($validAccounts !== $accountIds->count()) {
                $issues[] = __('One or more selected publishing accounts are unavailable to the activating member.');
            }

            $campaignId = (int) data_get($node, 'config.campaign_id', 0);
            if ($campaignId > 0 && ! PublishingCampaign::query()->ownedBy($owner->id)->whereKey($campaignId)->exists()) {
                $issues[] = __('The selected publishing campaign is no longer available.');
            }
            $labelIds = collect((array) data_get($node, 'config.label_ids', []))->map(fn ($id): int => (int) $id)->filter()->unique();
            if ($labelIds->isNotEmpty()
                && PublishingLabel::query()->ownedBy($owner->id)->whereIn('id', $labelIds)->count() !== $labelIds->count()) {
                $issues[] = __('One or more selected publishing labels are no longer available.');
            }
        }

        return $issues;
    }
}
