<?php

namespace Modules\AdminThemes\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Modules\AdminThemes\Support\ThemeManager;
use Symfony\Component\HttpFoundation\Response;

class SetThemeContext
{
    public function __construct(
        protected ThemeManager $themeManager,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $this->themeManager->flushResolved();

        $currentTheme = $this->themeManager->resolveForRequest($request);

        foreach (array_keys((array) config('themes.areas', [])) as $area) {
            $theme = $this->themeManager->themeForArea($area);
            View::replaceNamespace("theme-{$area}", $theme->viewsPath());
        }

        $appTheme = $this->themeManager->themeForArea('app');

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

        View::share('currentTheme', $currentTheme);
        $request->attributes->set('currentTheme', $currentTheme);

        app()->instance(ThemeManager::class, $this->themeManager);
        app()->instance('currentTheme', $currentTheme);

        return $next($request);
    }
}
