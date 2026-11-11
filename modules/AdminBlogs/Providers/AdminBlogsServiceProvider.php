<?php

namespace Modules\AdminBlogs\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AdminBlogs\Console\Commands\ImportRssBlogsCommand;
use Modules\AdminBlogs\Models\Blog;
use Modules\AdminBlogs\Support\MultilingualContentGenerator;
use Modules\AdminBlogs\Support\RssContentImprover;
use Modules\AdminBlogs\Support\RssImportService;
use Modules\AdminCrons\Support\SystemCronRegistry;

class AdminBlogsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.adminblogs');
        $this->app->singleton(MultilingualContentGenerator::class);
        $this->app->singleton(RssContentImprover::class);
        $this->app->singleton(RssImportService::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'adminblogs');

        register_sidebar_item('main', [
            'label' => 'Blogs',
            'route_name' => 'admin-blogs.index',
            'active_when' => ['admin-blogs.*', 'admin-blog-categories.*', 'admin-blog-tags.*'],
            'icon' => 'blogs',
            'order' => 14,
            'children' => [
                [
                    'label' => 'Posts',
                    'route_name' => 'admin-blogs.index',
                    'active_when' => ['admin-blogs.*'],
                    'order' => 10,
                ],
                [
                    'label' => 'Categories',
                    'route_name' => 'admin-blog-categories.index',
                    'active_when' => ['admin-blog-categories.*'],
                    'order' => 20,
                ],
                [
                    'label' => 'Tags',
                    'route_name' => 'admin-blog-tags.index',
                    'active_when' => ['admin-blog-tags.*'],
                    'order' => 30,
                ],
                [
                    'label' => 'RSS Feeds',
                    'route_name' => 'admin-blogs.rss.index',
                    'active_when' => ['admin-blogs.rss.*'],
                    'order' => 40,
                ],
            ],
        ]);

        register_admin_dashboard_item('admin-blogs.snapshot', [
            'title' => 'Content',
            'view' => 'adminblogs::dashboard.snapshot',
            'width' => 'full',
            'order' => 58,
            'data' => fn () => [
                'metrics' => [
                    'total' => Blog::query()->count(),
                    'published' => Blog::query()->where('status', true)->count(),
                    'drafts' => Blog::query()->where('status', false)->count(),
                    'recent' => Blog::query()->where('created', '>=', now()->subDays(30)->timestamp)->count(),
                ],
                'route' => route('admin-blogs.index'),
            ],
        ]);

        $this->commands([
            ImportRssBlogsCommand::class,
        ]);

        $this->app->afterResolving(SystemCronRegistry::class, function (SystemCronRegistry $registry): void {
            $registry->register([
                'key' => 'blogs-rss-import',
                'name' => __('Blogs RSS Import'),
                'icon' => 'fa-light fa-rss',
                'description' => __('Import RSS blog sources that are ready for processing.'),
                'command' => 'blogs:rss-import',
                'recommended' => false,
                'cron_expression' => '* * * * *',
            ]);
        });
    }
}
