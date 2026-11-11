<?php

namespace Modules\AppAffiliate\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\AdminSettings\Support\OptionStore;

#[Title('Affiliate Settings')]
class AffiliateSettings extends Component
{
    protected OptionStore $options;

    public string $affiliate_status = '1';

    public string $affiliate_commission_percentage = '15';

    public string $affiliate_minimum_withdrawal = '50';

    public string $affiliate_types_of_payments = 'manual';

    public string $affiliate_onetime_commission_status = '1';

    public function boot(OptionStore $options): void
    {
        $this->options = $options;
    }

    public function mount(): void
    {
        $this->affiliate_status = (string) $this->options->get('affiliate_status', '1');
        $this->affiliate_commission_percentage = (string) $this->options->get('affiliate_commission_percentage', '15');
        $this->affiliate_minimum_withdrawal = (string) $this->options->get('affiliate_minimum_withdrawal', '50');
        $this->affiliate_types_of_payments = (string) $this->options->get('affiliate_types_of_payments', 'manual');
        $this->affiliate_onetime_commission_status = (string) $this->options->get('affiliate_onetime_commission_status', '1');
    }

    public function save(): void
    {
        $validated = $this->validate([
            'affiliate_status' => ['required', 'in:0,1'],
            'affiliate_commission_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'affiliate_minimum_withdrawal' => ['required', 'numeric', 'min:0'],
            'affiliate_types_of_payments' => ['nullable', 'string', 'max:255'],
            'affiliate_onetime_commission_status' => ['required', 'in:0,1'],
        ]);

        foreach ($validated as $key => $value) {
            $this->options->set($key, is_string($value) ? trim($value) : $value);
        }

        $this->dispatch('settings-saved');
    }

    public function render(): View
    {
        return view('appaffiliate::admin.settings')
            ->layout(theme_view('layouts.app', 'app'), [
                'title' => __('Affiliate Settings'),
            ]);
    }
}
