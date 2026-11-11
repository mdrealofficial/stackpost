<?php

namespace Modules\AdminLanguages\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AdminLanguages\Support\DatabaseTranslationLoader;
use Modules\AdminLanguages\Support\GoogleTranslationService;
use Modules\AdminLanguages\Support\LanguageMaintenanceService;
use Modules\AdminLanguages\Support\LocaleManager;
use Modules\AdminLanguages\Support\TranslationCatalog;
use Modules\AdminLanguages\Support\TranslationScanner;

class AdminLanguagesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.adminlanguages');
        require_once __DIR__.'/../Support/helpers.php';

        $this->app->singleton(TranslationCatalog::class);
        $this->app->singleton(TranslationScanner::class);
        $this->app->singleton(GoogleTranslationService::class);
        $this->app->singleton(LanguageMaintenanceService::class);
        $this->app->singleton(LocaleManager::class);

        $this->app->extend('translation.loader', function ($loader, $app) {
            return new DatabaseTranslationLoader(
                $loader,
                $app->make(TranslationCatalog::class),
            );
        });
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'adminlanguages');

        register_sidebar_section('localization', 'Localization', 50);

        register_sidebar_item('localization', [
            'label' => 'Admin Languages',
            'route_name' => 'admin-languages.index',
            'active_when' => ['admin-languages.*'],
            'icon' => 'ai-template',
            'order' => 10,
        ]);
    }
}
