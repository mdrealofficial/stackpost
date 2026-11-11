<?php

namespace Modules\AdminThemes\Support;

use Modules\AdminSettings\Support\OptionStore;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ThemeManager
{
    /**
     * @var array<string, Theme>
     */
    protected array $resolved = [];

    protected ?string $currentArea = null;

    public function __construct(
        protected ThemeRegistry $registry,
        protected ThemeAreaResolver $areaResolver,
        protected OptionStore $optionStore,
    ) {
    }

    /**
     * @return array<string, Theme>
     */
    public function resolvedThemes(): array
    {
        return $this->resolved;
    }

    public function currentArea(): string
    {
        return $this->currentArea ?? config('themes.default_area', 'guest');
    }

    public function flushResolved(): void
    {
        $this->resolved = [];
        $this->currentArea = null;
    }

    public function setCurrentArea(string $area): void
    {
        $this->currentArea = $area;
    }

    public function resolveForRequest(Request $request): Theme
    {
        $area = $this->areaResolver->resolve($request);
        $this->setCurrentArea($area);

        if ($theme = $this->demoThemeOverride($request, $area)) {
            return $this->resolved[$area] = $theme;
        }

        return $this->themeForArea($area);
    }

    public function currentTheme(): Theme
    {
        return $this->themeForArea($this->currentArea());
    }

    public function themeForArea(string $area): Theme
    {
        if (isset($this->resolved[$area])) {
            return $this->resolved[$area];
        }

        $definition = config("themes.areas.{$area}");

        if (! is_array($definition)) {
            throw new InvalidArgumentException("Theme area [{$area}] is not configured.");
        }

        $name = (string) $this->optionStore->get(
            $definition['option_key'] ?? "{$area}_theme",
            $definition['fallback'] ?? 'default'
        );

        $theme = $this->registry->find($area, $name)
            ?? $this->registry->find($area, (string) ($definition['fallback'] ?? 'default'))
            ?? $this->registry->first($area);

        if (! $theme) {
            throw new InvalidArgumentException("No theme registered for area [{$area}].");
        }

        return $this->resolved[$area] = $theme;
    }

    protected function demoThemeOverride(Request $request, string $area): ?Theme
    {
        if (! config('app.demo_mode') || $area !== 'guest') {
            return null;
        }

        $name = (string) ($request->query('demo_theme') ?: $request->cookie('demo_frontend_theme', ''));

        if (! in_array($name, ['default', 'luma'], true)) {
            return null;
        }

        return $this->registry->find($area, $name);
    }
}
