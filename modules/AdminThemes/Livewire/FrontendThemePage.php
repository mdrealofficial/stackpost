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

#[Title('Frontend Themes')]
class FrontendThemePage extends Component
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
        return view('adminthemes::livewire.frontend', $this->themeState())->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Frontend Themes'),
        ]);
    }

    public function submit(string $payload): void
    {
        parse_str($payload, $input);

        $tab = (string) ($input['tab'] ?? 'select-theme');

        if (($input['intent'] ?? null) === 'import_settings') {
            $this->importFrontend($input, $tab);

            return;
        }

        $frontendThemeName = (string) ($input['frontend_theme'] ?? '');
        $selectedFrontendTheme = $this->themes->find('guest', $frontendThemeName);
        $frontendThemeSchema = $selectedFrontendTheme ? $this->themeSettings->schema($selectedFrontendTheme) : [];

        $validated = Validator::make($input, [
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
        ])->validate();

        $this->options->set(config('themes.areas.guest.option_key', 'frontend_theme'), $validated['frontend_theme']);

        if ($frontendTheme = $this->themes->find('guest', $validated['frontend_theme'])) {
            if (($input['intent'] ?? null) === 'reset_defaults') {
                $this->themeSettings->set($frontendTheme, $this->themeSettings->defaults($frontendTheme));
                Artisan::call('view:clear');
                session()->flash('status', __('Frontend theme defaults restored successfully.'));
                $this->redirectRoute('admin-themes.frontend', ['tab' => $tab], navigate: true);

                return;
            }

            $this->themeSettings->set($frontendTheme, (array) ($input['frontend_settings'] ?? []));
        }

        Artisan::call('view:clear');
        session()->flash('status', __('Frontend theme updated successfully.'));
        $this->redirectRoute('admin-themes.frontend', ['tab' => $tab], navigate: true);
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
                'library' => count($guestThemes),
                'editor_fields' => count($selectedFrontendTheme ? $this->themeSettings->schema($selectedFrontendTheme) : []),
                'backend_themes' => count($appThemes),
                'supports' => count(array_filter([
                    isset(($selectedFrontendTheme?->meta ?? [])['supports']) && is_array(($selectedFrontendTheme?->meta ?? [])['supports'])
                        ? count(($selectedFrontendTheme?->meta ?? [])['supports'])
                        : 0,
                ])) ? count(($selectedFrontendTheme?->meta ?? [])['supports'] ?? []) : 0,
            ],
        ];
    }

    protected function importFrontend(array $input, string $tab): void
    {
        $payload = $this->decodeImportPayload((string) ($input['frontend_import_json'] ?? ''), 'frontend_import_json');

        if (($payload['area'] ?? 'guest') !== 'guest') {
            throw ValidationException::withMessages([
                'frontend_import_json' => __('This JSON file is not a frontend theme export.'),
            ]);
        }

        $themeName = (string) ($payload['theme'] ?? ($input['frontend_theme'] ?? ''));
        $theme = $this->themes->find('guest', $themeName);

        if (! $theme) {
            throw ValidationException::withMessages([
                'frontend_import_json' => __('The imported frontend theme does not exist on this installation.'),
            ]);
        }

        $this->options->set(config('themes.areas.guest.option_key', 'frontend_theme'), $theme->name);
        $this->themeSettings->set($theme, is_array($payload['settings'] ?? null) ? $payload['settings'] : []);
        Artisan::call('view:clear');

        session()->flash('status', __('Frontend theme imported successfully.'));
        $this->redirectRoute('admin-themes.frontend', ['tab' => $tab], navigate: true);
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
