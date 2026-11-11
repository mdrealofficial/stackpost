<?php

namespace Modules\AdminThemes\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class ThemeBuildCommand extends Command
{
    protected $signature = 'theme:build {theme}';

    protected $description = 'Build assets for a specific theme.';

    public function handle(): int
    {
        $theme = (string) $this->argument('theme');

        $this->info("Building theme [{$theme}]...");

        $result = Process::path(base_path())
            ->env(['THEME' => $theme])
            ->timeout(300)
            ->run('npm run build');

        if ($result->failed()) {
            $this->error($result->errorOutput() ?: $result->output());

            return self::FAILURE;
        }

        $this->line($result->output());

        return self::SUCCESS;
    }
}
