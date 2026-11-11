<?php

namespace Modules\PaymentStripe\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AdminSettings\Support\OptionStore;
use Modules\AppPayments\Support\PaymentGatewayDefinition;
use Modules\AppPayments\Support\PaymentGatewaySettingsRegistry;
use Modules\AppPayments\Support\PaymentManager;
use Modules\PaymentStripe\Services\StripePaymentGateway;
use Modules\PaymentStripe\Services\StripeRecurringPaymentGateway;

class PaymentStripeServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'paymentstripe');

        app(PaymentManager::class)->register(
            new PaymentGatewayDefinition(
                key: 'stripe',
                title: 'Stripe',
                type: 'one_time',
                description: 'Pay securely with card via Stripe Checkout.',
                icon: 'fa-brands fa-stripe',
                display: [
                    'logo' => asset('modules/PaymentStripe/Public/logos/stripe.svg'),
                    'button_label' => 'Pay with Stripe',
                    'badge' => 'Card',
                    'priority_label' => 'Instant confirmation',
                    'checkout_note' => 'Stripe Checkout handles secure card collection and payment confirmation.',
                    'checkout_hint' => 'Ideal for direct card payments with a hosted Stripe Checkout page.',
                    'theme_styles' => [
                        'border' => 'rgba(99, 91, 255, 0.18)',
                        'surface' => 'rgba(99, 91, 255, 0.03)',
                        'badge_border' => 'rgba(99, 91, 255, 0.18)',
                        'badge_bg' => 'rgba(99, 91, 255, 0.08)',
                        'badge_text' => '#635bff',
                        'priority_bg' => 'rgba(99, 91, 255, 0.08)',
                        'priority_text' => '#4f46e5',
                    ],
                ],
                reportLabel: 'Stripe',
                sort: 40,
                enabled: function (): bool {
                    $options = app(OptionStore::class);

                    return (bool) $options->get('stripe_status', 0)
                        && filled($options->get('stripe_publishable_key'))
                        && filled($options->get('stripe_secret_key'));
                }
            ),
            StripePaymentGateway::class
        );

        app(PaymentManager::class)->register(
            new PaymentGatewayDefinition(
                key: 'stripe_recurring',
                title: 'Stripe Recurring',
                type: 'recurring',
                description: 'Subscribe with Stripe for automatic renewals.',
                icon: 'fa-brands fa-stripe',
                display: [
                    'logo' => asset('modules/PaymentStripe/Public/logos/stripe.svg'),
                    'button_label' => 'Subscribe with Stripe',
                    'badge' => 'Auto-renew',
                    'priority_label' => 'Best for cards',
                    'checkout_note' => 'Stripe creates and manages the recurring subscription for this plan.',
                    'checkout_hint' => 'Recurring billing stays on Stripe and can be managed later from billing.',
                    'theme_styles' => [
                        'border' => 'rgba(99, 91, 255, 0.18)',
                        'surface' => 'rgba(99, 91, 255, 0.03)',
                        'badge_border' => 'rgba(99, 91, 255, 0.18)',
                        'badge_bg' => 'rgba(99, 91, 255, 0.08)',
                        'badge_text' => '#635bff',
                        'priority_bg' => 'rgba(99, 91, 255, 0.08)',
                        'priority_text' => '#4f46e5',
                    ],
                ],
                reportLabel: 'Stripe Recurring',
                capabilities: ['user_cancel_recurring', 'system_cancel_recurring'],
                callbacks: [
                    'cancel_recurring' => function (
                        string $subscriptionId,
                        bool $atPeriodEnd = false,
                    ): bool {
                        return app(StripeRecurringPaymentGateway::class)->cancelSubscription($subscriptionId, $atPeriodEnd);
                    },
                ],
                sort: 50,
                enabled: function (): bool {
                    $options = app(OptionStore::class);

                    return (bool) $options->get('stripe_status', 0)
                        && (bool) $options->get('stripe_recurring_status', 0)
                        && filled($options->get('stripe_publishable_key'))
                        && filled($options->get('stripe_secret_key'));
                }
            ),
            StripeRecurringPaymentGateway::class
        );

        app(PaymentGatewaySettingsRegistry::class)->register('stripe', [
            'title' => 'Stripe',
            'description' => 'Configure Stripe Checkout credentials for one-time and recurring billing.',
            'view' => 'paymentstripe::admin.settings',
            'order' => 30,
            'aliases' => ['stripe_recurring'],
            'status_key' => 'stripe_status',
            'state' => [
                'stripe_status' => '0',
                'stripe_recurring_status' => '0',
                'stripe_publishable_key' => '',
                'stripe_secret_key' => '',
                'stripe_webhook_secret' => '',
            ],
            'rules' => [
                'state.stripe_status' => ['required', 'in:0,1'],
                'state.stripe_recurring_status' => ['required', 'in:0,1'],
                'state.stripe_publishable_key' => ['nullable', 'string', 'max:255', 'required_if:state.stripe_status,1'],
                'state.stripe_secret_key' => ['nullable', 'string', 'max:255', 'required_if:state.stripe_status,1'],
                'state.stripe_webhook_secret' => ['nullable', 'string', 'max:255'],
            ],
            'data' => fn (): array => [
                'toggleOptions' => [
                    ['value' => '1', 'label' => 'Enable'],
                    ['value' => '0', 'label' => 'Disable'],
                ],
                'credentialTips' => [
                    [
                        'title' => __('Status'),
                        'body' => __('Controls whether Stripe one-time checkout is available at payment time.'),
                    ],
                    [
                        'title' => __('Recurring'),
                        'body' => __('Allows recurring Stripe subscriptions for monthly and yearly plans.'),
                    ],
                    [
                        'title' => __('Mode'),
                        'body' => __('Mode is derived from your keys. Test keys start with :test and live keys start with :live.', ['test' => 'sk_test_', 'live' => 'sk_live_']),
                    ],
                ],
                'endpointItems' => [
                    ['label' => __('Webhook URL'), 'value' => route('payment.webhook', ['gateway' => 'stripe'])],
                    ['label' => __('Success URL'), 'value' => route('payment.success', ['gateway' => 'stripe'])],
                    ['label' => __('Cancel URL'), 'value' => route('payment.cancel', ['gateway' => 'stripe'])],
                ],
                'requirementCards' => [
                    [
                        'title' => __('Public HTTPS webhook URL'),
                        'body' => __('Stripe cannot push webhooks to localhost directly. Use a public HTTPS URL or a tunnel such as ngrok / Cloudflare Tunnel during local testing.'),
                    ],
                    [
                        'title' => __('Key mode must match'),
                        'body' => __('Publishable key, secret key, and webhook signing secret must all come from the same Stripe mode: test or live.'),
                    ],
                    [
                        'title' => __('Use the webhook signing secret'),
                        'body' => __('Copy the webhook signing secret from Stripe and store it here so incoming webhook signatures can be verified.'),
                    ],
                ],
                'eventGroups' => [
                    [
                        'title' => __('One-time'),
                        'events' => [
                            'checkout.session.completed',
                            'checkout.session.async_payment_succeeded',
                            'checkout.session.async_payment_failed',
                            'payment_intent.payment_failed',
                        ],
                    ],
                    [
                        'title' => __('Recurring'),
                        'events' => [
                            'checkout.session.completed',
                            'customer.subscription.updated',
                            'customer.subscription.deleted',
                            'invoice.payment_succeeded',
                            'invoice.payment_failed',
                        ],
                    ],
                ],
            ],
        ]);
    }
}
