<?php

namespace Modules\PaymentRazorpay\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AdminSettings\Support\OptionStore;
use Modules\AppPayments\Support\PaymentGatewayDefinition;
use Modules\AppPayments\Support\PaymentGatewaySettingsRegistry;
use Modules\AppPayments\Support\PaymentManager;
use Modules\PaymentRazorpay\Services\RazorpayPaymentGateway;
use Modules\PaymentRazorpay\Services\RazorpayRecurringPaymentGateway;

class PaymentRazorpayServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'paymentrazorpay');

        app(PaymentManager::class)->register(
            new PaymentGatewayDefinition(
                key: 'razorpay',
                title: 'Razorpay',
                type: 'one_time',
                description: 'Pay with cards, UPI, netbanking, and wallets via Razorpay.',
                icon: 'fa-light fa-credit-card',
                display: [
                    'logo' => asset('modules/PaymentRazorpay/Public/logos/razorpay.svg'),
                    'button_label' => 'Pay with Razorpay',
                    'badge' => 'UPI / Cards',
                    'priority_label' => 'India-first',
                    'checkout_note' => 'Razorpay Standard Checkout handles payment authorization and capture.',
                    'checkout_hint' => 'Use cards, UPI, wallets, or netbanking from Razorpay Checkout.',
                    'theme_styles' => [
                        'border' => 'rgba(7, 101, 255, 0.18)',
                        'surface' => 'rgba(7, 101, 255, 0.03)',
                        'badge_border' => 'rgba(7, 101, 255, 0.18)',
                        'badge_bg' => 'rgba(7, 101, 255, 0.08)',
                        'badge_text' => '#0765ff',
                        'priority_bg' => 'rgba(0, 174, 255, 0.08)',
                        'priority_text' => '#0b57d0',
                    ],
                ],
                reportLabel: 'Razorpay',
                sort: 60,
                enabled: function (): bool {
                    $options = app(OptionStore::class);

                    return (bool) $options->get('razorpay_status', 0)
                        && filled($options->get('razorpay_key_id'))
                        && filled($options->get('razorpay_key_secret'));
                }
            ),
            RazorpayPaymentGateway::class
        );

        app(PaymentManager::class)->register(
            new PaymentGatewayDefinition(
                key: 'razorpay_recurring',
                title: 'Razorpay Recurring',
                type: 'recurring',
                description: 'Authorize recurring billing with Razorpay subscriptions.',
                icon: 'fa-light fa-arrows-rotate',
                display: [
                    'logo' => asset('modules/PaymentRazorpay/Public/logos/razorpay.svg'),
                    'button_label' => 'Subscribe with Razorpay',
                    'badge' => 'Auto-renew',
                    'priority_label' => 'Cards and UPI AutoPay',
                    'checkout_note' => 'Razorpay creates and manages the recurring billing agreement for this plan.',
                    'checkout_hint' => 'Best when you want recurring billing managed from Razorpay subscriptions.',
                    'theme_styles' => [
                        'border' => 'rgba(7, 101, 255, 0.18)',
                        'surface' => 'rgba(7, 101, 255, 0.03)',
                        'badge_border' => 'rgba(7, 101, 255, 0.18)',
                        'badge_bg' => 'rgba(7, 101, 255, 0.08)',
                        'badge_text' => '#0765ff',
                        'priority_bg' => 'rgba(0, 174, 255, 0.08)',
                        'priority_text' => '#0b57d0',
                    ],
                ],
                reportLabel: 'Razorpay Recurring',
                capabilities: ['user_cancel_recurring', 'system_cancel_recurring'],
                callbacks: [
                    'cancel_recurring' => function (
                        string $subscriptionId,
                        bool $atPeriodEnd = false,
                    ): bool {
                        return app(RazorpayRecurringPaymentGateway::class)->cancelSubscription($subscriptionId, $atPeriodEnd);
                    },
                ],
                sort: 70,
                enabled: function (): bool {
                    $options = app(OptionStore::class);

                    return (bool) $options->get('razorpay_status', 0)
                        && (bool) $options->get('razorpay_recurring_status', 0)
                        && filled($options->get('razorpay_key_id'))
                        && filled($options->get('razorpay_key_secret'));
                }
            ),
            RazorpayRecurringPaymentGateway::class
        );

        app(PaymentGatewaySettingsRegistry::class)->register('razorpay', [
            'title' => 'Razorpay',
            'description' => 'Configure Razorpay credentials for one-time and recurring billing.',
            'view' => 'paymentrazorpay::admin.settings',
            'order' => 40,
            'aliases' => ['razorpay_recurring'],
            'status_key' => 'razorpay_status',
            'state' => [
                'razorpay_status' => '0',
                'razorpay_recurring_status' => '0',
                'razorpay_key_id' => '',
                'razorpay_key_secret' => '',
                'razorpay_webhook_secret' => '',
            ],
            'rules' => [
                'state.razorpay_status' => ['required', 'in:0,1'],
                'state.razorpay_recurring_status' => ['required', 'in:0,1'],
                'state.razorpay_key_id' => ['nullable', 'string', 'max:255', 'required_if:state.razorpay_status,1'],
                'state.razorpay_key_secret' => ['nullable', 'string', 'max:255', 'required_if:state.razorpay_status,1'],
                'state.razorpay_webhook_secret' => ['nullable', 'string', 'max:255'],
            ],
            'data' => fn (): array => [
                'toggleOptions' => [
                    ['value' => '1', 'label' => 'Enable'],
                    ['value' => '0', 'label' => 'Disable'],
                ],
                'credentialTips' => [
                    ['title' => __('Status'), 'body' => __('Controls whether Razorpay one-time checkout is available at payment time.')],
                    ['title' => __('Recurring'), 'body' => __('Allows recurring Razorpay subscriptions for monthly and yearly plans.')],
                    ['title' => __('Mode'), 'body' => __('Mode is derived from your key ID. Test keys usually start with :test while live keys start with :live.', ['test' => 'rzp_test_', 'live' => 'rzp_live_'])],
                ],
                'endpointItems' => [
                    ['label' => __('Webhook URL'), 'value' => route('payment.webhook', ['gateway' => 'razorpay'])],
                    ['label' => __('Success URL'), 'value' => route('payment.success', ['gateway' => 'razorpay'])],
                    ['label' => __('Cancel URL'), 'value' => route('payment.cancel', ['gateway' => 'razorpay'])],
                ],
                'requirementCards' => [
                    ['title' => __('Public HTTPS callback URL'), 'body' => __('Razorpay callbacks and webhooks require a public HTTPS URL. Use a tunnel such as ngrok or Cloudflare Tunnel for local testing.')],
                    ['title' => __('Use matching test/live keys'), 'body' => __('The key ID, key secret, and webhook secret must all come from the same Razorpay mode. Do not mix test and live credentials.')],
                    ['title' => __('Enable subscriptions on your account'), 'body' => __('Recurring checkout requires the Subscriptions product to be enabled on the same Razorpay account that owns the configured API keys.')],
                ],
                'eventGroups' => [
                    ['title' => __('One-time'), 'events' => ['payment.authorized', 'payment.captured', 'payment.failed', 'order.paid']],
                    ['title' => __('Recurring'), 'events' => ['subscription.authenticated', 'subscription.activated', 'subscription.charged', 'subscription.pending', 'subscription.halted', 'subscription.cancelled', 'subscription.completed']],
                ],
            ],
        ]);
    }
}
