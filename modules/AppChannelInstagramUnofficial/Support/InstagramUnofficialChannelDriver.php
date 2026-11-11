<?php

namespace Modules\AppChannelInstagramUnofficial\Support;

use Modules\AdminUser\Models\User;
use Modules\AppChannels\Support\Drivers\ManualChannelDriver;

class InstagramUnofficialChannelDriver extends ManualChannelDriver
{
    public static function key(): string
    {
        return 'instagram-unofficial-manual';
    }

    public static function authorizeUrl(User $user, array $context = []): ?string
    {
        return route('portal.channels.instagram-unofficial.connect', [
            'reconnect' => (bool) ($context['reconnect'] ?? false),
            'account' => (int) ($context['account_id'] ?? 0),
        ]);
    }
}
