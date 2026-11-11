<?php

namespace Modules\Payment2Checkout\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AdminSettings\Support\OptionStore;
use Modules\AppPayments\Support\PaymentGatewayDefinition;
use Modules\AppPayments\Support\PaymentGatewaySettingsRegistry;
use Modules\AppPayments\Support\PaymentManager;
use Modules\Payment2Checkout\Services\TwoCheckoutPaymentGateway;

class Payment2CheckoutServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'payment2checkout');
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');

        app(PaymentManager::class)->register(
            new PaymentGatewayDefinition(
                key: '2checkout',
                title: '2Checkout',
                type: 'one_time',
                description: 'Pay with 2Checkout hosted checkout for global card payments.',
                icon: 'fa-light fa-credit-card',
                display: [
                    'logo' => asset('modules/Payment2Checkout/Public/logos/2checkout.svg'),
                    'button_label' => 'Pay with 2Checkout',
                    'badge' => 'Cards',
                    'priority_label' => 'Global cards',
                    'checkout_note' => '2Checkout uses a hosted purchase form and returns to the app after the transaction is confirmed.',
                    'checkout_hint' => 'This module is configured for hosted checkout and callback verification.',
                    'theme_styles' => [
                        'border' => 'rgba(146, 64, 14, 0.18)',
                        'surface' => 'rgba(146, 64, 14, 0.03)',
                        'badge_border' => 'rgba(146, 64, 14, 0.18)',
                        'badge_bg' => 'rgba(146, 64, 14, 0.08)',
                        'badge_text' => '#92400e',
                        'priority_bg' => 'rgba(245, 158, 11, 0.08)',
                        'priority_text' => '#b45309',
                    ],
                ],
                reportLabel: '2Checkout',
                sort: 75,
                enabled: function (): bool {
                    $options = app(OptionStore::class);

                    return (bool) $options->get('2checkout_status', 0)
                        && filled($options->get('2checkout_seller_id'))
                        && filled($options->get('2checkout_secret_key'));
                }
            ),
            TwoCheckoutPaymentGateway::class
        );

        app(PaymentGatewaySettingsRegistry::class)->register('2checkout', [
            'title' => '2Checkout',
            'description' => 'Configure 2Checkout credentials for one-time billing.',
            'view' => 'payment2checkout::admin.settings',
            'order' => 60,
            'status_key' => '2checkout_status',
            'state' => [
                '2checkout_status' => '0',
                '2checkout_environment' => '0',
                '2checkout_seller_id' => '',
                '2checkout_secret_key' => '',
            ],
            'rules' => [
                'state.2checkout_status' => ['required', 'in:0,1'],
                'state.2checkout_environment' => ['required', 'in:0,1'],
                'state.2checkout_seller_id' => ['nullable', 'string', 'max:255', 'required_if:state.2checkout_status,1'],
                'state.2checkout_secret_key' => ['nullable', 'string', 'max:255', 'required_if:state.2checkout_status,1'],
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
                    ['title' => __('Status'), 'body' => __('Controls whether 2Checkout checkout is available at payment time.')],
                    ['title' => __('Environment'), 'body' => __('Use sandbox credentials for test mode and live credentials for production.')],
                    ['title' => __('Hosted checkout'), 'body' => __('2Checkout uses a hosted purchase form and validates the return payload before finalizing the payment.')],
                ],
                'endpointItems' => [
                    ['label' => __('Webhook URL'), 'value' => route('payment.webhook', ['gateway' => '2checkout'])],
                    ['label' => __('Success URL'), 'value' => route('payment.success', ['gateway' => '2checkout'])],
                    ['label' => __('Cancel URL'), 'value' => route('payment.cancel', ['gateway' => '2checkout'])],
                ],
                'requirementCards' => [
                    ['title' => __('Public HTTPS callback URL'), 'body' => __('2Checkout must be able to return to a public HTTPS URL after the shopper completes checkout.')],
                    ['title' => __('Seller ID and secret key'), 'body' => __('Use the seller ID and secret key from the same 2Checkout account and environment.')],
                    ['title' => __('Hosted purchase form'), 'body' => __('This module prepares a hosted 2Checkout purchase form and verifies the response on return.')],
                ],
                'eventGroups' => [
                    ['title' => __('One-time'), 'events' => ['SALE_APPROVED', 'SALE_PENDING', 'SALE_REFUSED']],
                ],
            ],
        ]);
    }
}
