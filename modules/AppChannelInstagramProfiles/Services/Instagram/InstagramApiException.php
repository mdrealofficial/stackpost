<?php

namespace Modules\AppChannelInstagramProfiles\Services\Instagram;

use RuntimeException;

class InstagramApiException extends RuntimeException
{
    public static function integrationNotReady(): self
    {
        return new self(__('Instagram API Integration is not ready yet.'));
    }

    public static function tokenExchangeFailed(): self
    {
        return new self(__('Instagram authorization could not be completed.'));
    }

    public static function missingAccessToken(): self
    {
        return new self(__('Instagram did not return an access token.'));
    }

    public static function profilesLoadFailed(): self
    {
        return new self(__('Instagram business or creator profiles could not be loaded after authorization.'));
    }
}
