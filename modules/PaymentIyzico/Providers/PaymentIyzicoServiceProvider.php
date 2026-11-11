<?php

namespace Modules\PaymentIyzico\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AdminSettings\Support\OptionStore;
use Modules\AppPayments\Support\PaymentGatewayDefinition;
use Modules\AppPayments\Support\PaymentGatewaySettingsRegistry;
use Modules\AppPayments\Support\PaymentManager;
use Modules\PaymentIyzico\Services\IyzicoPaymentGateway;

class PaymentIyzicoServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'paymentiyzico');
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');

        app(PaymentManager::class)->register(
            new PaymentGatewayDefinition(
                key: 'iyzico',
                title: 'iyzico',
                type: 'one_time',
                description: 'Pay with iyzico hosted checkout for Turkish customers.',
                icon: 'fa-light fa-credit-card',
                display: [
                    'logo' => asset('modules/PaymentIyzico/Public/logos/iyzico.svg'),
                    'button_label' => 'Pay with iyzico',
                    'badge' => 'TRY only',
                    'priority_label' => 'Checkout Form',
                    'checkout_note' => 'iyzico opens a hosted checkout form and returns the customer to the app after the payment is verified.',
                    'checkout_hint' => 'Use sandbox credentials with the sandbox endpoint and live credentials with the production endpoint.',
                    'theme_styles' => [
                        'border' => 'rgba(0, 132, 255, 0.18)',
                        'surface' => 'rgba(0, 132, 255, 0.03)',
                        'badge_border' => 'rgba(0, 132, 255, 0.18)',
                        'badge_bg' => 'rgba(0, 132, 255, 0.08)',
                        'badge_text' => '#0084ff',
                        'priority_bg' => 'rgba(0, 132, 255, 0.08)',
                        'priority_text' => '#1d4ed8',
                    ],
                ],
                reportLabel: 'iyzico',
                sort: 97,
                enabled: function (): bool {
                    $options = app(OptionStore::class);

                    return (bool) $options->get('iyzico_status', 0)
                        && filled($options->get('iyzico_api_key'))
                        && filled($options->get('iyzico_secret_key'));
                }
            ),
            IyzicoPaymentGateway::class
        );

        app(PaymentGatewaySettingsRegistry::class)->register('iyzico', [
            'title' => 'iyzico',
            'description' => 'Configure iyzico credentials for hosted one-time checkout.',
            'view' => 'paymentiyzico::admin.settings',
            'order' => 97,
            'status_key' => 'iyzico_status',
            'state' => [
                'iyzico_status' => '0',
                'iyzico_environment' => '0',
                'iyzico_api_key' => '',
                'iyzico_secret_key' => '',
                'iyzico_locale' => 'en',
            ],
            'rules' => [
                'state.iyzico_status' => ['required', 'in:0,1'],
                'state.iyzico_environment' => ['required', 'in:0,1'],
                'state.iyzico_api_key' => ['nullable', 'string', 'max:255', 'required_if:state.iyzico_status,1'],
                'state.iyzico_secret_key' => ['nullable', 'string', 'max:255', 'required_if:state.iyzico_status,1'],
                'state.iyzico_locale' => ['required', 'in:en,tr'],
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
                'localeOptions' => [
                    ['value' => 'en', 'label' => __('English')],
                    ['value' => 'tr', 'label' => __('Turkish')],
                ],
                'credentialTips' => [
                    ['title' => __('Status'), 'body' => __('Controls whether iyzico checkout is available at payment time.')],
                    ['title' => __('Environment'), 'body' => __('Use sandbox credentials with sandbox mode and live credentials with production mode.')],
                    ['title' => __('Locale'), 'body' => __('The checkout form and API responses can be switched between English and Turkish.')],
                ],
                'endpointItems' => [
                    ['label' => __('Success URL'), 'value' => route('payment.success', ['gateway' => 'iyzico'])],
                    ['label' => __('Webhook URL'), 'value' => route('payment.webhook', ['gateway' => 'iyzico'])],
                    ['label' => __('Cancel URL'), 'value' => route('payment.cancel', ['gateway' => 'iyzico'])],
                ],
                'requirementCards' => [
                    ['title' => __('Public HTTPS callback URL'), 'body' => __('iyzico requires the return URL to be publicly reachable over HTTPS in production.')],
                    ['title' => __('Matching API key and secret key'), 'body' => __('Use the key pair from the same iyzico account and environment.')],
                    ['title' => __('Checkout form token'), 'body' => __('The module initializes a checkout form token and retrieves the final payment details from iyzico before confirming the order.')],
                ],
                'eventGroups' => [
                    ['title' => __('Checkout form'), 'events' => ['payment.success', 'payment.failed']],
                    ['title' => __('Webhook'), 'events' => ['callback notification', 'status retrieval']],
                ],
            ],
        ]);
    }
}
