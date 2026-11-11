<?php

namespace Modules\PaymentFlutterwave\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AdminSettings\Support\OptionStore;
use Modules\AppPayments\Support\PaymentGatewayDefinition;
use Modules\AppPayments\Support\PaymentGatewaySettingsRegistry;
use Modules\AppPayments\Support\PaymentManager;
use Modules\PaymentFlutterwave\Services\FlutterwavePaymentGateway;

class PaymentFlutterwaveServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'paymentflutterwave');

        app(PaymentManager::class)->register(
            new PaymentGatewayDefinition(
                key: 'flutterwave',
                title: 'Flutterwave',
                type: 'one_time',
                description: 'Pay securely with Flutterwave Checkout.',
                icon: 'fa-light fa-bolt',
                display: [
                    'logo' => asset('modules/PaymentFlutterwave/Public/logos/flutterwave.png'),
                    'button_label' => 'Pay with Flutterwave',
                    'badge' => 'Africa',
                    'priority_label' => 'Mobile money / Card',
                    'checkout_note' => 'Flutterwave provides a hosted payment page and confirms the transaction server-side.',
                    'checkout_hint' => 'Use this gateway for card and mobile-money checkout in supported markets.',
                    'theme_styles' => [
                        'border' => 'rgba(34, 197, 94, 0.18)',
                        'surface' => 'rgba(34, 197, 94, 0.03)',
                        'badge_border' => 'rgba(34, 197, 94, 0.18)',
                        'badge_bg' => 'rgba(34, 197, 94, 0.08)',
                        'badge_text' => '#16a34a',
                        'priority_bg' => 'rgba(34, 197, 94, 0.08)',
                        'priority_text' => '#15803d',
                    ],
                ],
                reportLabel: 'Flutterwave',
                sort: 90,
                enabled: function (): bool {
                    $options = app(OptionStore::class);

                    return (bool) $options->get('flutterwave_status', 0)
                        && filled($options->get('flutterwave_public_key'))
                        && filled($options->get('flutterwave_secret_key'));
                }
            ),
            FlutterwavePaymentGateway::class
        );

        app(PaymentGatewaySettingsRegistry::class)->register('flutterwave', [
            'title' => 'Flutterwave',
            'description' => 'Configure Flutterwave credentials for one-time checkout.',
            'view' => 'paymentflutterwave::admin.settings',
            'order' => 90,
            'status_key' => 'flutterwave_status',
            'state' => [
                'flutterwave_status' => '0',
                'flutterwave_environment' => '0',
                'flutterwave_public_key' => '',
                'flutterwave_secret_key' => '',
                'flutterwave_encryption_key' => '',
            ],
            'rules' => [
                'state.flutterwave_status' => ['required', 'in:0,1'],
                'state.flutterwave_environment' => ['required', 'in:0,1'],
                'state.flutterwave_public_key' => ['nullable', 'string', 'max:255', 'required_if:state.flutterwave_status,1'],
                'state.flutterwave_secret_key' => ['nullable', 'string', 'max:255', 'required_if:state.flutterwave_status,1'],
                'state.flutterwave_encryption_key' => ['nullable', 'string', 'max:255', 'required_if:state.flutterwave_status,1'],
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
                    ['title' => __('Status'), 'body' => __('Controls whether Flutterwave checkout is available at payment time.')],
                    ['title' => __('Environment'), 'body' => __('Use sandbox credentials during testing and live keys for production.')],
                    ['title' => __('Verification'), 'body' => __('Transactions are verified after redirect using the Flutterwave verify endpoint.')],
                ],
                'endpointItems' => [
                    ['label' => __('Webhook URL'), 'value' => route('payment.webhook', ['gateway' => 'flutterwave'])],
                    ['label' => __('Success URL'), 'value' => route('payment.success', ['gateway' => 'flutterwave'])],
                    ['label' => __('Cancel URL'), 'value' => route('payment.cancel', ['gateway' => 'flutterwave'])],
                ],
                'requirementCards' => [
                    ['title' => __('Public callback URL'), 'body' => __('Flutterwave must be able to reach your webhook endpoint over HTTPS.')],
                    ['title' => __('Key consistency'), 'body' => __('Public, secret, and encryption keys should all come from the same Flutterwave app.')],
                    ['title' => __('Server-side verification'), 'body' => __('Verify the transaction after redirect before applying the paid plan.')],
                ],
            ],
        ]);
    }
}
