<?php

namespace Modules\AppChannelXProfiles\Support;

use Modules\AdminUser\Models\User;
use Modules\AppChannels\Support\Drivers\ManualChannelDriver;

class XProfilesChannelDriver extends ManualChannelDriver
{
    public static function key(): string
    {
        return 'x-profiles-oauth';
    }

    public static function authorizeUrl(User $user, array $context = []): ?string
    {
        return route('portal.channels.x.profiles.connect', [
            'reconnect' => (bool) ($context['reconnect'] ?? false),
            'account' => (int) ($context['account_id'] ?? 0),
        ]);
    }
}
