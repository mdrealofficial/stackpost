<?php

namespace Modules\PaymentInstamojo\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AdminSettings\Support\OptionStore;
use Modules\AppPayments\Support\PaymentGatewayDefinition;
use Modules\AppPayments\Support\PaymentGatewaySettingsRegistry;
use Modules\AppPayments\Support\PaymentManager;
use Modules\PaymentInstamojo\Services\InstamojoPaymentGateway;

class PaymentInstamojoServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'paymentinstamojo');

        app(PaymentManager::class)->register(
            new PaymentGatewayDefinition(
                key: 'instamojo',
                title: 'Instamojo',
                type: 'one_time',
                description: 'Pay with Instamojo payment requests.',
                icon: 'fa-light fa-bag-shopping',
                display: [
                    'logo' => asset('modules/PaymentInstamojo/Public/logos/instamojo.png'),
                    'button_label' => 'Pay with Instamojo',
                    'badge' => 'India',
                    'priority_label' => 'Payment link',
                    'checkout_note' => 'Instamojo creates a payment request and redirects the customer to the hosted payment page.',
                    'checkout_hint' => 'Useful for payment links and hosted checkout in INR.',
                    'theme_styles' => [
                        'border' => 'rgba(236, 72, 153, 0.18)',
                        'surface' => 'rgba(236, 72, 153, 0.03)',
                        'badge_border' => 'rgba(236, 72, 153, 0.18)',
                        'badge_bg' => 'rgba(236, 72, 153, 0.08)',
                        'badge_text' => '#db2777',
                        'priority_bg' => 'rgba(236, 72, 153, 0.08)',
                        'priority_text' => '#be185d',
                    ],
                ],
                reportLabel: 'Instamojo',
                sort: 80,
                enabled: function (): bool {
                    $options = app(OptionStore::class);

                    return (bool) $options->get('instamojo_status', 0)
                        && filled($options->get('instamojo_client_id') ?: $options->get('instamojo_api_key'))
                        && filled($options->get('instamojo_client_secret') ?: $options->get('instamojo_auth_token'));
                }
            ),
            InstamojoPaymentGateway::class
        );

        app(PaymentGatewaySettingsRegistry::class)->register('instamojo', [
            'title' => 'Instamojo',
            'description' => 'Configure Instamojo credentials for one-time hosted checkout.',
            'view' => 'paymentinstamojo::admin.settings',
            'order' => 80,
            'status_key' => 'instamojo_status',
            'state' => [
                'instamojo_status' => '0',
                'instamojo_environment' => '0',
                'instamojo_client_id' => '',
                'instamojo_client_secret' => '',
                'instamojo_salt' => '',
            ],
            'rules' => [
                'state.instamojo_status' => ['required', 'in:0,1'],
                'state.instamojo_environment' => ['required', 'in:0,1'],
                'state.instamojo_client_id' => ['nullable', 'string', 'max:255', 'required_if:state.instamojo_status,1'],
                'state.instamojo_client_secret' => ['nullable', 'string', 'max:255', 'required_if:state.instamojo_status,1'],
                'state.instamojo_salt' => ['nullable', 'string', 'max:255'],
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
                    ['title' => __('Status'), 'body' => __('Controls whether Instamojo checkout is available at payment time.')],
                    ['title' => __('Environment'), 'body' => __('Use test credentials in sandbox and live credentials in production.')],
                    ['title' => __('Verification'), 'body' => __('The payment request is verified using the payment_id and payment_request_id returned by Instamojo.')],
                ],
                'endpointItems' => [
                    ['label' => __('Webhook URL'), 'value' => route('payment.webhook', ['gateway' => 'instamojo'])],
                    ['label' => __('Success URL'), 'value' => route('payment.success', ['gateway' => 'instamojo'])],
                    ['label' => __('Cancel URL'), 'value' => route('payment.cancel', ['gateway' => 'instamojo'])],
                ],
                'requirementCards' => [
                    ['title' => __('Public HTTPS callback URL'), 'body' => __('Instamojo callbacks should point to a public HTTPS URL.')],
                    ['title' => __('OAuth credentials'), 'body' => __('The client ID and client secret must belong to the same Instamojo application.')],
                    ['title' => __('Payment status verification'), 'body' => __('Verify the payment status before finalizing the order.')],
                ],
            ],
        ]);
    }
}
