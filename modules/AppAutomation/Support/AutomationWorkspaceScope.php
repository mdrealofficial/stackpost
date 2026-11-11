<?php

namespace Modules\AppAutomation\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\AdminUser\Models\Team;
use Modules\AdminUser\Models\User;
use Modules\AppAutomation\Models\AutomationApiKey;
use Modules\AppChannels\Models\SocialAccount;

class AutomationWorkspaceScope
{
    public function contextForApiKey(AutomationApiKey $apiKey): ?AutomationWorkspaceContext
    {
        return $this->contextForOwnerAndTeam(
            (int) $apiKey->user_id,
            $apiKey->team_id === null ? null : (int) $apiKey->team_id,
        );
    }

    public function contextForOwnerAndTeam(int $ownerUserId, ?int $teamId): ?AutomationWorkspaceContext
    {
        /** @var User|null $owner */
        $owner = User::query()->with('plan')->find($ownerUserId);

        if (! $owner) {
            return null;
        }

        if ($teamId === null) {
            return new AutomationWorkspaceContext($owner, null);
        }

        /** @var Team|null $team */
        $team = Team::query()
            ->with(['owner.plan'])
            ->whereKey($teamId)
            ->where('owner_user_id', (int) $owner->id)
            ->first();

        return $team ? new AutomationWorkspaceContext($owner, $team) : null;
    }

    public function apply(Builder $query, int $ownerUserId, ?int $teamId, string $ownerColumn = 'user_id'): Builder
    {
        $query->where($ownerColumn, $ownerUserId);

        return $teamId === null
            ? $query->whereNull('team_id')
            : $query->where('team_id', $teamId);
    }

    public function matches(Model $record, int $ownerUserId, ?int $teamId, string $ownerColumn = 'user_id'): bool
    {
        $recordTeamId = $record->getAttribute('team_id');

        return (int) $record->getAttribute($ownerColumn) === $ownerUserId
            && ($recordTeamId === null ? $teamId === null : (int) $recordTeamId === $teamId);
    }

    public function accountsQuery(AutomationWorkspaceContext $workspace): Builder
    {
        return SocialAccount::query()->where('created_by_user_id', $workspace->ownerUserId());
    }

    /**
     * Resolve publishable capability keys from the persisted workspace owner,
     * without consulting the active portal session.
     *
     * @return array<int, string>
     */
    public function publishableCapabilityKeys(AutomationWorkspaceContext $workspace): array
    {
        $keys = collect(channel_capabilities())
            ->filter(fn (array $capability): bool => (bool) ($capability['supports_publishing'] ?? true))
            ->keys()
            ->values()
            ->all();
        $planPermissions = (array) ($workspace->owner->plan?->permissions ?? []);
        $hasExplicitPublishingSelections = collect($keys)
            ->contains(fn (string $key): bool => array_key_exists($key, $planPermissions));

        if (! $hasExplicitPublishingSelections) {
            return $keys;
        }

        return collect($keys)
            ->filter(fn (string $key): bool => $workspace->owner->hasPlanFeature($key))
            ->values()
            ->all();
    }
}
