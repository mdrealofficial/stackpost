<?php

namespace Modules\AdminPaymentManualConfig\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\AdminSettings\Support\OptionStore;

#[Title('Payment Manual Configurations')]
class PaymentManualConfigSettings extends Component
{
    protected OptionStore $options;

    public string $payment_manual_status = '0';

    public string $payment_manual_prefix = 'PAY-';

    public string $payment_manual_info = 'Bank Info';

    public function boot(OptionStore $options): void
    {
        $this->options = $options;
    }

    public function mount(): void
    {
        foreach ($this->stateKeys() as $key => $default) {
            $this->{$key} = (string) $this->options->get($key, $default);
        }
    }

    public function save(): void
    {
        $validated = $this->validate([
            'payment_manual_status' => ['required', 'in:0,1'],
            'payment_manual_prefix' => ['required', 'string', 'max:50'],
            'payment_manual_info' => ['required', 'string', 'max:10000'],
        ]);

        foreach ($validated as $key => $value) {
            $this->options->set($key, $value);
        }

        $this->dispatch('settings-saved');
    }

    /**
     * @return array<string, string>
     */
    protected function stateKeys(): array
    {
        return [
            'payment_manual_status' => '0',
            'payment_manual_prefix' => 'PAY-',
            'payment_manual_info' => 'Bank Info',
        ];
    }

    /**
     * @return array<int, array{value:string,label:string}>
     */
    protected function toggleOptions(): array
    {
        return [
            ['value' => '1', 'label' => 'Enable'],
            ['value' => '0', 'label' => 'Disable'],
        ];
    }

    public function render(): View
    {
        return view('adminpaymentmanualconfig::index', [
            'toggleOptions' => $this->toggleOptions(),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Payment Manual Configurations'),
        ]);
    }
}
