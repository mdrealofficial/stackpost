<?php

namespace Modules\AppChannelTiktokProfiles\Services\Tiktok;

use RuntimeException;

class TiktokApiException extends RuntimeException
{
    public static function integrationNotReady(): self
    {
        return new self(__('TikTok integration is not configured yet. Add the Client Key and Client Secret first.'));
    }

    public static function tokenExchangeFailed(string $message = ''): self
    {
        return new self($message !== '' ? $message : __('TikTok could not exchange the authorization code for an access token.'));
    }

    public static function missingAccessToken(): self
    {
        return new self(__('TikTok did not return an access token.'));
    }

    public static function profileLoadFailed(string $message = ''): self
    {
        return new self($message !== '' ? $message : __('TikTok could not load the connected profile.'));
    }

    public static function creatorInfoFailed(string $message = ''): self
    {
        return new self($message !== '' ? $message : __('TikTok could not load the latest creator settings.'));
    }

    public static function publishFailed(string $message = ''): self
    {
        return new self($message !== '' ? $message : __('TikTok could not publish this post.'));
    }
}
