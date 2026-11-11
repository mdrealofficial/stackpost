<?php

use App\Support\Navigation\SidebarRegistry;
use Modules\AdminSettings\Support\OptionStore;
use Illuminate\Foundation\Vite;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Modules\AdminLanguages\Support\LocaleManager;
use Modules\AdminThemes\Support\Theme;
use Modules\AdminThemes\Support\ThemeManager;
use Modules\AdminThemes\Support\ThemeSettings;

if (! function_exists('theme_manager')) {
    function theme_manager(): ThemeManager
    {
        return app(ThemeManager::class);
    }
}

if (! function_exists('current_theme')) {
    function current_theme(?string $area = null): Theme
    {
        return $area
            ? theme_manager()->themeForArea($area)
            : theme_manager()->currentTheme();
    }
}

if (! function_exists('theme_view')) {
    function theme_view(string $view, ?string $area = null): string
    {
        $area ??= theme_manager()->currentArea();

        return "theme-{$area}::{$view}";
    }
}

if (! function_exists('theme_asset')) {
    function theme_asset(string $path = '', ?string $area = null): string
    {
        $theme = current_theme($area);
        $relative = trim("resources/themes/{$theme->area}/{$theme->name}/".ltrim($path, '/'), '/');

        return asset($relative);
    }
}

if (! function_exists('theme_asset_for')) {
    function theme_asset_for(Theme $theme, string $path = ''): string
    {
        $relative = trim("resources/themes/{$theme->area}/{$theme->name}/".ltrim($path, '/'), '/');

        return asset($relative);
    }
}

if (! function_exists('theme_shared_asset')) {
    function theme_shared_asset(string $path = ''): string
    {
        $relative = trim('resources/themes/shared/'.ltrim($path, '/'), '/');

        return asset($relative);
    }
}

if (! function_exists('theme_vite')) {
    function theme_vite(?string $area = null, ?array $entryPoints = null): HtmlString
    {
        $theme = current_theme($area);
        $entryPoints ??= config('themes.vite.entry_points', ['assets/js/app.js']);
        $entryPoints = array_map(
            static function (string $entryPoint) use ($theme): string {
                if (Str::startsWith($entryPoint, ['resources/', '/'])) {
                    return ltrim($entryPoint, '/');
                }

                return trim("resources/themes/{$theme->area}/{$theme->name}/".ltrim($entryPoint, '/'), '/');
            },
            $entryPoints
        );
        $vite = app(Vite::class)
            ->useHotFile(config('themes.vite.hot_file', public_path('hot')));

        if (! app()->runningInConsole()) {
            $documentRoot = request()->server('DOCUMENT_ROOT');
            $publicPath = public_path();

            if (is_string($documentRoot) && $documentRoot !== '') {
                $resolvedDocumentRoot = realpath($documentRoot);
                $resolvedPublicPath = realpath($publicPath);

                if ($resolvedDocumentRoot && $resolvedPublicPath && $resolvedDocumentRoot !== $resolvedPublicPath) {
                    $baseUrl = trim((string) request()->getBaseUrl(), '/');
                    $prefix = $baseUrl === '' ? '/public/' : '/'.$baseUrl.'/public/';

                    $vite->createAssetPathsUsing(
                        static fn (string $path, ?bool $secure = null): string => $prefix.ltrim($path, '/')
                    );
                }
            }
        }

        try {
            return $vite->__invoke($entryPoints, $theme->buildDirectory());
        } catch (Throwable $exception) {
            return theme_browser_assets($theme, $exception->getMessage());
        }
    }
}

if (! function_exists('theme_browser_assets')) {
    function theme_browser_assets(Theme $theme, ?string $reason = null): HtmlString
    {
        $compiledRelativePath = 'build/frontend/'.$theme->area.'-'.$theme->name.'.css';
        $compiledPath = public_path($compiledRelativePath);

        if (is_file($compiledPath)) {
            $baseUrl = app()->runningInConsole() ? '' : rtrim((string) request()->getBaseUrl(), '/');
            $documentRoot = app()->runningInConsole() ? null : request()->server('DOCUMENT_ROOT');
            $usesPublicRoot = is_string($documentRoot) && realpath($documentRoot) === realpath(public_path());
            $assetUrl = $baseUrl.($usesPublicRoot ? '/' : '/public/').$compiledRelativePath;

            return new HtmlString('<link rel="stylesheet" href="'.e($assetUrl).'?v='.filemtime($compiledPath).'">');
        }

        $cssBlocks = array_filter([
            theme_browser_css(resource_path('themes/shared/css/theme-browser.css')),
            theme_browser_css($theme->assetsPath().'/css/browser.css'),
        ]);

        $styleTag = empty($cssBlocks)
            ? ''
            : '<style type="text/tailwindcss">'.implode("\n\n", $cssBlocks).'</style>';

        $comment = sprintf(
            '<!-- theme_vite fallback for %s%s -->',
            e($theme->id()),
            $reason ? ': '.e($reason) : ''
        );

        return new HtmlString(
            implode("\n", array_filter([
                $comment,
                '<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>',
                $styleTag,
            ]))
        );
    }
}

if (! function_exists('theme_browser_css')) {
    function theme_browser_css(string $path): ?string
    {
        if (! file_exists($path)) {
            return null;
        }

        $css = file_get_contents($path);

        if ($css === false) {
            return null;
        }

        $css = Str::replaceMatches('/^\s*@source[^\n]*\R?/m', '', $css);
        $css = Str::replaceMatches('/^\s*@import\s+["\']tailwindcss["\'];?\s*\R?/m', '', $css);

        return trim($css);
    }
}

if (! function_exists('theme_navigation_modes')) {
    function theme_navigation_modes(?string $area = 'app'): array
    {
        $area ??= theme_manager()->currentArea();
        $theme = current_theme($area);
        $modes = $theme->meta['navigation_modes'] ?? ['sidebar'];

        if (! is_array($modes) || $modes === []) {
            return ['sidebar'];
        }

        return array_values(array_unique(array_filter(
            array_map(fn ($value) => is_string($value) ? trim($value) : null, $modes)
        ))) ?: ['sidebar'];
    }
}

if (! function_exists('theme_settings')) {
    function theme_settings(?string $area = null): array
    {
        return app(ThemeSettings::class)->values(current_theme($area));
    }
}

if (! function_exists('theme_setting')) {
    function theme_setting(string $key, ?string $area = null, mixed $default = null): mixed
    {
        return app(ThemeSettings::class)->get(current_theme($area), $key, $default);
    }
}

if (! function_exists('theme_font_stack')) {
    function theme_font_stack(?string $area = null): string
    {
        return app(ThemeSettings::class)->fontStack(current_theme($area));
    }
}

if (! function_exists('theme_accent_rgb')) {
    function theme_accent_rgb(?string $area = null): string
    {
        return app(ThemeSettings::class)->accentRgb(current_theme($area));
    }
}

if (! function_exists('theme_color_rgb')) {
    function theme_color_rgb(string $key, ?string $area = null, string $fallback = '#4f46e5'): string
    {
        return app(ThemeSettings::class)->colorRgb(current_theme($area), $key, $fallback);
    }
}

if (! function_exists('theme_navigation_mode')) {
    function theme_navigation_mode(?string $area = 'app'): string
    {
        $area ??= theme_manager()->currentArea();
        $optionKey = "{$area}_navigation";
        $modes = theme_navigation_modes($area);
        $selected = app(OptionStore::class)->get($optionKey, $modes[0] ?? 'sidebar');

        return in_array($selected, $modes, true)
            ? $selected
            : ($modes[0] ?? 'sidebar');
    }
}
