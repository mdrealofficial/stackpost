<?php

namespace Modules\AdminCache\Actions;

use Illuminate\Support\Facades\Artisan;
use Modules\AdminCache\Actions\Contracts\CacheAction;

class ClearViewCacheAction implements CacheAction
{
    public function key(): string
    {
        return 'view';
    }

    public function title(): string
    {
        return 'View cache';
    }

    public function description(): string
    {
        return 'Remove compiled Blade view files.';
    }

    public function icon(): string
    {
        return 'fa-eye';
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
        return 'This action will remove compiled Blade view files and regenerate them when pages are rendered again.';
    }

    public function confirmTitle(): string
    {
        return 'Are you absolutely sure?';
    }

    public function handle(): string
    {
        Artisan::call('view:clear');

        return __('View cache cleared successfully.');
    }
}
