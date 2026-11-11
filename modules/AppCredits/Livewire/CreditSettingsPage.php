<?php

namespace Modules\AppCredits\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\AdminSettings\Support\OptionStore;

#[Title('Credits')]
class CreditSettingsPage extends Component
{
    protected OptionStore $options;

    public string $credit_topup_enabled = '1';
    public string $credit_pack_enabled = '1';
    public string $credit_pack_expiry_days = '0';
    public string $credit_pack_carry_over = '1';
    public string $credit_spend_priority = 'topup_first';
    public string $credit_low_balance_threshold = '10';
    public string $credit_negative_balance_allowed = '0';
    public string $credit_topup_requires_active_plan = '1';
    public string $credit_trial_user_can_buy_extra = '1';
    public string $credit_free_plan_user_can_buy_extra = '1';
    public string $credit_action_block_on_empty = '1';
    public string $credit_action_empty_message = '';
    public string $credit_action_empty_cta = 'buy_more';
    public string $credit_pack_visibility = 'user';

    public function boot(OptionStore $options): void
    {
        $this->options = $options;
    }

    public function mount(): void
    {
        foreach ($this->defaults() as $key => $default) {
            $this->{$key} = (string) $this->options->get($key, $default);
        }
    }

    public function save(): void
    {
        $validated = $this->validate($this->rules());

        foreach ($this->defaults() as $key => $default) {
            $this->options->set($key, $validated[$key] ?? $default);
        }

        $this->dispatch('settings-saved');
    }

    protected function defaults(): array
    {
        return [
            'credit_topup_enabled' => '1',
            'credit_pack_enabled' => '1',
            'credit_pack_expiry_days' => '0',
            'credit_pack_carry_over' => '1',
            'credit_spend_priority' => 'topup_first',
            'credit_low_balance_threshold' => '10',
            'credit_negative_balance_allowed' => '0',
            'credit_topup_requires_active_plan' => '1',
            'credit_trial_user_can_buy_extra' => '1',
            'credit_free_plan_user_can_buy_extra' => '1',
            'credit_action_block_on_empty' => '1',
            'credit_action_empty_message' => __('Not enough credits remaining for this action.'),
            'credit_action_empty_cta' => 'buy_more',
            'credit_pack_visibility' => 'user',
        ];
    }

    protected function rules(): array
    {
        return [
            'credit_topup_enabled' => ['required', 'in:0,1'],
            'credit_pack_enabled' => ['required', 'in:0,1'],
            'credit_pack_expiry_days' => ['required', 'integer', 'min:0', 'max:3650'],
            'credit_pack_carry_over' => ['required', 'in:0,1'],
            'credit_spend_priority' => ['required', 'in:topup_first,plan_first'],
            'credit_low_balance_threshold' => ['required', 'integer', 'min:0', 'max:1000000'],
            'credit_negative_balance_allowed' => ['required', 'in:0,1'],
            'credit_topup_requires_active_plan' => ['required', 'in:0,1'],
            'credit_trial_user_can_buy_extra' => ['required', 'in:0,1'],
            'credit_free_plan_user_can_buy_extra' => ['required', 'in:0,1'],
            'credit_action_block_on_empty' => ['required', 'in:0,1'],
            'credit_action_empty_message' => ['required', 'string', 'max:255'],
            'credit_action_empty_cta' => ['required', 'in:buy_more,upgrade'],
            'credit_pack_visibility' => ['required', 'in:guest,user'],
        ];
    }

    public function render(): View
    {
        return view('appcredits::admin.settings', [
            'toggleOptions' => [
                ['value' => '1', 'label' => 'Enable'],
                ['value' => '0', 'label' => 'Disable'],
            ],
            'spendPriorityOptions' => [
                ['value' => 'topup_first', 'label' => __('Use top-up credits first')],
                ['value' => 'plan_first', 'label' => __('Use plan credits first')],
            ],
            'ctaOptions' => [
                ['value' => 'buy_more', 'label' => __('Buy more credits')],
                ['value' => 'upgrade', 'label' => __('Upgrade plan')],
            ],
            'visibilityOptions' => [
                ['value' => 'user', 'label' => __('Logged-in users only')],
                ['value' => 'guest', 'label' => __('Guests can see packs')],
            ],
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Credits'),
        ]);
    }
}
