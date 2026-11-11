<?php

namespace Modules\AdminThemes\Support;

class Theme
{
    public function __construct(
        public readonly string $area,
        public readonly string $name,
        public readonly string $path,
        public readonly array $meta = [],
    ) {
    }

    public function id(): string
    {
        return "{$this->area}/{$this->name}";
    }

    public function viewsPath(): string
    {
        return "{$this->path}/resources/views";
    }

    public function assetsPath(): string
    {
        return "{$this->path}/assets";
    }

    public function buildDirectory(): string
    {
        return trim(config('themes.vite.build_root', 'build/themes'), '/')."/{$this->area}/{$this->name}";
    }
}
