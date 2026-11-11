<?php

namespace Modules\AppChannelFacebookPages\Facades;

use Illuminate\Support\Facades\Facade;

class Facebook extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Modules\AppChannelFacebookPages\Services\Facebook\FacebookApiService::class;
    }
}
