<?php

namespace Modules\AdminThemes\Support;

use Illuminate\Support\Facades\File;

class ThemeRegistry
{
    /**
     * @var array<string, array<string, Theme>>
     */
    protected array $cache = [];

    /**
     * @return array<string, Theme>
     */
    public function forArea(string $area): array
    {
        if (isset($this->cache[$area])) {
            return $this->cache[$area];
        }

        $areaPath = rtrim(config('themes.base_path'), '/\\').DIRECTORY_SEPARATOR.$area;

        if (! File::isDirectory($areaPath)) {
            return $this->cache[$area] = [];
        }

        $themes = [];

        foreach (File::directories($areaPath) as $directory) {
            $name = basename($directory);

            $metaPath = $directory.DIRECTORY_SEPARATOR.'theme.json';
            $meta = File::exists($metaPath)
                ? json_decode(File::get($metaPath), true) ?: []
                : [];

            $themes[$name] = new Theme($area, $name, $directory, $meta);
        }

        uasort($themes, function (Theme $left, Theme $right): int {
            $leftOrder = (int) ($left->meta['order'] ?? PHP_INT_MAX);
            $rightOrder = (int) ($right->meta['order'] ?? PHP_INT_MAX);

            if ($leftOrder !== $rightOrder) {
                return $leftOrder <=> $rightOrder;
            }

            return strcasecmp($left->meta['name'] ?? $left->name, $right->meta['name'] ?? $right->name);
        });

        return $this->cache[$area] = $themes;
    }

    public function find(string $area, string $name): ?Theme
    {
        return $this->forArea($area)[$name] ?? null;
    }

    public function first(string $area): ?Theme
    {
        return array_values($this->forArea($area))[0] ?? null;
    }
}
