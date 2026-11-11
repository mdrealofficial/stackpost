<?php

namespace Modules\PaymentPayTR\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AdminSettings\Support\OptionStore;
use Modules\AppPayments\Support\PaymentGatewayDefinition;
use Modules\AppPayments\Support\PaymentGatewaySettingsRegistry;
use Modules\AppPayments\Support\PaymentManager;
use Modules\PaymentPayTR\Services\PayTRPaymentGateway;

class PaymentPayTRServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'paymentpaytr');
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');

        app(PaymentManager::class)->register(
            new PaymentGatewayDefinition(
                key: 'paytr',
                title: 'PayTR',
                type: 'one_time',
                description: 'Pay with PayTR hosted iFrame checkout for Turkish customers.',
                icon: 'fa-light fa-credit-card',
                display: [
                    'logo' => asset('modules/PaymentPayTR/Public/logos/paytr.svg'),
                    'button_label' => 'Pay with PayTR',
                    'badge' => 'TRY only',
                    'priority_label' => 'iFrame API',
                    'checkout_note' => 'PayTR opens a hosted iFrame checkout and posts the payment result back to the merchant callback URL.',
                    'checkout_hint' => 'Use public HTTPS callback URLs and confirm the PayTR hash in the callback before finalizing the order.',
                    'theme_styles' => [
                        'border' => 'rgba(186, 28, 28, 0.18)',
                        'surface' => 'rgba(186, 28, 28, 0.03)',
                        'badge_border' => 'rgba(186, 28, 28, 0.18)',
                        'badge_bg' => 'rgba(186, 28, 28, 0.08)',
                        'badge_text' => '#b91c1c',
                        'priority_bg' => 'rgba(249, 115, 22, 0.08)',
                        'priority_text' => '#c2410c',
                    ],
                ],
                reportLabel: 'PayTR',
                sort: 98,
                enabled: function (): bool {
                    $options = app(OptionStore::class);

                    return (bool) $options->get('paytr_status', 0)
                        && filled($options->get('paytr_merchant_id'))
                        && filled($options->get('paytr_merchant_key'))
                        && filled($options->get('paytr_merchant_salt'));
                }
            ),
            PayTRPaymentGateway::class
        );

        app(PaymentGatewaySettingsRegistry::class)->register('paytr', [
            'title' => 'PayTR',
            'description' => 'Configure PayTR credentials for hosted one-time checkout.',
            'view' => 'paymentpaytr::admin.settings',
            'order' => 98,
            'status_key' => 'paytr_status',
            'state' => [
                'paytr_status' => '0',
                'paytr_environment' => '0',
                'paytr_merchant_id' => '',
                'paytr_merchant_key' => '',
                'paytr_merchant_salt' => '',
                'paytr_lang' => 'en',
                'paytr_no_installment' => '0',
                'paytr_max_installment' => '0',
            ],
            'rules' => [
                'state.paytr_status' => ['required', 'in:0,1'],
                'state.paytr_environment' => ['required', 'in:0,1'],
                'state.paytr_merchant_id' => ['nullable', 'string', 'max:255', 'required_if:state.paytr_status,1'],
                'state.paytr_merchant_key' => ['nullable', 'string', 'max:255', 'required_if:state.paytr_status,1'],
                'state.paytr_merchant_salt' => ['nullable', 'string', 'max:255', 'required_if:state.paytr_status,1'],
                'state.paytr_lang' => ['required', 'in:en,tr'],
                'state.paytr_no_installment' => ['required', 'in:0,1'],
                'state.paytr_max_installment' => ['required', 'integer', 'min:0', 'max:12'],
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
                'languageOptions' => [
                    ['value' => 'en', 'label' => __('English')],
                    ['value' => 'tr', 'label' => __('Turkish')],
                ],
                'installmentOptions' => [
                    ['value' => '0', 'label' => __('Auto')],
                    ['value' => '1', 'label' => __('Disable installments')],
                ],
                'credentialTips' => [
                    ['title' => __('Status'), 'body' => __('Controls whether PayTR checkout is available at payment time.')],
                    ['title' => __('Environment'), 'body' => __('Use test mode for sandbox credentials and live mode for production credentials.')],
                    ['title' => __('Installments'), 'body' => __('PayTR can hide installments or cap the maximum number of installments displayed during checkout.')],
                ],
                'endpointItems' => [
                    ['label' => __('Webhook URL'), 'value' => route('payment.webhook', ['gateway' => 'paytr'])],
                    ['label' => __('Success URL'), 'value' => route('payment.success', ['gateway' => 'paytr'])],
                    ['label' => __('Cancel URL'), 'value' => route('payment.cancel', ['gateway' => 'paytr'])],
                ],
                'requirementCards' => [
                    ['title' => __('Public HTTPS callback URL'), 'body' => __('PayTR callback notifications must be reachable from the public internet and should not point to localhost in production.')],
                    ['title' => __('Callback hash verification'), 'body' => __('Respond with OK only after verifying the callback hash and order identifier.')],
                    ['title' => __('Hosted iFrame checkout'), 'body' => __('This module initializes a PayTR iframe token and renders the hosted checkout form on a separate page.')],
                ],
                'eventGroups' => [
                    ['title' => __('iFrame API'), 'events' => ['payment.success', 'payment.failed', 'callback notification']],
                ],
            ],
        ]);
    }
}
