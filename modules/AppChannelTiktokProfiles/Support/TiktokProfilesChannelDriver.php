<?php

namespace Modules\AppChannelTiktokProfiles\Support;

use Modules\AdminUser\Models\User;
use Modules\AppChannels\Support\Drivers\ManualChannelDriver;

class TiktokProfilesChannelDriver extends ManualChannelDriver
{
    public static function key(): string
    {
        return 'tiktok-profiles-oauth';
    }

    public static function authorizeUrl(User $user, array $context = []): ?string
    {
        return route('portal.channels.tiktok.profiles.connect', [
            'reconnect' => (bool) ($context['reconnect'] ?? false),
            'account' => (int) ($context['account_id'] ?? 0),
        ]);
    }
}
