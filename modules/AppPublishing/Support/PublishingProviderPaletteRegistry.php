<?php

namespace Modules\AppPublishing\Support;

class PublishingProviderPaletteRegistry
{
    /**
     * @var array<string, array{surface:string,text:string}>
     */
    protected array $tones = [];

    public function register(string $providerKey, array $tone): self
    {
        $key = strtolower(trim($providerKey));
        $surface = trim((string) ($tone['surface'] ?? ''));
        $text = trim((string) ($tone['text'] ?? ''));

        if ($key !== '' && $surface !== '' && $text !== '') {
            $this->tones[$key] = [
                'surface' => $surface,
                'text' => $text,
            ];
        }

        return $this;
    }

    public function get(string $providerKey, array $default = ['surface' => 'rgba(99, 102, 241, 0.12)', 'text' => '#4f46e5']): array
    {
        $key = strtolower(trim($providerKey));

        if (isset($this->tones[$key])) {
            return $this->tones[$key];
        }

        $provider = function_exists('integration_item') ? integration_item($key) : [];
        $color = trim((string) ($provider['color'] ?? ''));

        if ($color === '') {
            return $default;
        }

        $rgb = $this->hexToRgb($color);

        if (! $rgb) {
            return $default;
        }

        return [
            'surface' => sprintf('rgba(%d, %d, %d, 0.12)', $rgb['r'], $rgb['g'], $rgb['b']),
            'text' => $color,
        ];
    }

    protected function hexToRgb(string $value): ?array
    {
        $hex = ltrim(trim($value), '#');

        if (strlen($hex) === 3) {
            $hex = preg_replace('/(.)/', '$1$1', $hex) ?? $hex;
        }

        if (strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            return null;
        }

        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2)),
        ];
    }
}
