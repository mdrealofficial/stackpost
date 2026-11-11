<?php

namespace Modules\AdminCache\Actions;

use Illuminate\Support\Facades\Artisan;
use Modules\AdminCache\Actions\Contracts\CacheAction;

class ClearApplicationCacheAction implements CacheAction
{
    public function key(): string
    {
        return 'app';
    }

    public function title(): string
    {
        return 'Application cache';
    }

    public function description(): string
    {
        return 'Clear all cached application data.';
    }

    public function icon(): string
    {
        return 'fa-database';
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
        return 'This action will remove the current application cache and force Laravel to rebuild it on the next request.';
    }

    public function confirmTitle(): string
    {
        return 'Are you absolutely sure?';
    }

    public function handle(): string
    {
        Artisan::call('cache:clear');

        return __('Application cache cleared successfully.');
    }
}
