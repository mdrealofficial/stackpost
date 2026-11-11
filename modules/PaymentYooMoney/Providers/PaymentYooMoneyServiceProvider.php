<?php

namespace Modules\PaymentYooMoney\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AdminSettings\Support\OptionStore;
use Modules\AppPayments\Support\PaymentGatewayDefinition;
use Modules\AppPayments\Support\PaymentGatewaySettingsRegistry;
use Modules\AppPayments\Support\PaymentManager;
use Modules\PaymentYooMoney\Services\YooMoneyPaymentGateway;

class PaymentYooMoneyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'paymentyoomoney');

        app(PaymentManager::class)->register(
            new PaymentGatewayDefinition(
                key: 'yoomoney',
                title: 'YooMoney',
                type: 'one_time',
                description: 'Pay with YooMoney wallet or bank card via YooKassa checkout.',
                icon: 'fa-light fa-wallet',
                display: [
                    'logo' => asset('modules/PaymentYooMoney/Public/logos/yoomoney.svg'),
                    'button_label' => 'Pay with YooMoney',
                    'badge' => 'RUB only',
                    'priority_label' => 'Wallet / Card',
                    'checkout_note' => 'YooMoney creates a redirect confirmation and verifies success server-side.',
                    'checkout_hint' => 'Supports YooMoney wallet and bank card payments in RUB.',
                    'theme_styles' => [
                        'border' => 'rgba(131, 51, 255, 0.18)',
                        'surface' => 'rgba(131, 51, 255, 0.03)',
                        'badge_border' => 'rgba(131, 51, 255, 0.18)',
                        'badge_bg' => 'rgba(131, 51, 255, 0.08)',
                        'badge_text' => '#8333ff',
                        'priority_bg' => 'rgba(131, 51, 255, 0.08)',
                        'priority_text' => '#5b21b6',
                    ],
                ],
                reportLabel: 'YooMoney',
                sort: 80,
                enabled: function (): bool {
                    $options = app(OptionStore::class);

                    return (bool) $options->get('yoomoney_status', 0)
                        && filled($options->get('yoomoney_shop_id'))
                        && filled($options->get('yoomoney_secret_key'));
                }
            ),
            YooMoneyPaymentGateway::class
        );

        app(PaymentGatewaySettingsRegistry::class)->register('yoomoney', [
            'title' => 'YooMoney',
            'description' => 'Configure YooMoney credentials for one-time billing.',
            'view' => 'paymentyoomoney::admin.settings',
            'order' => 50,
            'status_key' => 'yoomoney_status',
            'state' => [
                'yoomoney_status' => '0',
                'yoomoney_environment' => '0',
                'yoomoney_shop_id' => '',
                'yoomoney_secret_key' => '',
                'yoomoney_payment_method' => 'yoo_money',
            ],
            'rules' => [
                'state.yoomoney_status' => ['required', 'in:0,1'],
                'state.yoomoney_environment' => ['required', 'in:0,1'],
                'state.yoomoney_shop_id' => ['nullable', 'string', 'max:255', 'required_if:state.yoomoney_status,1'],
                'state.yoomoney_secret_key' => ['nullable', 'string', 'max:255', 'required_if:state.yoomoney_status,1'],
                'state.yoomoney_payment_method' => ['required', 'in:yoo_money,bank_card'],
            ],
            'data' => fn (): array => [
                'toggleOptions' => [
                    ['value' => '1', 'label' => 'Enable'],
                    ['value' => '0', 'label' => 'Disable'],
                ],
                'environmentOptions' => [
                    ['value' => '1', 'label' => __('Live')],
                    ['value' => '0', 'label' => __('Sandbox / Test')],
                ],
                'paymentMethodOptions' => [
                    ['value' => 'yoo_money', 'label' => __('YooMoney Wallet')],
                    ['value' => 'bank_card', 'label' => __('Bank Card')],
                ],
                'credentialTips' => [
                    ['title' => __('Status'), 'body' => __('Controls whether YooMoney checkout is available at payment time.')],
                    ['title' => __('Environment'), 'body' => __('Use matching live or test credentials for the selected environment.')],
                    ['title' => __('RUB only'), 'body' => __('YooMoney charge requests are restricted to RUB in this module.')],
                ],
                'endpointItems' => [
                    ['label' => __('Webhook URL'), 'value' => route('payment.webhook', ['gateway' => 'yoomoney'])],
                    ['label' => __('Success URL'), 'value' => route('payment.success', ['gateway' => 'yoomoney'])],
                    ['label' => __('Cancel URL'), 'value' => route('payment.cancel', ['gateway' => 'yoomoney'])],
                ],
                'requirementCards' => [
                    ['title' => __('Public HTTPS callback URL'), 'body' => __('YooMoney callbacks and webhooks require a public HTTPS URL. Use a tunnel such as ngrok or Cloudflare Tunnel for local testing.')],
                    ['title' => __('Test and live credentials must match'), 'body' => __('Use the Shop ID and Secret Key from the same YooMoney environment. Do not mix test and live credentials.')],
                    ['title' => __('RUB settlement'), 'body' => __('This module enforces RUB because YooMoney payment requests are normally issued in RUB.')],
                ],
                'eventGroups' => [
                    ['title' => __('One-time'), 'events' => ['payment.succeeded', 'payment.canceled']],
                ],
            ],
        ]);
    }
}
