<?php

namespace Modules\AppAutomation\Support;

use Modules\AdminUser\Models\Team;
use Modules\AdminUser\Models\User;
use Modules\AppTeams\Support\TeamWorkspaceAccess;

class AutomationAccess
{
    public function enabled(?User $user): bool
    {
        $team = TeamWorkspaceAccess::activeTeam($user);

        return $this->enabledForWorkspace($team?->owner ?: $user, $team);
    }

    public function enabledForWorkspace(?User $owner, ?Team $team): bool
    {
        if (! $owner) {
            return false;
        }

        if ($team) {
            if ((int) $team->owner_user_id !== (int) $owner->id) {
                return false;
            }

            if (! in_array('automation', TeamWorkspaceAccess::enabledModules($team), true)) {
                return false;
            }
        }

        return ! $owner->plan || $owner->canUsePlanFeature('automation');
    }

    public function workspaceOwnerUserId(?User $user): int
    {
        return TeamWorkspaceAccess::workspaceOwnerUserId($user);
    }

    public function teamId(?User $user): ?int
    {
        return TeamWorkspaceAccess::activeTeam($user)?->id;
    }

    public function canView(?User $user): bool
    {
        return $this->canAccessWorkflowWorkspace($user) && TeamWorkspaceAccess::hasPermission($user, 'workflow.view');
    }

    public function canManage(?User $user): bool
    {
        return $this->canAccessWorkflowWorkspace($user) && TeamWorkspaceAccess::hasPermission($user, 'workflow.manage');
    }

    public function canExecute(?User $user): bool
    {
        return $this->enabled($user) && TeamWorkspaceAccess::hasPermission($user, 'workflow.execute');
    }

    public function canManageCredentials(?User $user): bool
    {
        return $this->canAccessWorkspaceModule($user, 'automation')
            && TeamWorkspaceAccess::hasPermission($user, 'automation.credentials.manage');
    }

    protected function canAccessWorkflowWorkspace(?User $user): bool
    {
        return $this->canAccessWorkspaceModule($user, 'ai_workflows')
            || $this->canAccessWorkspaceModule($user, 'automation');
    }

    protected function canAccessWorkspaceModule(?User $user, string $module): bool
    {
        if (! $user) return false;
        $team = TeamWorkspaceAccess::activeTeam($user);
        $owner = $team?->owner ?: $user;

        // Portal access follows the shared owner/member policy used by navigation.
        // Background execution and API requests retain enabledForWorkspace checks.
        return (! $owner->plan || $owner->canUsePlanFeature('automation'))
            && TeamWorkspaceAccess::teamHasModule($team, $module);
    }
}
