<?php

namespace Modules\AdminCaptcha\Providers;

use Illuminate\Support\ServiceProvider;

class AdminCaptchaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.admincaptcha');

        $helperPath = __DIR__.'/../Support/helpers.php';
        if (file_exists($helperPath)) {
            require_once $helperPath;
        }
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'admincaptcha');

        register_setting_item('general', [
            'label' => 'Captcha',
            'description' => 'Configure bot protection for login, registration, and guest forms.',
            'route_name' => 'admin-captcha.index',
            'active_when' => ['admin-captcha.*'],
            'order' => 40,
        ]);
    }
}
