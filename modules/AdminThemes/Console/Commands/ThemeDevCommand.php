<?php

namespace Modules\AdminThemes\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class ThemeDevCommand extends Command
{
    protected $signature = 'theme:dev {theme}';

    protected $description = 'Run Vite dev server for a specific theme.';

    public function handle(): int
    {
        $theme = (string) $this->argument('theme');

        $this->info("Starting dev server for theme [{$theme}]...");

        Process::path(base_path())
            ->env(['THEME' => $theme])
            ->forever()
            ->start('npm run dev');

        $this->line('Vite dev server started in the background.');

        return self::SUCCESS;
    }
}
