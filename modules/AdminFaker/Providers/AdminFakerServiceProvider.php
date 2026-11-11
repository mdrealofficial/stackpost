<?php

namespace Modules\AdminFaker\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Modules\AdminCrons\Support\SystemCronRegistry;
use Modules\AdminFaker\Console\Commands\RefreshAdminFakerCommand;

class AdminFakerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'adminfaker');

        $this->commands([
            RefreshAdminFakerCommand::class,
        ]);

        if ($this->app->runningInConsole()) {
            $this->app->booted(function (): void {
                $this->app->make(Schedule::class)
                    ->command('admin-faker:refresh --no-clear')
                    ->dailyAt('02:00')
                    ->withoutOverlapping();
            });
        }

        $this->app->afterResolving(SystemCronRegistry::class, function (SystemCronRegistry $registry): void {
            $registry->register([
                'key' => 'admin-faker-rebuild',
                'name' => __('Admin Faker Rebuild'),
                'icon' => 'fa-light fa-rotate-right',
                'description' => __('Clear existing demo data for the first user account, then seed a fresh demo workspace from scratch.'),
                'command' => 'admin-faker:refresh',
                'recommended' => false,
                'cron_expression' => '0 2 * * *',
            ]);
        });
    }
}
