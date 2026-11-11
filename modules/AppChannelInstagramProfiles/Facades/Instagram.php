<?php

namespace Modules\AppChannelInstagramProfiles\Facades;

use Illuminate\Support\Facades\Facade;

class Instagram extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Modules\AppChannelInstagramProfiles\Services\Instagram\InstagramApiService::class;
    }
}
