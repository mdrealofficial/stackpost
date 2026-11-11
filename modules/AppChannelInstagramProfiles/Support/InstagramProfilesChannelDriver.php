<?php

namespace Modules\AppChannelInstagramProfiles\Support;

use Modules\AdminUser\Models\User;
use Modules\AppChannels\Support\Drivers\ManualChannelDriver;

class InstagramProfilesChannelDriver extends ManualChannelDriver
{
    public static function key(): string
    {
        return 'instagram-profiles-oauth';
    }

    public static function authorizeUrl(User $user, array $context = []): ?string
    {
        return route('portal.channels.instagram.profiles.connect');
    }
}
