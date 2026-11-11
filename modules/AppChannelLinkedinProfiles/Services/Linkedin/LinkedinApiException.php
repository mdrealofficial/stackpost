<?php

namespace Modules\AppChannelLinkedinProfiles\Services\Linkedin;

use RuntimeException;

class LinkedinApiException extends RuntimeException
{
    public static function integrationNotReady(): self
    {
        return new self(__('LinkedIn integration is not configured yet. Add the App ID and App Secret first.'));
    }

    public static function tokenExchangeFailed(string $message = ''): self
    {
        return new self($message !== '' ? $message : __('LinkedIn could not exchange the authorization code for an access token.'));
    }

    public static function missingAccessToken(): self
    {
        return new self(__('LinkedIn did not return an access token.'));
    }

    public static function profilesLoadFailed(string $message = ''): self
    {
        return new self($message !== '' ? $message : __('LinkedIn could not load the requested profile or page data.'));
    }

    public static function publishingFailed(string $message = ''): self
    {
        return new self($message !== '' ? $message : __('LinkedIn could not publish the post.'));
    }
}
