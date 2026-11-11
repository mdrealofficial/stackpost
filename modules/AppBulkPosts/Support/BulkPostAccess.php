<?php

namespace Modules\AppBulkPosts\Support;

use Modules\AdminUser\Models\Team;
use Modules\AdminUser\Models\User;
use Modules\AppChannels\Models\SocialAccount;
use Modules\AppTeams\Support\TeamWorkspaceAccess;

class BulkPostAccess
{
    public static function allowed(?User $user, ?Team $team = null): bool
    {
        if (! $user) {
            return false;
        }
        if ($team && (int) $team->owner_user_id !== (int) $user->id && ! $team->members()->where('users.id', $user->id)->exists()) {
            return false;
        }
        $owner = $team?->owner ?: $user;

        return TeamWorkspaceAccess::teamHasModule($team, 'bulk_posts') && TeamWorkspaceAccess::hasPermission($user, 'bulk_posts.view', $team) && (! $owner->plan || $owner->canUsePlanFeature('bulk_posts'));
    }

    public static function accounts(User $user, ?Team $team = null)
    {
        $owner = $team?->owner ?: $user;
        $keys = publishable_channel_capability_keys($owner);
        $query = SocialAccount::query()->where('created_by_user_id', $owner->id)->where('is_active', true)->where(fn ($q) => $q->whereIn('capability_key', $keys)->orWhere(fn ($q) => $q->whereNull('capability_key')->whereIn('provider_key', $keys)));
        if ($team && (int) $team->owner_user_id !== (int) $user->id) {
            $query->whereIn('id', TeamWorkspaceAccess::managedAccountIds($user, $team) ?? []);
        }

        return $query;
    }

    public static function rowLimit(User $user, ?Team $team = null): int
    {
        $owner = $team?->owner ?: $user;
        $limit = (int) $owner->planLimit('max_bulk_posts_per_csv', 100);

        return $limit < 0 ? 5000 : min(5000, max(0, $limit));
    }
}
