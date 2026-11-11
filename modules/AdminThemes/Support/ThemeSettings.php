<?php

namespace Modules\AdminThemes\Support;

use Modules\AdminSettings\Support\OptionStore;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ThemeSettings
{
    protected array $fontStacks = [
        'inter' => '"Inter", ui-sans-serif, system-ui, sans-serif',
        'instrument-sans' => '"Instrument Sans", ui-sans-serif, system-ui, sans-serif',
        'plus-jakarta-sans' => '"Plus Jakarta Sans", ui-sans-serif, system-ui, sans-serif',
        'manrope' => '"Manrope", ui-sans-serif, system-ui, sans-serif',
        'outfit' => '"Outfit", ui-sans-serif, system-ui, sans-serif',
        'sora' => '"Sora", ui-sans-serif, system-ui, sans-serif',
        'space-grotesk' => '"Space Grotesk", ui-sans-serif, system-ui, sans-serif',
        'public-sans' => '"Public Sans", ui-sans-serif, system-ui, sans-serif',
        'ibm-plex-sans' => '"IBM Plex Sans", ui-sans-serif, system-ui, sans-serif',
        'dm-sans' => '"DM Sans", ui-sans-serif, system-ui, sans-serif',
        'system' => 'ui-sans-serif, system-ui, sans-serif',
    ];

    public function __construct(
        protected OptionStore $options,
    ) {}

    public function schema(Theme $theme): array
    {
        return Arr::get($theme->meta, 'settings.schema', [
            'accent_color' => [
                'type' => 'color',
                'label' => 'Accent color',
                'default' => '#4f46e5',
            ],
            'font_family' => [
                'type' => 'select',
                'label' => 'Font family',
                'default' => 'inter',
                'options' => [
                    'inter' => 'Inter',
                    'instrument-sans' => 'Instrument Sans',
                    'plus-jakarta-sans' => 'Plus Jakarta Sans',
                    'manrope' => 'Manrope',
                    'outfit' => 'Outfit',
                    'sora' => 'Sora',
                    'space-grotesk' => 'Space Grotesk',
                    'public-sans' => 'Public Sans',
                    'ibm-plex-sans' => 'IBM Plex Sans',
                    'dm-sans' => 'DM Sans',
                    'system' => 'System',
                ],
            ],
            'layout_width' => [
                'type' => 'select',
                'label' => 'Layout width',
                'default' => 'full',
                'options' => [
                    'full' => 'Full width',
                    'boxed' => 'Boxed',
                ],
            ],
            'supports_dark_mode' => [
                'type' => 'select',
                'label' => 'Dark mode support',
                'default' => '1',
                'options' => [
                    '1' => 'Enabled',
                    '0' => 'Disabled',
                ],
            ],
            'allow_user_appearance_toggle' => [
                'type' => 'select',
                'label' => 'Appearance switcher',
                'default' => '1',
                'options' => [
                    '1' => 'Enabled',
                    '0' => 'Disabled',
                ],
            ],
            'default_appearance' => [
                'type' => 'select',
                'label' => 'Default appearance',
                'default' => 'system',
                'options' => [
                    'system' => 'System',
                    'light' => 'Light',
                    'dark' => 'Dark',
                ],
            ],
        ]);
    }

    public function defaults(Theme $theme): array
    {
        $schema = $this->schema($theme);

        return collect($schema)
            ->mapWithKeys(fn (array $field, string $key) => [$key => $field['default'] ?? null])
            ->all();
    }

    public function values(Theme $theme): array
    {
        $stored = $this->options->get($this->optionKey($theme), []);
        $stored = is_string($stored) ? json_decode($stored, true) : $stored;
        $stored = is_array($stored) ? $stored : [];

        return $this->sanitize($theme, array_replace($this->defaults($theme), $stored));
    }

    public function get(Theme $theme, string $key, mixed $default = null): mixed
    {
        return $this->values($theme)[$key] ?? $default;
    }

    public function set(Theme $theme, array $settings): void
    {
        $this->options->set($this->optionKey($theme), $this->sanitize($theme, $settings));
    }

    public function fontStack(Theme $theme): string
    {
        $key = (string) $this->get($theme, 'font_family', 'inter');

        return $this->fontStacks[$key] ?? $this->fontStacks['inter'];
    }

    public function accentRgb(Theme $theme): string
    {
        return $this->hexToRgb((string) $this->get($theme, 'accent_color', '#4f46e5'));
    }

    public function colorRgb(Theme $theme, string $key, string $fallback = '#4f46e5'): string
    {
        return $this->hexToRgb((string) $this->get($theme, $key, $fallback));
    }

    protected function sanitize(Theme $theme, array $settings): array
    {
        $schema = $this->schema($theme);
        $sanitized = [];

        foreach ($schema as $key => $field) {
            $value = $settings[$key] ?? ($field['default'] ?? null);

            $sanitized[$key] = match ($field['type'] ?? 'text') {
                'color' => $this->sanitizeColor($value, (string) ($field['default'] ?? '#4f46e5')),
                'select' => $this->sanitizeSelect($value, array_keys($field['options'] ?? []), $field['default'] ?? null),
                default => is_scalar($value) || $value === null ? (string) $value : (string) ($field['default'] ?? ''),
            };
        }

        return $sanitized;
    }

    protected function sanitizeColor(mixed $value, string $fallback): string
    {
        $value = is_string($value) ? trim($value) : '';

        if (preg_match('/^#([0-9a-fA-F]{6})$/', $value) !== 1) {
            return $fallback;
        }

        return Str::lower($value);
    }

    protected function sanitizeSelect(mixed $value, array $allowed, mixed $fallback): mixed
    {
        $normalizedValue = is_scalar($value) || $value === null ? (string) $value : '';
        $normalizedAllowed = array_map(
            static fn ($item) => is_scalar($item) || $item === null ? (string) $item : '',
            $allowed,
        );
        $normalizedFallback = is_scalar($fallback) || $fallback === null ? (string) $fallback : $fallback;

        return in_array($normalizedValue, $normalizedAllowed, true) ? $normalizedValue : $normalizedFallback;
    }

    protected function optionKey(Theme $theme): string
    {
        return "theme_settings.{$theme->area}.{$theme->name}";
    }

    protected function hexToRgb(string $hex): string
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) !== 6) {
            return '79, 70, 229';
        }

        return implode(', ', [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ]);
    }
}
