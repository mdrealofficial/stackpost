<?php

namespace Modules\AdminMarketplace\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\AdminSettings\Support\OptionStore;

#[Title('Marketplace Settings')]
class MarketplaceSettings extends Component
{
    protected OptionStore $options;

    public string $marketplace_auto_update_status = '0';

    public function boot(OptionStore $options): void
    {
        $this->options = $options;
    }

    public function mount(): void
    {
        $this->marketplace_auto_update_status = (string) $this->options->get('marketplace_auto_update_status', '0');
    }

    public function save(): void
    {
        $validated = $this->validate([
            'marketplace_auto_update_status' => ['required', 'in:0,1'],
        ]);

        $this->options->set('marketplace_auto_update_status', $validated['marketplace_auto_update_status']);

        $this->dispatch('settings-saved');
    }

    public function render(): View
    {
        $artisan = config('app.demo_mode') ? 'artisan' : str_replace('\\', '/', base_path('artisan'));

        return view('adminmarketplace::settings', [
            'toggleOptions' => [
                ['value' => '1', 'label' => __('Enable'), 'description' => __('Automatically install newer versions for marketplace purchase packages during scheduled runs.')],
                ['value' => '0', 'label' => __('Disable'), 'description' => __('Keep update checks manual and require an administrator to trigger each upgrade.')],
            ],
            'schedulerCommand' => sprintf('* * * * * php "%s" schedule:run', $artisan),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Marketplace Settings'),
        ]);
    }
}
