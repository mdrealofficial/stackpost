<?php

namespace Modules\PaymentPayU\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AdminSettings\Support\OptionStore;
use Modules\AppPayments\Support\PaymentGatewayDefinition;
use Modules\AppPayments\Support\PaymentGatewaySettingsRegistry;
use Modules\AppPayments\Support\PaymentManager;
use Modules\PaymentPayU\Services\PayUPaymentGateway;

class PaymentPayUServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'paymentpayu');
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');

        app(PaymentManager::class)->register(
            new PaymentGatewayDefinition(
                key: 'payu',
                title: 'PayU',
                type: 'one_time',
                description: 'Pay with PayU hosted checkout for card and UPI payments.',
                icon: 'fa-light fa-credit-card',
                display: [
                    'logo' => asset('modules/PaymentPayU/Public/logos/payu.png'),
                    'button_label' => 'Pay with PayU',
                    'badge' => 'INR only',
                    'priority_label' => 'Hosted checkout',
                    'checkout_note' => 'PayU collects payment on its hosted form and returns to the app after the payment is verified.',
                    'checkout_hint' => 'This gateway is configured for INR only in this module.',
                    'theme_styles' => [
                        'border' => 'rgba(45, 125, 92, 0.18)',
                        'surface' => 'rgba(45, 125, 92, 0.03)',
                        'badge_border' => 'rgba(45, 125, 92, 0.18)',
                        'badge_bg' => 'rgba(45, 125, 92, 0.08)',
                        'badge_text' => '#2d7d5c',
                        'priority_bg' => 'rgba(45, 125, 92, 0.08)',
                        'priority_text' => '#166534',
                    ],
                ],
                reportLabel: 'PayU',
                sort: 60,
                enabled: function (): bool {
                    $options = app(OptionStore::class);

                    return (bool) $options->get('payu_status', 0)
                        && filled($options->get('payu_merchant_key'))
                        && filled($options->get('payu_salt'));
                }
            ),
            PayUPaymentGateway::class
        );

        app(PaymentGatewaySettingsRegistry::class)->register('payu', [
            'title' => 'PayU',
            'description' => 'Configure PayU credentials for hosted one-time payments.',
            'view' => 'paymentpayu::admin.settings',
            'order' => 40,
            'status_key' => 'payu_status',
            'state' => [
                'payu_status' => '0',
                'payu_environment' => '0',
                'payu_merchant_key' => '',
                'payu_salt' => '',
            ],
            'rules' => [
                'state.payu_status' => ['required', 'in:0,1'],
                'state.payu_environment' => ['required', 'in:0,1'],
                'state.payu_merchant_key' => ['nullable', 'string', 'max:255', 'required_if:state.payu_status,1'],
                'state.payu_salt' => ['nullable', 'string', 'max:255', 'required_if:state.payu_status,1'],
            ],
            'data' => fn (): array => [
                'toggleOptions' => [
                    ['value' => '1', 'label' => 'Enable'],
                    ['value' => '0', 'label' => 'Disable'],
                ],
                'environmentOptions' => [
                    ['value' => '0', 'label' => __('Test')],
                    ['value' => '1', 'label' => __('Live')],
                ],
                'credentialTips' => [
                    ['title' => __('Status'), 'body' => __('Controls whether PayU checkout is available at payment time.')],
                    ['title' => __('Environment'), 'body' => __('Use the same environment for the merchant key and salt.')],
                    ['title' => __('INR only'), 'body' => __('PayU payment requests in this module are restricted to INR.')],
                ],
                'endpointItems' => [
                    ['label' => __('Webhook URL'), 'value' => route('payment.webhook', ['gateway' => 'payu'])],
                    ['label' => __('Success URL'), 'value' => route('payment.success', ['gateway' => 'payu'])],
                    ['label' => __('Cancel URL'), 'value' => route('payment.cancel', ['gateway' => 'payu'])],
                ],
                'requirementCards' => [
                    ['title' => __('Public HTTPS callback URL'), 'body' => __('PayU requires a public HTTPS URL for the success and cancel callbacks.')],
                    ['title' => __('Matching merchant key and salt'), 'body' => __('Use the merchant key and salt from the same PayU environment.')],
                    ['title' => __('Hosted checkout form'), 'body' => __('This module redirects to a hosted PayU form and verifies the callback hash before finalizing the payment.')],
                ],
                'eventGroups' => [
                    ['title' => __('One-time'), 'events' => ['payment.success', 'payment.failed']],
                ],
            ],
        ]);
    }
}
