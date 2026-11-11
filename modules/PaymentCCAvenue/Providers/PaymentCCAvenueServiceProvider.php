<?php

namespace Modules\PaymentCCAvenue\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AdminSettings\Support\OptionStore;
use Modules\AppPayments\Support\PaymentGatewayDefinition;
use Modules\AppPayments\Support\PaymentGatewaySettingsRegistry;
use Modules\AppPayments\Support\PaymentManager;
use Modules\PaymentCCAvenue\Services\CCAvenuePaymentGateway;

class PaymentCCAvenueServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'paymentccavenue');
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');

        app(PaymentManager::class)->register(
            new PaymentGatewayDefinition(
                key: 'ccavenue',
                title: 'CCAvenue',
                type: 'one_time',
                description: 'Pay with CCAvenue hosted checkout for Indian payments.',
                icon: 'fa-light fa-indian-rupee-sign',
                display: [
                    'logo' => asset('modules/PaymentCCAvenue/Public/logos/ccavenue.png'),
                    'button_label' => 'Pay with CCAvenue',
                    'badge' => 'INR only',
                    'priority_label' => 'Hosted form',
                    'checkout_note' => 'CCAvenue encrypts the checkout payload and redirects the customer to its hosted payment page.',
                    'checkout_hint' => 'This gateway is configured for INR only and requires the merchant ID, access code, and working key.',
                    'theme_styles' => [
                        'border' => 'rgba(31, 41, 55, 0.18)',
                        'surface' => 'rgba(31, 41, 55, 0.03)',
                        'badge_border' => 'rgba(31, 41, 55, 0.18)',
                        'badge_bg' => 'rgba(31, 41, 55, 0.08)',
                        'badge_text' => '#111827',
                        'priority_bg' => 'rgba(31, 41, 55, 0.08)',
                        'priority_text' => '#374151',
                    ],
                ],
                reportLabel: 'CCAvenue',
                sort: 70,
                enabled: function (): bool {
                    $options = app(OptionStore::class);

                    return (bool) $options->get('ccavenue_status', 0)
                        && filled($options->get('ccavenue_merchant_id'))
                        && filled($options->get('ccavenue_access_code'))
                        && filled($options->get('ccavenue_working_key'));
                }
            ),
            CCAvenuePaymentGateway::class
        );

        app(PaymentGatewaySettingsRegistry::class)->register('ccavenue', [
            'title' => 'CCAvenue',
            'description' => 'Configure CCAvenue credentials for one-time billing.',
            'view' => 'paymentccavenue::admin.settings',
            'order' => 55,
            'status_key' => 'ccavenue_status',
            'state' => [
                'ccavenue_status' => '0',
                'ccavenue_environment' => '0',
                'ccavenue_merchant_id' => '',
                'ccavenue_access_code' => '',
                'ccavenue_working_key' => '',
            ],
            'rules' => [
                'state.ccavenue_status' => ['required', 'in:0,1'],
                'state.ccavenue_environment' => ['required', 'in:0,1'],
                'state.ccavenue_merchant_id' => ['nullable', 'string', 'max:255', 'required_if:state.ccavenue_status,1'],
                'state.ccavenue_access_code' => ['nullable', 'string', 'max:255', 'required_if:state.ccavenue_status,1'],
                'state.ccavenue_working_key' => ['nullable', 'string', 'max:255', 'required_if:state.ccavenue_status,1'],
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
                    ['title' => __('Status'), 'body' => __('Controls whether CCAvenue checkout is available at payment time.')],
                    ['title' => __('Environment'), 'body' => __('Use the sandbox or live merchant details consistently across all fields.')],
                    ['title' => __('INR only'), 'body' => __('CCAvenue payment requests in this module are restricted to INR.')],
                ],
                'endpointItems' => [
                    ['label' => __('Webhook URL'), 'value' => route('payment.webhook', ['gateway' => 'ccavenue'])],
                    ['label' => __('Success URL'), 'value' => route('payment.success', ['gateway' => 'ccavenue'])],
                    ['label' => __('Cancel URL'), 'value' => route('payment.cancel', ['gateway' => 'ccavenue'])],
                ],
                'requirementCards' => [
                    ['title' => __('Public HTTPS callback URL'), 'body' => __('CCAvenue must be able to return to the app using a public HTTPS URL.')],
                    ['title' => __('Same environment keys'), 'body' => __('Merchant ID, access code, and working key must all be from the same CCAvenue environment.')],
                    ['title' => __('Encrypted checkout form'), 'body' => __('This module encrypts the checkout payload before redirecting to CCAvenue.')],
                ],
                'eventGroups' => [
                    ['title' => __('One-time'), 'events' => ['Success', 'Failure', 'Cancelled']],
                ],
            ],
        ]);
    }
}
