<?php

namespace Modules\AppTeams\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\AdminUser\Models\User;

/** Workspace libraries are private to their team. Legacy personal items stay with their owner. */
class PortalLibraryAccess
{
    public static function query(Builder $query, ?User $user = null): Builder
    {
        $user ??= auth()->user();
        $ownerId = TeamWorkspaceAccess::workspaceOwnerUserId($user);
        $teamId = TeamWorkspaceAccess::activeTeam($user)?->id;
        return $query->where('owner_user_id', $ownerId)->where(function (Builder $scope) use ($teamId, $user, $ownerId) {
            if ($teamId) {
                $scope->where('team_id', $teamId);
                if ((int) $user?->id === $ownerId) $scope->orWhereNull('team_id');
            } else {
                $scope->whereNull('team_id');
            }
        });
    }

    public static function canManage(string $permission, ?Model $item = null): bool
    {
        $user = auth()->user();
        if (!$user || !TeamWorkspaceAccess::hasPermission($user, $permission)) return false;
        return !$item || static::query($item->newQuery())->whereKey($item->getKey())->exists();
    }
}
