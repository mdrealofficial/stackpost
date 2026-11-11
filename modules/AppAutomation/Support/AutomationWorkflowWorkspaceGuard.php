<?php

namespace Modules\AppAutomation\Support;

use Illuminate\Support\Collection;
use Modules\AdminUser\Models\Team;
use Modules\AdminUser\Models\User;
use Modules\AppAutomation\Exceptions\AutomationNodeException;
use Modules\AppAutomation\Runtime\AutomationNodeContext;
use Modules\AppChannels\Models\SocialAccount;
use Modules\AppTeams\Support\TeamWorkspaceAccess;

class AutomationWorkflowWorkspaceGuard
{
    /**
     * Resolve the workflow actor exclusively from persisted run identifiers.
     * No active-team or authenticated-session state is consulted.
     *
     * @return array{owner: User, requester: User, team: ?Team, permissions: array<int, string>, managed_account_ids: ?array<int, int>}
     */
    public function resolve(AutomationNodeContext $context, array $requiredPermissions = []): array
    {
        $owner = User::query()->with('plan')->find($context->ownerUserId());
        if (! $owner) {
            throw AutomationNodeException::permanent(__('The workflow workspace owner no longer exists.'));
        }

        $team = null;
        if ($context->teamId()) {
            $team = Team::query()
                ->with('members')
                ->whereKey($context->teamId())
                ->where('owner_user_id', $owner->id)
                ->first();

            if (! $team) {
                throw AutomationNodeException::permanent(__('The workflow team is no longer available to its persisted owner.'));
            }
        }

        $requesterId = $context->requestedByUserId() ?: (int) $owner->id;
        $requester = User::query()->find($requesterId);
        if (! $requester) {
            throw AutomationNodeException::permanent(__('The user who requested this workflow run no longer exists.'));
        }

        $isOwner = (int) $requester->id === (int) $owner->id;
        if (! $team && ! $isOwner) {
            throw AutomationNodeException::permanent(__('Only the workspace owner can execute a personal workflow.'));
        }

        if ($team && ! $isOwner && ! $team->members->contains(fn (User $member): bool => (int) $member->id === (int) $requester->id)) {
            throw AutomationNodeException::permanent(__('The workflow requester is no longer a member of this workspace.'));
        }

        foreach (['automation', 'publishing'] as $module) {
            if ($team && ! $isOwner && ! in_array($module, TeamWorkspaceAccess::enabledModules($team), true)) {
                throw AutomationNodeException::permanent(__(':module is disabled for this workspace.', [
                    'module' => str($module)->headline()->value(),
                ]));
            }

            if (! $owner->canUsePlanFeature($module)) {
                throw AutomationNodeException::permanent(__(':module is not available in the workspace plan.', [
                    'module' => str($module)->headline()->value(),
                ]));
            }
        }

        $permissions = $team
            ? TeamWorkspaceAccess::permissionsForUser($requester, $team)
            : [];

        foreach ($requiredPermissions as $permission) {
            if (! $isOwner && ! in_array((string) $permission, $permissions, true)) {
                throw AutomationNodeException::permanent(__('The workflow requester no longer has the :permission permission.', [
                    'permission' => (string) $permission,
                ]));
            }
        }

        return [
            'owner' => $owner,
            'requester' => $requester,
            'team' => $team,
            'permissions' => $permissions,
            'managed_account_ids' => $team
                ? TeamWorkspaceAccess::managedAccountIds($requester, $team)
                : null,
        ];
    }

    /**
     * @param  array{owner: User, requester: User, team: ?Team, permissions: array<int, string>, managed_account_ids: ?array<int, int>}  $actor
     * @param  array<int, int>  $accountIds
     * @return Collection<int, SocialAccount>
     */
    public function publishingAccounts(array $actor, array $accountIds): Collection
    {
        $ids = collect($accountIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            throw AutomationNodeException::permanent(__('Select at least one connected publishing account.'));
        }

        $query = SocialAccount::query()
            ->where('created_by_user_id', (int) $actor['owner']->id)
            ->where('is_active', true)
            ->whereIn('id', $ids->all());

        if (is_array($actor['managed_account_ids'])) {
            $query->whereIn('id', $actor['managed_account_ids']);
        }

        $publishableKeys = $this->publishableCapabilityKeys($actor['owner']);
        $query->where(function ($builder) use ($publishableKeys): void {
            $builder->whereIn('capability_key', $publishableKeys)
                ->orWhere(function ($fallback) use ($publishableKeys): void {
                    $fallback->where(function ($missingCapability): void {
                        $missingCapability->whereNull('capability_key')->orWhere('capability_key', '');
                    })->whereIn('provider_key', $publishableKeys);
                });
        });

        $accounts = $query->get()->keyBy('id');
        if ($accounts->count() !== $ids->count()) {
            throw AutomationNodeException::permanent(__('One or more selected accounts are inactive, inaccessible, or not publishable in this workspace plan.'));
        }

        return $ids->map(fn (int $id): SocialAccount => $accounts->get($id));
    }

    /** @return array<int, string> */
    protected function publishableCapabilityKeys(User $owner): array
    {
        $keys = collect(channel_capabilities())
            ->filter(fn (array $capability): bool => (bool) ($capability['supports_publishing'] ?? true))
            ->keys()
            ->map(fn ($key): string => (string) $key)
            ->values();
        $permissions = (array) ($owner->plan?->permissions ?? []);
        $hasExplicitSelections = $keys->contains(fn (string $key): bool => array_key_exists($key, $permissions));

        return $hasExplicitSelections
            ? $keys->filter(fn (string $key): bool => $owner->hasPlanFeature($key))->values()->all()
            : $keys->all();
    }
}
