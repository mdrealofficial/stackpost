<?php
namespace App\Social\Automation;

use Modules\AppTeams\Support\TeamWorkspaceAccess as Teams;
use Modules\AdminUser\Models\Team;
use Modules\AdminUser\Models\User;
use Modules\AppChannels\Models\SocialAccount;

class Access
{
    public function can(string $key, string $action): bool
    {
        $user = auth()->user();
        $team = Teams::activeTeam($user);
        $owner = $team?->owner ?: $user;
        return app(Registry::class)->get($key) !== null && $owner?->canUsePlanFeature($key)
            && Teams::teamHasModule($team, $key) && Teams::hasPermission($user, $key.'.'.$action, $team);
    }

    public function ruleAllowed(string $key, Rule $rule, SocialAccount $account): bool
    {
        $owner = User::find($rule->owner_user_id);
        $creator = User::find($rule->created_by_user_id);
        $team = $rule->team_id ? Team::find($rule->team_id) : null;
        $adminBypass = $owner?->isSuperAdmin() || $creator?->isSuperAdmin();
        if (!$owner || !$creator || !$owner->canUsePlanFeature($key)
            || (int)$account->created_by_user_id !== (int)$owner->id
            || ($rule->team_id && (!$team || (int)$team->owner_user_id !== (int)$owner->id))) return false;
        if (!$team && (int)$creator->id !== (int)$owner->id) return false;
        if ($team && !$adminBypass && !in_array($key, Teams::enabledModules($team), true)) return false;
        Teams::clearCache();
        if (!Teams::hasPermission($creator, $key.'.manage', $team)) return false;
        $ids = Teams::managedAccountIds($creator, $team);
        return $ids === null || in_array((int)$account->id, array_map('intval', $ids), true);
    }
}
