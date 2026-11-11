<?php

namespace Modules\AppChannels\Contracts;

use Modules\AdminUser\Models\User;

interface ChannelDriver
{
    public static function key(): string;

    public static function authorizeUrl(User $user, array $context = []): ?string;
}
