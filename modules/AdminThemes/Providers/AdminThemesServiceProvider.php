<?php

namespace Modules\AdminThemes\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Modules\AdminThemes\Console\Commands\MakeThemeCommand;
use Modules\AdminThemes\Console\Commands\ThemeBuildCommand;
use Modules\AdminThemes\Console\Commands\ThemeDevCommand;
use Modules\AdminThemes\Support\ThemeAreaResolver;
use Modules\AdminThemes\Support\ThemeManager;
use Modules\AdminThemes\Support\ThemeRegistry;
use Modules\AdminThemes\Support\ThemeSettings;

class AdminThemesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.adminthemes');
        require_once __DIR__.'/../Support/helpers.php';

        $this->app->singleton(ThemeRegistry::class);
        $this->app->singleton(ThemeAreaResolver::class);
        $this->app->singleton(ThemeManager::class);
        $this->app->singleton(ThemeSettings::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'adminthemes');
        $this->registerThemeBladePaths();

        register_sidebar_section('frontend', 'Settings', 40);

        register_sidebar_item('frontend', [
            'label' => 'Themes',
            'icon' => 'themes',
            'active_when' => ['admin-themes.*'],
            'order' => 10,
            'children' => [
                [
                    'label' => 'Frontend',
                    'route_name' => 'admin-themes.frontend',
                    'active_when' => ['admin-themes.frontend', 'admin-themes.frontend.update'],
                    'order' => 10,
                ],
                [
                    'label' => 'Backend',
                    'route_name' => 'admin-themes.backend',
                    'active_when' => ['admin-themes.backend', 'admin-themes.backend.update'],
                    'order' => 20,
                ],
            ],
        ]);

        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeThemeCommand::class,
                ThemeBuildCommand::class,
                ThemeDevCommand::class,
            ]);
        }
    }

    protected function registerThemeBladePaths(): void
    {
        $themeManager = $this->app->make(ThemeManager::class);

        foreach (array_keys((array) config('themes.areas', [])) as $area) {
            $theme = $themeManager->themeForArea($area);
            View::replaceNamespace("theme-{$area}", $theme->viewsPath());
        }

        $appTheme = $themeManager->themeForArea('app');
        $componentPath = $appTheme->viewsPath().'/components';
        $layoutPath = $appTheme->viewsPath().'/layouts';
        $layoutComponentPath = $componentPath.'/layout';
        $themeComponentPath = $componentPath.'/theme';
        $aiComponentPath = $componentPath.'/ai';

        if (File::isDirectory($componentPath)) {
            Blade::anonymousComponentPath($componentPath);
        }

        if (File::isDirectory($layoutComponentPath)) {
            Blade::anonymousComponentPath($layoutComponentPath, 'layout');
        }

        if (File::isDirectory($themeComponentPath)) {
            Blade::anonymousComponentPath($themeComponentPath, 'theme');
        }

        if (File::isDirectory($aiComponentPath)) {
            Blade::anonymousComponentPath($aiComponentPath, 'ai');
        }

        if (File::isDirectory($layoutPath)) {
            Blade::anonymousComponentPath($layoutPath, 'layouts');
        }
    }
}
