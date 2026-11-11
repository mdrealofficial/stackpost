<?php

namespace Modules\PaymentSslCommerz\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AdminSettings\Support\OptionStore;
use Modules\AppPayments\Support\PaymentGatewayDefinition;
use Modules\AppPayments\Support\PaymentGatewaySettingsRegistry;
use Modules\AppPayments\Support\PaymentManager;
use Modules\PaymentSslCommerz\Services\SslCommerzPaymentGateway;

class PaymentSslCommerzServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'paymentsslcommerz');

        app(PaymentManager::class)->register(
            new PaymentGatewayDefinition(
                key: 'sslcommerz',
                title: 'SSLCOMMERZ',
                type: 'one_time',
                description: 'Pay securely with SSLCOMMERZ checkout.',
                icon: 'fa-light fa-credit-card',
                display: [
                    'logo' => asset('modules/PaymentSslCommerz/Public/logos/sslcommerz.png'),
                    'button_label' => 'Pay with SSLCOMMERZ',
                    'badge' => 'Bangladesh',
                    'priority_label' => 'Bank / Card',
                    'checkout_note' => 'SSLCOMMERZ creates a hosted payment session and verifies the confirmation server-side.',
                    'checkout_hint' => 'Useful for Bangladesh payments with hosted card or bank checkout.',
                    'theme_styles' => [
                        'border' => 'rgba(249, 115, 22, 0.18)',
                        'surface' => 'rgba(249, 115, 22, 0.03)',
                        'badge_border' => 'rgba(249, 115, 22, 0.18)',
                        'badge_bg' => 'rgba(249, 115, 22, 0.08)',
                        'badge_text' => '#ea580c',
                        'priority_bg' => 'rgba(249, 115, 22, 0.08)',
                        'priority_text' => '#c2410c',
                    ],
                ],
                reportLabel: 'SSLCOMMERZ',
                sort: 60,
                enabled: function (): bool {
                    $options = app(OptionStore::class);

                    return (bool) $options->get('sslcommerz_status', 0)
                        && filled($options->get('sslcommerz_store_id'))
                        && filled($options->get('sslcommerz_store_password'));
                }
            ),
            SslCommerzPaymentGateway::class
        );

        app(PaymentGatewaySettingsRegistry::class)->register('sslcommerz', [
            'title' => 'SSLCOMMERZ',
            'description' => 'Configure SSLCOMMERZ credentials for one-time checkout.',
            'view' => 'paymentsslcommerz::admin.settings',
            'order' => 60,
            'status_key' => 'sslcommerz_status',
            'state' => [
                'sslcommerz_status' => '0',
                'sslcommerz_environment' => '0',
                'sslcommerz_store_id' => '',
                'sslcommerz_store_password' => '',
            ],
            'rules' => [
                'state.sslcommerz_status' => ['required', 'in:0,1'],
                'state.sslcommerz_environment' => ['required', 'in:0,1'],
                'state.sslcommerz_store_id' => ['nullable', 'string', 'max:255', 'required_if:state.sslcommerz_status,1'],
                'state.sslcommerz_store_password' => ['nullable', 'string', 'max:255', 'required_if:state.sslcommerz_status,1'],
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
                    ['title' => __('Status'), 'body' => __('Controls whether SSLCOMMERZ checkout is available at payment time.')],
                    ['title' => __('Environment'), 'body' => __('Use the sandbox Store ID and Password for test mode or live credentials for production.')],
                    ['title' => __('Validation'), 'body' => __('Payments are validated server-side using the SSLCOMMERZ validation endpoint.')],
                ],
                'endpointItems' => [
                    ['label' => __('Webhook URL'), 'value' => route('payment.webhook', ['gateway' => 'sslcommerz'])],
                    ['label' => __('Success URL'), 'value' => route('payment.success', ['gateway' => 'sslcommerz'])],
                    ['label' => __('Cancel URL'), 'value' => route('payment.cancel', ['gateway' => 'sslcommerz'])],
                ],
                'requirementCards' => [
                    ['title' => __('Public callback URL'), 'body' => __('Use a public HTTPS URL or tunnel for local testing because SSLCOMMERZ must reach your callback endpoint.')],
                    ['title' => __('Matched credentials'), 'body' => __('Store ID and Store Password must come from the same SSLCOMMERZ environment.')],
                    ['title' => __('Server validation'), 'body' => __('The payment confirmation should be validated with the SSLCOMMERZ validation API before finalizing the order.')],
                ],
            ],
        ]);
    }
}
