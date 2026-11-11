<?php

namespace Modules\AppUrlShorteners\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AppUrlShorteners\Facades\URLShortener;
use Modules\AppUrlShorteners\Support\UrlShortenerService;

class AppUrlShortenersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.appurlshorteners');
        $this->app->singleton(UrlShortenerService::class);

        if (! class_exists('URLShortener')) {
            class_alias(URLShortener::class, 'URLShortener');
        }
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'appurlshorteners');

        register_setting_item('general', [
            'label' => 'URL Shorteners',
            'description' => 'Configure Bitly, TinyURL, Rebrandly, or Short.io for modules that need shortened links.',
            'route_name' => 'settings.url-shorteners',
            'active_when' => ['settings.url-shorteners'],
            'order' => 100,
        ]);

        register_plan_permission([
            'key' => 'url_shorteners',
            'label' => __('URL Shorteners'),
            'type' => 'toggle',
            'default' => true,
            'order' => 105,
        ]);

        $this->app->booted(function (): void {
            \Pricing::addSubFeatures([
                'sort' => 170,
                'parent' => 'features',
                'tab_id' => 'ops',
                'tab_name' => __('Operations'),
                'key' => 'url_shorteners',
                'label' => __('URL Shortener'),
                'check' => true,
                'type' => 'boolean',
                'raw' => 0,
            ]);

            \Pricing::add([
                'sort' => 400,
                'key' => 'url_shorteners',
                'label' => __('URL Shortener'),
                'check' => true,
                'type' => 'boolean',
                'raw' => 0,
            ]);
        });
    }
}
