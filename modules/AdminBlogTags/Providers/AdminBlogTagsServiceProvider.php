<?php

namespace Modules\AdminBlogTags\Providers;

use Illuminate\Support\ServiceProvider;

class AdminBlogTagsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.adminblogtags');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'adminblogtags');
    }
}
