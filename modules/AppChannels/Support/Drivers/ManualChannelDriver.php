<?php

namespace Modules\AppChannels\Support\Drivers;

use Modules\AppChannels\Contracts\ChannelDriver;
use Modules\AdminUser\Models\User;

class ManualChannelDriver implements ChannelDriver
{
    public static function key(): string
    {
        return 'manual';
    }

    public static function authorizeUrl(User $user, array $context = []): ?string
    {
        return null;
    }
}
