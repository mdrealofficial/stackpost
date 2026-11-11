<?php

namespace Modules\AppChannelFacebookPages\Services\Facebook;

use RuntimeException;

class FacebookApiException extends RuntimeException
{
    public static function integrationNotReady(): self
    {
        return new self(__('Facebook API Integration is not ready yet.'));
    }

    public static function tokenExchangeFailed(): self
    {
        return new self(__('Facebook token exchange failed.'));
    }

    public static function missingAccessToken(): self
    {
        return new self(__('Facebook did not return an access token.'));
    }

    public static function pagesLoadFailed(): self
    {
        return new self(__('Facebook pages could not be loaded after authorization.'));
    }
}
