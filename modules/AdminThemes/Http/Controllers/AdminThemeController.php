<?php

namespace Modules\AdminThemes\Http\Controllers;

use Modules\AdminSettings\Support\OptionStore;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\AdminThemes\Support\ThemeRegistry;
use Modules\AdminThemes\Support\ThemeSettings;

class AdminThemeController extends Controller
{
    public function __construct(
        protected ThemeRegistry $themes,
        protected OptionStore $options,
        protected ThemeSettings $themeSettings,
    ) {}

    public function frontend(): View
    {
        return view('adminthemes::frontend', $this->themeState());
    }

    public function backend(): View
    {
        return view('adminthemes::backend', $this->themeState());
    }

    public function exportFrontend(): JsonResponse
    {
        $theme = $this->themes->find('guest', (string) $this->options->get(
            config('themes.areas.guest.option_key', 'frontend_theme'),
            config('themes.areas.guest.fallback', 'default'),
        )) ?? $this->themes->first('guest');

        abort_if(! $theme, 404);

        $payload = [
            'area' => 'guest',
            'theme' => $theme->name,
            'settings' => $this->themeSettings->values($theme),
            'exported_at' => now()->toIso8601String(),
        ];

        return response()
            ->json($payload, 200, [
                'Content-Disposition' => 'attachment; filename="frontend-theme-'.$theme->name.'.json"',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function exportBackend(): JsonResponse
    {
        $theme = $this->themes->find('app', (string) $this->options->get(
            config('themes.areas.app.option_key', 'backend_theme'),
            config('themes.areas.app.fallback', 'default'),
        )) ?? $this->themes->first('app');

        abort_if(! $theme, 404);

        $navigationModes = is_array($theme->meta['navigation_modes'] ?? null)
            ? ($theme->meta['navigation_modes'] ?? ['sidebar'])
            : ['sidebar'];

        $payload = [
            'area' => 'app',
            'theme' => $theme->name,
            'settings' => $this->themeSettings->values($theme),
            'navigation' => (string) $this->options->get('app_navigation', $navigationModes[0] ?? 'sidebar'),
            'exported_at' => now()->toIso8601String(),
        ];

        return response()
            ->json($payload, 200, [
                'Content-Disposition' => 'attachment; filename="backend-theme-'.$theme->name.'.json"',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function updateFrontend(Request $request): RedirectResponse
    {
        $tab = $request->input('tab', 'select-theme');

        if ($request->input('intent') === 'import_settings') {
            return $this->importFrontend($request, $tab);
        }

        $frontendThemeName = (string) $request->input('frontend_theme');
        $selectedFrontendTheme = $this->themes->find('guest', $frontendThemeName);
        $frontendThemeSchema = $selectedFrontendTheme ? $this->themeSettings->schema($selectedFrontendTheme) : [];

        $validated = $request->validate([
            'frontend_theme' => [
                'required',
                'string',
                Rule::in(array_keys($this->themes->forArea('guest'))),
            ],
            'frontend_settings.custom_css' => isset($frontendThemeSchema['custom_css'])
                ? ['nullable', 'string', 'max:20000']
                : ['nullable'],
            'frontend_settings.custom_js' => isset($frontendThemeSchema['custom_js'])
                ? ['nullable', 'string', 'max:10000']
                : ['nullable'],
        ]);

        $this->options->set(config('themes.areas.guest.option_key', 'frontend_theme'), $validated['frontend_theme']);

        if ($frontendTheme = $this->themes->find('guest', $validated['frontend_theme'])) {
            if ($request->input('intent') === 'reset_defaults') {
                $this->themeSettings->set($frontendTheme, $this->themeSettings->defaults($frontendTheme));

                Artisan::call('view:clear');

                return redirect()->route('admin-themes.frontend', ['tab' => $tab])->with('status', __('Frontend theme defaults restored successfully.'));
            }

            $this->themeSettings->set($frontendTheme, (array) $request->input('frontend_settings', []));
        }

        Artisan::call('view:clear');

        return redirect()->route('admin-themes.frontend', ['tab' => $tab])->with('status', __('Frontend theme updated successfully.'));
    }

    public function updateBackend(Request $request): RedirectResponse
    {
        $tab = $request->input('tab', 'select-theme');

        if ($request->input('intent') === 'import_settings') {
            return $this->importBackend($request, $tab);
        }

        $backendThemeName = (string) $request->input('backend_theme');
        $selectedBackendTheme = $this->themes->find('app', $backendThemeName);
        $backendNavigationModes = is_array($selectedBackendTheme?->meta['navigation_modes'] ?? null)
            ? ($selectedBackendTheme->meta['navigation_modes'] ?? ['sidebar'])
            : ['sidebar'];
        $backendThemeSchema = $selectedBackendTheme ? $this->themeSettings->schema($selectedBackendTheme) : [];

        $validated = $request->validate([
            'backend_theme' => [
                'required',
                'string',
                Rule::in(array_keys($this->themes->forArea('app'))),
            ],
            'backend_navigation' => [
                'nullable',
                'string',
                Rule::in($backendNavigationModes),
            ],
            'backend_settings.custom_css' => isset($backendThemeSchema['custom_css'])
                ? ['nullable', 'string', 'max:20000']
                : ['nullable'],
            'backend_settings.custom_js' => isset($backendThemeSchema['custom_js'])
                ? ['nullable', 'string', 'max:10000']
                : ['nullable'],
        ]);

        $this->options->set(config('themes.areas.app.option_key', 'backend_theme'), $validated['backend_theme']);
        $this->options->set('app_navigation', $validated['backend_navigation'] ?? ($backendNavigationModes[0] ?? 'sidebar'));

        if ($backendTheme = $this->themes->find('app', $validated['backend_theme'])) {
            if ($request->input('intent') === 'reset_defaults') {
                $this->themeSettings->set($backendTheme, $this->themeSettings->defaults($backendTheme));
                $this->options->set('app_navigation', $backendNavigationModes[0] ?? 'sidebar');

                Artisan::call('view:clear');

                return redirect()->route('admin-themes.backend', ['tab' => $tab])->with('status', __('Backend theme defaults restored successfully.'));
            }

            $this->themeSettings->set($backendTheme, (array) $request->input('backend_settings', []));
        }

        Artisan::call('view:clear');

        return redirect()->route('admin-themes.backend', ['tab' => $tab])->with('status', __('Backend theme updated successfully.'));
    }

    protected function themeState(): array
    {
        $frontendTheme = (string) $this->options->get(
            config('themes.areas.guest.option_key', 'frontend_theme'),
            config('themes.areas.guest.fallback', 'default'),
        );
        $backendTheme = (string) $this->options->get(
            config('themes.areas.app.option_key', 'backend_theme'),
            config('themes.areas.app.fallback', 'default'),
        );
        $selectedFrontendTheme = $this->themes->find('guest', $frontendTheme) ?? $this->themes->first('guest');
        $selectedBackendTheme = $this->themes->find('app', $backendTheme) ?? $this->themes->first('app');
        $navigationModes = $selectedBackendTheme->meta['navigation_modes'] ?? ['sidebar'];
        $guestThemes = array_values($this->themes->forArea('guest'));
        $appThemes = array_values($this->themes->forArea('app'));

        return [
            'guestThemes' => $guestThemes,
            'appThemes' => $appThemes,
            'frontendTheme' => $frontendTheme,
            'backendTheme' => $backendTheme,
            'selectedFrontendTheme' => $selectedFrontendTheme,
            'selectedBackendTheme' => $selectedBackendTheme,
            'backendNavigation' => (string) $this->options->get('app_navigation', is_array($navigationModes) ? ($navigationModes[0] ?? 'sidebar') : 'sidebar'),
            'backendNavigationModes' => is_array($navigationModes) ? $navigationModes : ['sidebar'],
            'frontendThemeSchema' => $selectedFrontendTheme ? $this->themeSettings->schema($selectedFrontendTheme) : [],
            'frontendThemeValues' => $selectedFrontendTheme ? $this->themeSettings->values($selectedFrontendTheme) : [],
            'backendThemeSchema' => $selectedBackendTheme ? $this->themeSettings->schema($selectedBackendTheme) : [],
            'backendThemeValues' => $selectedBackendTheme ? $this->themeSettings->values($selectedBackendTheme) : [],
            'guestThemeDefaults' => collect($guestThemes)->mapWithKeys(fn ($theme) => [$theme->name => $this->themeSettings->defaults($theme)])->all(),
            'appThemeDefaults' => collect($appThemes)->mapWithKeys(fn ($theme) => [$theme->name => $this->themeSettings->defaults($theme)])->all(),
            'appThemeDefaultNavigation' => collect($appThemes)->mapWithKeys(function ($theme) {
                $modes = is_array($theme->meta['navigation_modes'] ?? null)
                    ? ($theme->meta['navigation_modes'] ?? ['sidebar'])
                    : ['sidebar'];

                return [$theme->name => (string) ($modes[0] ?? 'sidebar')];
            })->all(),
        ];
    }

    protected function importFrontend(Request $request, string $tab): RedirectResponse
    {
        $payload = $this->decodeImportPayload((string) $request->input('frontend_import_json'), 'frontend_import_json');

        if (($payload['area'] ?? 'guest') !== 'guest') {
            throw ValidationException::withMessages([
                'frontend_import_json' => __('This JSON file is not a frontend theme export.'),
            ]);
        }

        $themeName = (string) ($payload['theme'] ?? $request->input('frontend_theme', ''));
        $theme = $this->themes->find('guest', $themeName);

        if (! $theme) {
            throw ValidationException::withMessages([
                'frontend_import_json' => __('The imported frontend theme does not exist on this installation.'),
            ]);
        }

        $this->options->set(config('themes.areas.guest.option_key', 'frontend_theme'), $theme->name);
        $this->themeSettings->set($theme, is_array($payload['settings'] ?? null) ? $payload['settings'] : []);

        Artisan::call('view:clear');

        return redirect()->route('admin-themes.frontend', ['tab' => $tab])->with('status', __('Frontend theme imported successfully.'));
    }

    protected function importBackend(Request $request, string $tab): RedirectResponse
    {
        $payload = $this->decodeImportPayload((string) $request->input('backend_import_json'), 'backend_import_json');

        if (($payload['area'] ?? 'app') !== 'app') {
            throw ValidationException::withMessages([
                'backend_import_json' => __('This JSON file is not a backend theme export.'),
            ]);
        }

        $themeName = (string) ($payload['theme'] ?? $request->input('backend_theme', ''));
        $theme = $this->themes->find('app', $themeName);

        if (! $theme) {
            throw ValidationException::withMessages([
                'backend_import_json' => __('The imported backend theme does not exist on this installation.'),
            ]);
        }

        $navigationModes = is_array($theme->meta['navigation_modes'] ?? null)
            ? ($theme->meta['navigation_modes'] ?? ['sidebar'])
            : ['sidebar'];
        $navigation = (string) ($payload['navigation'] ?? ($navigationModes[0] ?? 'sidebar'));

        $this->options->set(config('themes.areas.app.option_key', 'backend_theme'), $theme->name);
        $this->options->set('app_navigation', in_array($navigation, $navigationModes, true) ? $navigation : ($navigationModes[0] ?? 'sidebar'));
        $this->themeSettings->set($theme, is_array($payload['settings'] ?? null) ? $payload['settings'] : []);

        Artisan::call('view:clear');

        return redirect()->route('admin-themes.backend', ['tab' => $tab])->with('status', __('Backend theme imported successfully.'));
    }

    protected function decodeImportPayload(string $json, string $field): array
    {
        $json = trim($json);

        if ($json === '') {
            throw ValidationException::withMessages([
                $field => __('Paste a theme JSON payload before importing.'),
            ]);
        }

        $payload = json_decode($json, true);

        if (! is_array($payload)) {
            throw ValidationException::withMessages([
                $field => __('The imported JSON is invalid.'),
            ]);
        }

        if (! is_array($payload['settings'] ?? null)) {
            throw ValidationException::withMessages([
                $field => __('The imported JSON must contain a settings object.'),
            ]);
        }

        return $payload;
    }
}
