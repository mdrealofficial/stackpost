<?php

namespace Modules\AdminSettings\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AdminSettings\Support\OptionStore;
use Modules\AdminSettings\Support\SettingsPageRegistry;

class AdminSettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OptionStore::class);
        $this->app->singleton(SettingsPageRegistry::class);

        $helper = __DIR__.'/../Support/helpers.php';

        if (is_file($helper)) {
            require_once $helper;
        }
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'adminsettings');

        register_setting_section('general', 'System', 10);

        register_setting_item('general', [
            'label' => 'General settings',
            'description' => 'Control shared application metadata, default formatting, and contact information.',
            'route_name' => 'settings.general',
            'active_when' => ['settings.general'],
            'order' => 10,
        ]);

        register_setting_item('general', [
            'label' => 'Google Analytics',
            'description' => 'Configure GA4 measurement ID and decide whether to track guest pages or the app area.',
            'route_name' => 'settings.analytics',
            'active_when' => ['settings.analytics'],
            'order' => 20,
        ]);

        register_setting_item('general', [
            'label' => 'Static pages',
            'description' => 'Manage public legal and information pages such as Privacy Policy and Terms of Use.',
            'route_name' => 'settings.static-pages',
            'active_when' => ['settings.static-pages'],
            'order' => 30,
        ]);

        register_sidebar_section('frontend', 'Settings', 40);

        register_sidebar_item('frontend', [
            'label' => 'Settings',
            'icon' => 'settings',
            'active_when' => ['settings.*', 'admin-cache.*'],
            'order' => 20,
            'children_resolver' => fn (): array => settings_navigation_items(),
        ]);

    }
}
