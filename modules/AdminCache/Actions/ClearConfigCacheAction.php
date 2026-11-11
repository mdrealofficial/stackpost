<?php

namespace Modules\AdminCache\Actions;

use Illuminate\Support\Facades\Artisan;
use Modules\AdminCache\Actions\Contracts\CacheAction;

class ClearConfigCacheAction implements CacheAction
{
    public function key(): string
    {
        return 'config';
    }

    public function title(): string
    {
        return 'Config cache';
    }

    public function description(): string
    {
        return 'Rebuild or clear cached configuration files.';
    }

    public function icon(): string
    {
        return 'fa-gear-complex-code';
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
        return 'This action will clear the cached configuration snapshot and reload configuration values on the next request.';
    }

    public function confirmTitle(): string
    {
        return 'Are you absolutely sure?';
    }

    public function handle(): string
    {
        Artisan::call('config:clear');

        return __('Config cache cleared successfully.');
    }
}
