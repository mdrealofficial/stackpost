<?php

namespace Modules\AdminThemes\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MakeThemeCommand extends Command
{
    protected $signature = 'theme:make {area} {name}';

    protected $description = 'Create a new frontend or backend theme skeleton.';

    public function handle(): int
    {
        $area = trim((string) $this->argument('area'));
        $name = trim((string) $this->argument('name'));

        if (! array_key_exists($area, (array) config('themes.areas', []))) {
            $this->error("Unknown theme area [{$area}].");

            return self::FAILURE;
        }

        $themePath = resource_path("themes/{$area}/{$name}");

        if (File::exists($themePath)) {
            $this->error("Theme [{$area}/{$name}] already exists.");

            return self::FAILURE;
        }

        File::ensureDirectoryExists("{$themePath}/assets/css");
        File::ensureDirectoryExists("{$themePath}/assets/js");
        File::ensureDirectoryExists("{$themePath}/resources/views/layouts");
        File::ensureDirectoryExists("{$themePath}/resources/views/pages");

        File::put("{$themePath}/theme.json", json_encode([
            'name' => ucfirst($name),
            'area' => $area,
            'version' => '1.0.0',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        File::put("{$themePath}/assets/js/app.js", "import '../css/app.css';\n");
        File::put("{$themePath}/assets/css/app.css", "@import '../../../../../themes/shared/css/theme-base.css';\n");

        $this->info("Theme [{$area}/{$name}] created.");

        return self::SUCCESS;
    }
}
