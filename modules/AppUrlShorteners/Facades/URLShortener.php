<?php

namespace Modules\AppUrlShorteners\Facades;

use Illuminate\Support\Facades\Facade;
use Modules\AppUrlShorteners\Support\UrlShortenerService;

class URLShortener extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return UrlShortenerService::class;
    }
}
