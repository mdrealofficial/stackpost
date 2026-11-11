<?php

namespace Modules\AppChannels\Support;

use Modules\AdminUser\Models\User;
use Modules\AppChannels\Models\SocialAccount;
use Modules\AppTeams\Support\TeamWorkspaceAccess;

class ChannelPlanAccess
{
    public function channelsEnabled(?User $user): bool
    {
        $team = TeamWorkspaceAccess::activeTeam($user);
        $planOwner = $this->planOwner($user);

        if (! TeamWorkspaceAccess::teamHasModule($team, 'channels')) {
            return false;
        }

        if (! TeamWorkspaceAccess::hasPermission($user, 'channel.view', $team)) {
            return false;
        }

        return $planOwner?->canUsePlanFeature('channels') ?? false;
    }

    public function canCreate(?User $user): bool
    {
        return $this->canManage($user);
    }

    public function canEdit(?User $user): bool
    {
        return $this->canManage($user);
    }

    public function canDelete(?User $user): bool
    {
        return $this->canManage($user);
    }

    public function canReconnect(?User $user): bool
    {
        return $this->canManage($user);
    }

    public function canToggleStatus(?User $user): bool
    {
        return $this->canManage($user);
    }

    public function allowedCapabilityKeys(?User $user): ?array
    {
        if (! $this->channelsEnabled($user)) {
            return [];
        }

        $planOwner = $this->planOwner($user);
        $allowedPermissions = collect((array) ($planOwner?->planLimit('allowed_channels', []) ?? []));

        $keys = collect(channel_capabilities())
            ->filter(fn (array $capability, string $capabilityKey): bool => $planOwner?->hasPlanFeature($this->permissionKeyFromCapability($capabilityKey, $capability)) ?? false)
            ->keys()
            ->values()
            ->all();

        if ($allowedPermissions->isEmpty()) {
            return $keys;
        }

        return $keys;
    }

    public function canUseCapability(?User $user, string $capabilityKey): bool
    {
        $allowed = $this->allowedCapabilityKeys($user);

        return in_array($capabilityKey, $allowed, true);
    }

    protected function canManage(?User $user): bool
    {
        $team = TeamWorkspaceAccess::activeTeam($user);

        return $this->channelsEnabled($user)
            && TeamWorkspaceAccess::hasPermission($user, 'channel.manage', $team);
    }

    public function hasReachedLimit(?User $user, string $capabilityKey): bool
    {
        $remaining = $this->remainingSlots($user, $capabilityKey);

        return $remaining !== null && $remaining <= 0;
    }

    public function remainingSlots(?User $user, string $capabilityKey): ?int
    {
        $planOwner = $this->planOwner($user);
        $limit = (int) ($planOwner?->planLimit('max_channels', -1) ?? -1);

        if ($limit < 0 || ! $user) {
            return null;
        }

        $query = TeamWorkspaceAccess::accessibleAccountsQuery($user);
        $mode = (string) ($planOwner?->planLimit('channel_count_mode', 'entire_social_network') ?? 'entire_social_network');

        if ($mode === 'each_social_network') {
            $providerKey = (string) (channel_capability($capabilityKey)['provider_key'] ?? '');

            if ($providerKey !== '') {
                $query->where('provider_key', $providerKey);
            }
        }

        return max($limit - $query->count(), 0);
    }

    public function permissionKeyFromCapability(string $capabilityKey, array $capability = []): string
    {
        return match ($capabilityKey) {
            'facebook_page' => 'channel_facebook_pages',
            'instagram_profile' => 'channel_instagram_profiles',
            default => 'channel_'.str_replace('-', '_', $capabilityKey),
        };
    }

    protected function planOwner(?User $user): ?User
    {
        if (! $user) {
            return null;
        }

        $team = TeamWorkspaceAccess::activeTeam($user);

        return $team?->owner ?: $user;
    }
}
