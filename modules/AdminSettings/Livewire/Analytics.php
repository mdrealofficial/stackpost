<?php

namespace Modules\AdminSettings\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\AdminSettings\Support\OptionStore;

#[Title('Google Analytics')]
class Analytics extends Component
{
    protected OptionStore $options;

    public string $google_analytics_status = '0';

    public string $google_analytics_measurement_id = '';

    public string $google_analytics_track_guest = '1';

    public string $google_analytics_track_app = '0';

    public function boot(OptionStore $options): void
    {
        $this->options = $options;
    }

    public function mount(): void
    {
        $this->google_analytics_status = (string) $this->options->get('google_analytics_status', '0');
        $this->google_analytics_measurement_id = (string) $this->options->get('google_analytics_measurement_id', '');
        $this->google_analytics_track_guest = (string) $this->options->get('google_analytics_track_guest', '1');
        $this->google_analytics_track_app = (string) $this->options->get('google_analytics_track_app', '0');
    }

    public function save(): void
    {
        $validated = $this->validate([
            'google_analytics_status' => ['required', 'in:0,1'],
            'google_analytics_measurement_id' => ['nullable', 'string', 'max:50', 'regex:/^G-[A-Z0-9]+$/'],
            'google_analytics_track_guest' => ['required', 'in:0,1'],
            'google_analytics_track_app' => ['required', 'in:0,1'],
        ], [
            'google_analytics_measurement_id.regex' => __('The measurement ID must look like G-XXXXXXXXXX.'),
        ]);

        if (($validated['google_analytics_status'] ?? '0') === '1' && trim((string) ($validated['google_analytics_measurement_id'] ?? '')) === '') {
            $this->addError('google_analytics_measurement_id', __('The measurement ID is required when Google Analytics is enabled.'));

            return;
        }

        foreach ($validated as $key => $value) {
            $this->options->set($key, $value);
        }

        $this->dispatch('settings-saved');
    }

    public function render(): View
    {
        return view('adminsettings::livewire.analytics')
            ->layout(theme_view('layouts.app', 'app'), [
                'title' => __('Google Analytics'),
            ]);
    }
}
