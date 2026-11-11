<?php

namespace Modules\AppCredits\Support;

use Modules\AdminSettings\Support\OptionStore;

class CreditSettings
{
    public function __construct(
        protected OptionStore $options,
    ) {}

    public function topupEnabled(): bool
    {
        return $this->bool('credit_topup_enabled', true);
    }

    public function packEnabled(): bool
    {
        return $this->bool('credit_pack_enabled', true);
    }

    public function packExpiryDays(): int
    {
        return max(0, $this->int('credit_pack_expiry_days', 0));
    }

    public function packCarryOver(): bool
    {
        return $this->bool('credit_pack_carry_over', true);
    }

    public function spendPriority(): string
    {
        $value = strtolower((string) $this->options->get('credit_spend_priority', 'topup_first'));

        return in_array($value, ['topup_first', 'plan_first'], true) ? $value : 'topup_first';
    }

    public function lowBalanceThreshold(): int
    {
        return max(0, $this->int('credit_low_balance_threshold', 10));
    }

    public function negativeBalanceAllowed(): bool
    {
        return $this->bool('credit_negative_balance_allowed', false);
    }

    public function topupRequiresActivePlan(): bool
    {
        return $this->bool('credit_topup_requires_active_plan', true);
    }

    public function trialUserCanBuyExtra(): bool
    {
        return $this->bool('credit_trial_user_can_buy_extra', true);
    }

    public function freePlanUserCanBuyExtra(): bool
    {
        return $this->bool('credit_free_plan_user_can_buy_extra', true);
    }

    public function actionBlockOnEmpty(): bool
    {
        return $this->bool('credit_action_block_on_empty', true);
    }

    public function actionEmptyMessage(): string
    {
        return trim((string) $this->options->get('credit_action_empty_message', __('Not enough credits remaining for this action.')))
            ?: __('Not enough credits remaining for this action.');
    }

    public function actionEmptyCta(): string
    {
        $value = strtolower((string) $this->options->get('credit_action_empty_cta', 'buy_more'));

        return in_array($value, ['buy_more', 'upgrade'], true) ? $value : 'buy_more';
    }

    public function packVisibility(): string
    {
        $value = strtolower((string) $this->options->get('credit_pack_visibility', 'user'));

        return in_array($value, ['guest', 'user'], true) ? $value : 'user';
    }

    protected function bool(string $key, bool $default): bool
    {
        return (string) $this->options->get($key, $default ? '1' : '0') === '1';
    }

    protected function int(string $key, int $default): int
    {
        return (int) $this->options->get($key, (string) $default);
    }
}
