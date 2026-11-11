<?php

namespace Modules\PaymentPaypal\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AdminSettings\Support\OptionStore;
use Modules\AppPayments\Support\PaymentGatewayDefinition;
use Modules\AppPayments\Support\PaymentGatewaySettingsRegistry;
use Modules\AppPayments\Support\PaymentManager;
use Modules\PaymentPaypal\Services\PaypalPaymentGateway;
use Modules\PaymentPaypal\Services\PaypalRecurringPaymentGateway;

class PaymentPaypalServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'paymentpaypal');

        app(PaymentManager::class)->register(
            new PaymentGatewayDefinition(
                key: 'paypal',
                title: 'PayPal',
                type: 'one_time',
                description: 'Pay with your PayPal balance or linked card.',
                icon: 'fa-brands fa-paypal',
                display: [
                    'logo' => asset('modules/PaymentPaypal/Public/logos/paypal.svg'),
                    'button_label' => 'Pay with PayPal',
                    'badge' => 'Wallet',
                    'priority_label' => 'Fast approval',
                    'checkout_note' => 'Redirects to PayPal Checkout for approval and capture.',
                    'checkout_hint' => 'Use your PayPal balance, linked bank account, or saved card.',
                    'theme_styles' => [
                        'border' => 'rgba(0, 48, 135, 0.18)',
                        'surface' => 'rgba(0, 48, 135, 0.03)',
                        'badge_border' => 'rgba(0, 48, 135, 0.18)',
                        'badge_bg' => 'rgba(0, 48, 135, 0.08)',
                        'badge_text' => '#003087',
                        'priority_bg' => 'rgba(0, 156, 222, 0.08)',
                        'priority_text' => '#005ea6',
                    ],
                ],
                reportLabel: 'PayPal',
                sort: 20,
                enabled: function (): bool {
                    $options = app(OptionStore::class);

                    return (bool) $options->get('paypal_status', 0)
                        && filled($options->get('paypal_client_id'))
                        && filled($options->get('paypal_client_secret'));
                }
            ),
            PaypalPaymentGateway::class
        );

        app(PaymentManager::class)->register(
            new PaymentGatewayDefinition(
                key: 'paypal_recurring',
                title: 'PayPal Recurring',
                type: 'recurring',
                description: 'Subscribe with PayPal for automatic renewals.',
                icon: 'fa-brands fa-paypal',
                display: [
                    'logo' => asset('modules/PaymentPaypal/Public/logos/paypal.svg'),
                    'button_label' => 'Subscribe with PayPal',
                    'badge' => 'Auto-renew',
                    'priority_label' => 'Managed in PayPal',
                    'checkout_note' => 'Your recurring agreement is created on PayPal and can be cancelled later.',
                    'checkout_hint' => 'Recurring billing is authorized in PayPal and renews automatically until cancelled.',
                    'theme_styles' => [
                        'border' => 'rgba(0, 48, 135, 0.18)',
                        'surface' => 'rgba(0, 48, 135, 0.03)',
                        'badge_border' => 'rgba(0, 48, 135, 0.18)',
                        'badge_bg' => 'rgba(0, 48, 135, 0.08)',
                        'badge_text' => '#003087',
                        'priority_bg' => 'rgba(0, 156, 222, 0.08)',
                        'priority_text' => '#005ea6',
                    ],
                ],
                reportLabel: 'PayPal Recurring',
                capabilities: ['user_cancel_recurring', 'system_cancel_recurring'],
                callbacks: [
                    'cancel_recurring' => function (
                        string $subscriptionId,
                        string $reason = '',
                    ): bool {
                        return app(PaypalRecurringPaymentGateway::class)->cancelSubscription($subscriptionId, $reason);
                    },
                ],
                sort: 30,
                enabled: function (): bool {
                    $options = app(OptionStore::class);

                    return (bool) $options->get('paypal_status', 0)
                        && (bool) $options->get('paypal_recurring_status', 0)
                        && filled($options->get('paypal_client_id'))
                        && filled($options->get('paypal_client_secret'));
                }
            ),
            PaypalRecurringPaymentGateway::class
        );

        app(PaymentGatewaySettingsRegistry::class)->register('paypal', [
            'title' => 'PayPal',
            'description' => 'Control PayPal checkout availability and provide the API credentials used for order creation and capture.',
            'view' => 'paymentpaypal::admin.settings',
            'order' => 20,
            'aliases' => ['paypal_recurring'],
            'status_key' => 'paypal_status',
            'state' => [
                'paypal_status' => '0',
                'paypal_recurring_status' => '0',
                'paypal_environment' => '0',
                'paypal_client_id' => '',
                'paypal_client_secret' => '',
            ],
            'rules' => [
                'state.paypal_status' => ['required', 'in:0,1'],
                'state.paypal_recurring_status' => ['required', 'in:0,1'],
                'state.paypal_environment' => ['required', 'in:0,1'],
                'state.paypal_client_id' => ['nullable', 'string', 'max:500', 'required_if:state.paypal_status,1'],
                'state.paypal_client_secret' => ['nullable', 'string', 'max:500', 'required_if:state.paypal_status,1'],
            ],
            'data' => fn (): array => [
                'toggleOptions' => [
                    ['value' => '1', 'label' => 'Enable'],
                    ['value' => '0', 'label' => 'Disable'],
                ],
                'environmentOptions' => [
                    ['value' => '0', 'label' => __('Sandbox')],
                    ['value' => '1', 'label' => __('Live')],
                ],
                'credentialTips' => [
                    [
                        'title' => __('Status'),
                        'body' => __('Controls whether PayPal one-time checkout is available at payment time.'),
                    ],
                    [
                        'title' => __('Recurring'),
                        'body' => __('Allows recurring PayPal subscriptions for monthly and yearly plans.'),
                    ],
                    [
                        'title' => __('Environment'),
                        'body' => __('Sandbox uses test app credentials. Live uses production app credentials.'),
                    ],
                ],
                'endpointItems' => [
                    ['label' => __('Webhook URL'), 'value' => route('payment.webhook', ['gateway' => 'paypal'])],
                    ['label' => __('Success URL'), 'value' => route('payment.success', ['gateway' => 'paypal'])],
                    ['label' => __('Cancel URL'), 'value' => route('payment.cancel', ['gateway' => 'paypal'])],
                ],
                'requirementCards' => [
                    [
                        'title' => __('Public HTTPS webhook URL'),
                        'body' => __('PayPal cannot send webhooks to localhost directly. Use a public HTTPS URL or a tunnel such as ngrok / Cloudflare Tunnel for local testing.'),
                    ],
                    [
                        'title' => __('Environment must match credentials'),
                        'body' => __('Sandbox mode requires Sandbox client ID and secret. Live mode requires Live credentials from the Live PayPal app.'),
                    ],
                    [
                        'title' => __('Register webhooks on the same app'),
                        'body' => __('Register the webhook URL inside the same PayPal REST app that owns the client ID and secret configured above.'),
                    ],
                ],
                'eventGroups' => [
                    [
                        'title' => __('One-time'),
                        'events' => [
                            'CHECKOUT.ORDER.APPROVED',
                            'PAYMENT.CAPTURE.COMPLETED',
                            'PAYMENT.CAPTURE.PENDING',
                            'CHECKOUT.PAYMENT-APPROVAL.REVERSED',
                        ],
                    ],
                    [
                        'title' => __('Recurring'),
                        'events' => [
                            'BILLING.SUBSCRIPTION.ACTIVATED',
                            'BILLING.SUBSCRIPTION.CANCELLED',
                            'BILLING.SUBSCRIPTION.SUSPENDED',
                            'BILLING.SUBSCRIPTION.EXPIRED',
                            'BILLING.SUBSCRIPTION.PAYMENT.FAILED',
                            'PAYMENT.SALE.COMPLETED',
                        ],
                    ],
                ],
            ],
        ]);
    }
}
