<?php

namespace Modules\AdminSettings\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\AdminSettings\Support\OptionStore;

#[Title('Embed Code')]
class EmbedCode extends Component
{
    protected OptionStore $options;

    public string $embed_code_status = '0';

    public string $embed_code_guest_head = '';

    public string $embed_code_guest_body_end = '';

    public string $embed_code_app_head = '';

    public string $embed_code_app_body_end = '';

    public function boot(OptionStore $options): void
    {
        $this->options = $options;
    }

    public function mount(): void
    {
        $this->embed_code_status = (string) $this->options->get('embed_code_status', '0');
        $this->embed_code_guest_head = (string) $this->options->get('embed_code_guest_head', '');
        $this->embed_code_guest_body_end = (string) $this->options->get('embed_code_guest_body_end', '');
        $this->embed_code_app_head = (string) $this->options->get('embed_code_app_head', '');
        $this->embed_code_app_body_end = (string) $this->options->get('embed_code_app_body_end', '');
    }

    public function save(): void
    {
        $validated = $this->validate([
            'embed_code_status' => ['required', 'in:0,1'],
            'embed_code_guest_head' => ['nullable', 'string'],
            'embed_code_guest_body_end' => ['nullable', 'string'],
            'embed_code_app_head' => ['nullable', 'string'],
            'embed_code_app_body_end' => ['nullable', 'string'],
        ]);

        foreach ($validated as $key => $value) {
            $this->options->set($key, $value);
        }

        $this->dispatch('settings-saved');
    }

    public function render(): View
    {
        return view('adminsettings::livewire.embed-code')
            ->layout(theme_view('layouts.app', 'app'), [
                'title' => __('Embed Code'),
            ]);
    }
}
