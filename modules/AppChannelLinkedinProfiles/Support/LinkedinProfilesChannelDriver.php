<?php

namespace Modules\AppChannelLinkedinProfiles\Support;

use Modules\AdminUser\Models\User;
use Modules\AppChannels\Support\Drivers\ManualChannelDriver;

class LinkedinProfilesChannelDriver extends ManualChannelDriver
{
    public static function key(): string
    {
        return 'linkedin-profiles-oauth';
    }

    public static function authorizeUrl(User $user, array $context = []): ?string
    {
        return route('portal.channels.linkedin.profiles.connect', [
            'reconnect' => (bool) ($context['reconnect'] ?? false),
            'account' => (int) ($context['account_id'] ?? 0),
        ]);
    }
}
