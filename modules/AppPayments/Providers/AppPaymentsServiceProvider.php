<?php

namespace Modules\AppPayments\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Modules\AppPayments\Console\Commands\ActivateScheduledPlansCommand;
use Modules\AppPayments\Gateways\Manual\ManualPaymentGateway;
use Modules\AppPayments\Support\PaymentGatewaySettingsRegistry;
use Modules\AppPayments\Support\PaymentCheckoutStore;
use Modules\AppPayments\Support\PaymentGatewayDefinition;
use Modules\AppPayments\Support\PaymentCheckoutTypeRegistry;
use Modules\AppPayments\Support\PaymentLifecycleService;
use Modules\AppPayments\Support\PaymentManager;
use Modules\AppPayments\Support\PaymentNotificationService;
use Modules\AppPayments\Support\PaymentPlanResolver;
use Modules\AppPayments\Support\PlanCheckoutTypeHandler;
use Modules\AppPayments\Support\UserPlanTransitionService;

class AppPaymentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentManager::class);
        $this->app->singleton(PaymentGatewaySettingsRegistry::class);
        $this->app->singleton(PaymentCheckoutStore::class);
        $this->app->singleton(PaymentCheckoutTypeRegistry::class);
        $this->app->singleton(PaymentPlanResolver::class);
        $this->app->singleton(PaymentLifecycleService::class);
        $this->app->singleton(PaymentNotificationService::class);
        $this->app->singleton(PlanCheckoutTypeHandler::class);
        $this->app->singleton(UserPlanTransitionService::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'apppayments');

        $this->commands([
            ActivateScheduledPlansCommand::class,
        ]);

        if ($this->app->runningInConsole()) {
            $this->app->booted(function (): void {
                $this->app->make(Schedule::class)
                    ->command('plans:activate-scheduled')
                    ->everyMinute()
                    ->withoutOverlapping();
            });
        }

        register_setting_item('general', [
            'label' => 'Payment Gateways',
            'description' => 'Configure payment gateway modules and manual transfer checkout settings.',
            'route_name' => 'settings.payment-gateways',
            'active_when' => ['settings.payment-gateways', 'admin-payment-manual-config.*'],
            'order' => 12,
        ]);

        app(PaymentCheckoutTypeRegistry::class)->register('plan', PlanCheckoutTypeHandler::class);

        app(PaymentManager::class)->register(
            new PaymentGatewayDefinition(
                key: 'manual',
                title: 'Manual Transfer',
                type: 'manual',
                description: 'Create a payment request for offline review.',
                icon: 'fa-light fa-money-check-dollar',
                reportLabel: 'Manual Transfer',
                sort: 10,
                enabled: true,
            ),
            ManualPaymentGateway::class
        );

        app(PaymentGatewaySettingsRegistry::class)->register('manual', [
            'title' => 'Manual Payment',
            'description' => 'Enable offline payment review and define the bank transfer or cash instructions customers will see during checkout.',
            'view' => 'apppayments::admin.partials.manual-settings',
            'order' => 10,
            'status_key' => 'payment_manual_status',
            'state' => [
                'payment_manual_status' => '0',
                'payment_manual_prefix' => 'PAY-',
                'payment_manual_info' => 'Bank Info',
            ],
            'rules' => [
                'state.payment_manual_status' => ['required', 'in:0,1'],
                'state.payment_manual_prefix' => ['required', 'string', 'max:50'],
                'state.payment_manual_info' => ['required', 'string', 'max:10000'],
            ],
            'data' => fn (): array => [
                'toggleOptions' => [
                    ['value' => '1', 'label' => 'Enable'],
                    ['value' => '0', 'label' => 'Disable'],
                ],
            ],
        ]);

        app(PaymentGatewaySettingsRegistry::class)->register('notifications', [
            'title' => 'Payment Notifications',
            'description' => 'Configure customer email notifications for payment lifecycle events.',
            'view' => 'apppayments::admin.partials.notification-settings',
            'order' => 5,
            'status_key' => 'payment_notify_success_status',
            'state' => PaymentNotificationService::defaults(),
            'rules' => [
                'state.payment_notify_success_status' => ['required', 'in:0,1'],
                'state.payment_notify_success_subject' => ['required', 'string', 'max:255'],
                'state.payment_notify_success_message' => ['required', 'string', 'max:5000'],
                'state.payment_notify_failed_status' => ['required', 'in:0,1'],
                'state.payment_notify_failed_subject' => ['required', 'string', 'max:255'],
                'state.payment_notify_failed_message' => ['required', 'string', 'max:5000'],
                'state.payment_notify_review_status' => ['required', 'in:0,1'],
                'state.payment_notify_review_subject' => ['required', 'string', 'max:255'],
                'state.payment_notify_review_message' => ['required', 'string', 'max:5000'],
                'state.payment_notify_cancel_status' => ['required', 'in:0,1'],
                'state.payment_notify_cancel_subject' => ['required', 'string', 'max:255'],
                'state.payment_notify_cancel_message' => ['required', 'string', 'max:5000'],
            ],
        ]);
    }
}
