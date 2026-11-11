<?php

namespace Modules\AppUrlShorteners\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\AdminSettings\Support\OptionStore;
use Modules\AppUrlShorteners\Support\UrlShortenerService;

#[Title('URL Shorteners')]
class UrlShorteners extends Component
{
    protected OptionStore $options;

    protected UrlShortenerService $shorteners;

    public string $url_shorteners_platform = '0';

    public string $bitly_status = '0';

    public string $bitly_api_key = '';

    public string $tinyurl_status = '0';

    public string $tinyurl_api_key = '';

    public string $rebrandly_status = '0';

    public string $rebrandly_api_key = '';

    public string $rebrandly_domain = 'rebrand.ly';

    public string $shortio_status = '0';

    public string $shortio_api_key = '';

    public string $shortio_domain = '';

    public function boot(OptionStore $options, UrlShortenerService $shorteners): void
    {
        $this->options = $options;
        $this->shorteners = $shorteners;
    }

    public function mount(): void
    {
        foreach ($this->fields() as $field => $default) {
            $this->{$field} = (string) $this->options->get($field, $default);
        }
    }

    public function save(): void
    {
        $validated = $this->validate([
            'url_shorteners_platform' => ['required', 'in:0,bitly,tinyurl,rebrandly,shortio'],
            'bitly_status' => ['required', 'in:0,1'],
            'bitly_api_key' => ['nullable', 'string', 'max:255'],
            'tinyurl_status' => ['required', 'in:0,1'],
            'tinyurl_api_key' => ['nullable', 'string', 'max:255'],
            'rebrandly_status' => ['required', 'in:0,1'],
            'rebrandly_api_key' => ['nullable', 'string', 'max:255'],
            'rebrandly_domain' => ['nullable', 'string', 'max:255'],
            'shortio_status' => ['required', 'in:0,1'],
            'shortio_api_key' => ['nullable', 'string', 'max:255'],
            'shortio_domain' => ['nullable', 'string', 'max:255'],
        ]);

        foreach ($validated as $key => $value) {
            $this->options->set($key, $value);
        }

        $this->dispatch('settings-saved');
    }

    public function render(): View
    {
        return view('appurlshorteners::admin.settings', [
            'platforms' => $this->shorteners->platforms(),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('URL Shorteners'),
        ]);
    }

    protected function fields(): array
    {
        return [
            'url_shorteners_platform' => '0',
            'bitly_status' => '0',
            'bitly_api_key' => '',
            'tinyurl_status' => '0',
            'tinyurl_api_key' => '',
            'rebrandly_status' => '0',
            'rebrandly_api_key' => '',
            'rebrandly_domain' => 'rebrand.ly',
            'shortio_status' => '0',
            'shortio_api_key' => '',
            'shortio_domain' => '',
        ];
    }
}
