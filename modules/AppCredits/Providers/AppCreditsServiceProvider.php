<?php

namespace Modules\AppCredits\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AppCredits\Support\CreditActionRegistry;
use Modules\AppCredits\Support\CreditPackCheckoutTypeHandler;
use Modules\AppCredits\Support\CreditService;
use Modules\AppCredits\Support\CreditSettings;
use Modules\AppCredits\Support\CreditTopupService;
use Modules\AppPayments\Support\PaymentCheckoutTypeRegistry;

class AppCreditsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.appcredits');
        $this->app->singleton(CreditActionRegistry::class);
        $this->app->singleton(CreditSettings::class);
        $this->app->singleton(CreditTopupService::class);
        $this->app->singleton(CreditService::class);
        $this->app->singleton(CreditPackCheckoutTypeHandler::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'appcredits');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        register_setting_item('general', [
            'label' => 'Credits',
            'description' => 'Configure credit top-ups, pack availability, and empty-balance behavior.',
            'route_name' => 'settings.credits',
            'active_when' => ['settings.credits'],
            'order' => 11,
        ]);

        app(PaymentCheckoutTypeRegistry::class)->register('credit_pack', CreditPackCheckoutTypeHandler::class);
    }
}
