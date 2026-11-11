<?php

namespace Modules\PaymentPaystack\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AdminSettings\Support\OptionStore;
use Modules\AppPayments\Support\PaymentGatewayDefinition;
use Modules\AppPayments\Support\PaymentGatewaySettingsRegistry;
use Modules\AppPayments\Support\PaymentManager;
use Modules\PaymentPaystack\Services\PaystackPaymentGateway;

class PaymentPaystackServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'paymentpaystack');

        app(PaymentManager::class)->register(
            new PaymentGatewayDefinition(
                key: 'paystack',
                title: 'Paystack',
                type: 'one_time',
                description: 'Pay with Paystack card checkout.',
                icon: 'fa-brands fa-paystack',
                display: [
                    'logo' => asset('modules/PaymentPaystack/Public/logos/paystack.png'),
                    'button_label' => 'Pay with Paystack',
                    'badge' => 'Card',
                    'priority_label' => 'Instant approval',
                    'checkout_note' => 'Paystack hosts the card payment step and returns to your callback URL after payment.',
                    'checkout_hint' => 'Well suited for card payments in Nigeria and supported markets.',
                    'theme_styles' => [
                        'border' => 'rgba(14, 165, 233, 0.18)',
                        'surface' => 'rgba(14, 165, 233, 0.03)',
                        'badge_border' => 'rgba(14, 165, 233, 0.18)',
                        'badge_bg' => 'rgba(14, 165, 233, 0.08)',
                        'badge_text' => '#0284c7',
                        'priority_bg' => 'rgba(14, 165, 233, 0.08)',
                        'priority_text' => '#0369a1',
                    ],
                ],
                reportLabel: 'Paystack',
                sort: 70,
                enabled: function (): bool {
                    $options = app(OptionStore::class);

                    return (bool) $options->get('paystack_status', 0)
                        && filled($options->get('paystack_public_key'))
                        && filled($options->get('paystack_secret_key'));
                }
            ),
            PaystackPaymentGateway::class
        );

        app(PaymentGatewaySettingsRegistry::class)->register('paystack', [
            'title' => 'Paystack',
            'description' => 'Configure Paystack credentials for one-time card billing.',
            'view' => 'paymentpaystack::admin.settings',
            'order' => 70,
            'status_key' => 'paystack_status',
            'state' => [
                'paystack_status' => '0',
                'paystack_environment' => '0',
                'paystack_public_key' => '',
                'paystack_secret_key' => '',
            ],
            'rules' => [
                'state.paystack_status' => ['required', 'in:0,1'],
                'state.paystack_environment' => ['required', 'in:0,1'],
                'state.paystack_public_key' => ['nullable', 'string', 'max:255', 'required_if:state.paystack_status,1'],
                'state.paystack_secret_key' => ['nullable', 'string', 'max:255', 'required_if:state.paystack_status,1'],
            ],
            'data' => fn (): array => [
                'toggleOptions' => [
                    ['value' => '1', 'label' => 'Enable'],
                    ['value' => '0', 'label' => 'Disable'],
                ],
                'environmentOptions' => [
                    ['value' => '1', 'label' => __('Live')],
                    ['value' => '0', 'label' => __('Test')],
                ],
                'credentialTips' => [
                    ['title' => __('Status'), 'body' => __('Controls whether Paystack checkout is available at payment time.')],
                    ['title' => __('Environment'), 'body' => __('Use test credentials for sandbox-style testing or live keys for production.')],
                    ['title' => __('Verification'), 'body' => __('Paystack transactions are verified with the reference returned on callback.')],
                ],
                'endpointItems' => [
                    ['label' => __('Webhook URL'), 'value' => route('payment.webhook', ['gateway' => 'paystack'])],
                    ['label' => __('Success URL'), 'value' => route('payment.success', ['gateway' => 'paystack'])],
                    ['label' => __('Cancel URL'), 'value' => route('payment.cancel', ['gateway' => 'paystack'])],
                ],
                'requirementCards' => [
                    ['title' => __('Public callback URL'), 'body' => __('Paystack must be able to reach your webhook endpoint over HTTPS.')],
                    ['title' => __('Secret key required'), 'body' => __('The secret key is required to initialize and verify Paystack payments.')],
                    ['title' => __('Reference verification'), 'body' => __('The transaction reference must be verified server-side before finalizing the order.')],
                ],
            ],
        ]);
    }
}
