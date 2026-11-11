<?php

namespace Modules\AppChannelFacebookPages\Support;

use Modules\AdminUser\Models\User;
use Modules\AppChannels\Support\Drivers\ManualChannelDriver;

class FacebookPagesChannelDriver extends ManualChannelDriver
{
    public static function key(): string
    {
        return 'facebook-pages-oauth';
    }

    public static function authorizeUrl(User $user, array $context = []): ?string
    {
        return route('portal.channels.facebook.pages.connect');
    }
}
