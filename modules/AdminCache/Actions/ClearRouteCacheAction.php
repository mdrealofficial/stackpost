<?php

namespace Modules\AdminCache\Actions;

use Illuminate\Support\Facades\Artisan;
use Modules\AdminCache\Actions\Contracts\CacheAction;

class ClearRouteCacheAction implements CacheAction
{
    public function key(): string
    {
        return 'route';
    }

    public function title(): string
    {
        return 'Route cache';
    }

    public function description(): string
    {
        return 'Clear cached routes and rebuild routing.';
    }

    public function icon(): string
    {
        return 'fa-route';
    }

    public function buttonLabel(): string
    {
        return 'Clear';
    }

    public function buttonVariant(): string
    {
        return 'outline';
    }

    public function confirmMessage(): string
    {
        return 'This action will clear the cached route manifest and force the router to rebuild its definitions.';
    }

    public function confirmTitle(): string
    {
        return 'Are you absolutely sure?';
    }

    public function handle(): string
    {
        Artisan::call('route:clear');

        return __('Route cache cleared successfully.');
    }
}
