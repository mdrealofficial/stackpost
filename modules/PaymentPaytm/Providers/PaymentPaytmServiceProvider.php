<?php

namespace Modules\PaymentPaytm\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AdminSettings\Support\OptionStore;
use Modules\AppPayments\Support\PaymentGatewayDefinition;
use Modules\AppPayments\Support\PaymentGatewaySettingsRegistry;
use Modules\AppPayments\Support\PaymentManager;
use Modules\PaymentPaytm\Services\PaytmPaymentGateway;

class PaymentPaytmServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'paymentpaytm');
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');

        app(PaymentManager::class)->register(
            new PaymentGatewayDefinition(
                key: 'paytm',
                title: 'Paytm',
                type: 'one_time',
                description: 'Pay with Paytm hosted checkout for UPI, wallet, and cards.',
                icon: 'fa-light fa-wallet',
                display: [
                    'logo' => asset('modules/PaymentPaytm/Public/logos/paytm.png'),
                    'button_label' => 'Pay with Paytm',
                    'badge' => 'INR only',
                    'priority_label' => 'CheckoutJS',
                    'checkout_note' => 'Paytm creates a transaction token and sends the customer to the hosted Paytm checkout page.',
                    'checkout_hint' => 'The module is configured for INR only and supports the Paytm staging or production environment.',
                    'theme_styles' => [
                        'border' => 'rgba(12, 74, 110, 0.18)',
                        'surface' => 'rgba(12, 74, 110, 0.03)',
                        'badge_border' => 'rgba(12, 74, 110, 0.18)',
                        'badge_bg' => 'rgba(12, 74, 110, 0.08)',
                        'badge_text' => '#0c4a6e',
                        'priority_bg' => 'rgba(14, 165, 233, 0.08)',
                        'priority_text' => '#0369a1',
                    ],
                ],
                reportLabel: 'Paytm',
                sort: 65,
                enabled: function (): bool {
                    $options = app(OptionStore::class);

                    return (bool) $options->get('paytm_status', 0)
                        && filled($options->get('paytm_merchant_id'))
                        && filled($options->get('paytm_merchant_key'));
                }
            ),
            PaytmPaymentGateway::class
        );

        app(PaymentGatewaySettingsRegistry::class)->register('paytm', [
            'title' => 'Paytm',
            'description' => 'Configure Paytm credentials for one-time billing.',
            'view' => 'paymentpaytm::admin.settings',
            'order' => 45,
            'status_key' => 'paytm_status',
            'state' => [
                'paytm_status' => '0',
                'paytm_environment' => '0',
                'paytm_merchant_id' => '',
                'paytm_merchant_key' => '',
                'paytm_website' => 'WEBSTAGING',
            ],
            'rules' => [
                'state.paytm_status' => ['required', 'in:0,1'],
                'state.paytm_environment' => ['required', 'in:0,1'],
                'state.paytm_merchant_id' => ['nullable', 'string', 'max:255', 'required_if:state.paytm_status,1'],
                'state.paytm_merchant_key' => ['nullable', 'string', 'max:255', 'required_if:state.paytm_status,1'],
                'state.paytm_website' => ['required', 'string', 'max:255'],
            ],
            'data' => fn (): array => [
                'toggleOptions' => [
                    ['value' => '1', 'label' => 'Enable'],
                    ['value' => '0', 'label' => 'Disable'],
                ],
                'environmentOptions' => [
                    ['value' => '0', 'label' => __('Staging')],
                    ['value' => '1', 'label' => __('Live')],
                ],
                'credentialTips' => [
                    ['title' => __('Status'), 'body' => __('Controls whether Paytm checkout is available at payment time.')],
                    ['title' => __('Environment'), 'body' => __('Use staging credentials with the staging environment and live credentials with the production environment.')],
                    ['title' => __('INR only'), 'body' => __('Paytm payment requests in this module are restricted to INR.')],
                ],
                'endpointItems' => [
                    ['label' => __('Webhook URL'), 'value' => route('payment.webhook', ['gateway' => 'paytm'])],
                    ['label' => __('Success URL'), 'value' => route('payment.success', ['gateway' => 'paytm'])],
                    ['label' => __('Cancel URL'), 'value' => route('payment.cancel', ['gateway' => 'paytm'])],
                ],
                'requirementCards' => [
                    ['title' => __('Public HTTPS callback URL'), 'body' => __('Paytm callbacks should be reachable by the gateway and not point to localhost in production.')],
                    ['title' => __('Merchant key must match'), 'body' => __('Use the same merchant ID and key pair for the selected environment.')],
                    ['title' => __('Hosted transaction token'), 'body' => __('This module creates a transaction token and sends the shopper to the Paytm hosted payment page.')],
                ],
                'eventGroups' => [
                    ['title' => __('One-time'), 'events' => ['TXN_SUCCESS', 'TXN_FAILURE', 'PENDING']],
                ],
            ],
        ]);
    }
}
