<?php

namespace Modules\AdminThemes\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\AdminSettings\Support\OptionStore;
use Modules\AdminThemes\Support\ThemeRegistry;
use Modules\AdminThemes\Support\ThemeSettings;

#[Title('Backend Themes')]
class BackendThemePage extends Component
{
    protected ThemeRegistry $themes;

    protected OptionStore $options;

    protected ThemeSettings $themeSettings;

    public function boot(ThemeRegistry $themes, OptionStore $options, ThemeSettings $themeSettings): void
    {
        $this->themes = $themes;
        $this->options = $options;
        $this->themeSettings = $themeSettings;
    }

    public function render(): View
    {
        return view('adminthemes::livewire.backend', $this->themeState())->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Backend Themes'),
        ]);
    }

    public function submit(string $payload): void
    {
        parse_str($payload, $input);

        $tab = (string) ($input['tab'] ?? 'select-theme');

        if (($input['intent'] ?? null) === 'import_settings') {
            $this->importBackend($input, $tab);

            return;
        }

        $backendThemeName = (string) ($input['backend_theme'] ?? '');
        $selectedBackendTheme = $this->themes->find('app', $backendThemeName);
        $backendNavigationModes = is_array($selectedBackendTheme?->meta['navigation_modes'] ?? null)
            ? ($selectedBackendTheme->meta['navigation_modes'] ?? ['sidebar'])
            : ['sidebar'];
        $backendThemeSchema = $selectedBackendTheme ? $this->themeSettings->schema($selectedBackendTheme) : [];

        $validated = Validator::make($input, [
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
        ])->validate();

        $this->options->set(config('themes.areas.app.option_key', 'backend_theme'), $validated['backend_theme']);
        $this->options->set('app_navigation', $validated['backend_navigation'] ?? ($backendNavigationModes[0] ?? 'sidebar'));

        if ($backendTheme = $this->themes->find('app', $validated['backend_theme'])) {
            if (($input['intent'] ?? null) === 'reset_defaults') {
                $this->themeSettings->set($backendTheme, $this->themeSettings->defaults($backendTheme));
                $this->options->set('app_navigation', $backendNavigationModes[0] ?? 'sidebar');
                Artisan::call('view:clear');
                session()->flash('status', __('Backend theme defaults restored successfully.'));
                $this->redirectRoute('admin-themes.backend', ['tab' => $tab], navigate: true);

                return;
            }

            $this->themeSettings->set($backendTheme, (array) ($input['backend_settings'] ?? []));
        }

        Artisan::call('view:clear');
        session()->flash('status', __('Backend theme updated successfully.'));
        $this->redirectRoute('admin-themes.backend', ['tab' => $tab], navigate: true);
    }

    protected function themeState(): array
    {
        $frontendTheme = (string) $this->options->get(config('themes.areas.guest.option_key', 'frontend_theme'), config('themes.areas.guest.fallback', 'default'));
        $backendTheme = (string) $this->options->get(config('themes.areas.app.option_key', 'backend_theme'), config('themes.areas.app.fallback', 'default'));
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
                $modes = is_array($theme->meta['navigation_modes'] ?? null) ? ($theme->meta['navigation_modes'] ?? ['sidebar']) : ['sidebar'];
                return [$theme->name => (string) ($modes[0] ?? 'sidebar')];
            })->all(),
            'themeSummary' => [
                'library' => count($appThemes),
                'editor_fields' => count($selectedBackendTheme ? $this->themeSettings->schema($selectedBackendTheme) : []),
                'navigation_modes' => count(is_array($navigationModes) ? $navigationModes : ['sidebar']),
                'guest_themes' => count($guestThemes),
            ],
        ];
    }

    protected function importBackend(array $input, string $tab): void
    {
        $payload = $this->decodeImportPayload((string) ($input['backend_import_json'] ?? ''), 'backend_import_json');

        if (($payload['area'] ?? 'app') !== 'app') {
            throw ValidationException::withMessages([
                'backend_import_json' => __('This JSON file is not a backend theme export.'),
            ]);
        }

        $themeName = (string) ($payload['theme'] ?? ($input['backend_theme'] ?? ''));
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

        session()->flash('status', __('Backend theme imported successfully.'));
        $this->redirectRoute('admin-themes.backend', ['tab' => $tab], navigate: true);
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
