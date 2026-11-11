<?php

namespace App\Social;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Console\Scheduling\Schedule;

class SocialServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Automation\Registry::class);
    }

    public function boot(): void
    {
        Route::prefix('webhooks/meta')->middleware(['api', 'throttle:120,1'])->group(function (): void {
            Route::match(['get', 'post'], '/{provider}', Http\Controllers\MetaWebhookController::class)
                ->whereIn('provider', ['facebook', 'instagram'])->name('api.social-inbox.meta-webhook');
        });
        $this->loadViewsFrom(__DIR__.'/views', 'social-automation');
        $this->commands([Automation\DispatchCommand::class]);
        if (class_exists(\Modules\AdminCrons\Support\SystemCronRegistry::class)) {
            app(\Modules\AdminCrons\Support\SystemCronRegistry::class)->register([
                'key'=>'social-automations','name'=>__('DM Automation & Auto Comment'),
                'icon'=>'fa-light fa-comments','description'=>__('Process incoming interactions and approved replies. Run every minute.'),
                'command'=>'social:automations',
            ]);
        }
        $this->app->afterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('social:automations')->everyMinute()->withoutOverlapping(10);
        });
    }
}
