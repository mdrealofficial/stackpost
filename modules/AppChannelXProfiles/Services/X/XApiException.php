<?php

namespace Modules\AppChannelXProfiles\Services\X;

use RuntimeException;

class XApiException extends RuntimeException
{
    public static function integrationNotReady(): self
    {
        return new self('X is not fully configured. Add the Client ID and Client Secret in API Integration first.');
    }

    public static function tokenExchangeFailed(string $message = ''): self
    {
        return new self($message !== '' ? $message : 'X did not return a valid access token.');
    }

    public static function profileLoadFailed(string $message = ''): self
    {
        return new self($message !== '' ? $message : 'X did not return the authenticated profile.');
    }

    public static function publishingFailed(string $message = ''): self
    {
        return new self($message !== '' ? $message : 'Could not create the tweet.');
    }

    public static function mediaUploadFailed(string $message = ''): self
    {
        return new self($message !== '' ? $message : 'Could not upload media to X.');
    }
}
