<?php

namespace Modules\AdminMailServer\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AdminMailServer\Support\MailServerConfigurator;
use Modules\AdminSettings\Support\OptionStore;

class AdminMailServerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.adminmailserver');
    }

    public function boot(OptionStore $options): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'adminmailserver');

        register_setting_item('general', [
            'label' => 'Mail Server',
            'description' => 'Configure delivery transport, sender identity, SMTP credentials, and test email flow.',
            'route_name' => 'settings.mail-server',
            'active_when' => ['settings.mail-server'],
            'order' => 11,
        ]);

        MailServerConfigurator::apply(MailServerConfigurator::fromOptions($options));
    }
}
