<?php

namespace Modules\AppChannelLinkedinPages\Support;

use Modules\AdminUser\Models\User;
use Modules\AppChannels\Support\Drivers\ManualChannelDriver;

class LinkedinPagesChannelDriver extends ManualChannelDriver
{
    public static function key(): string
    {
        return 'linkedin-pages-oauth';
    }

    public static function authorizeUrl(User $user, array $context = []): ?string
    {
        return route('portal.channels.linkedin.pages.connect', [
            'reconnect' => (bool) ($context['reconnect'] ?? false),
            'account' => (int) ($context['account_id'] ?? 0),
        ]);
    }
}
