<?php

namespace Modules\AdminSettings\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\AdminSettings\Support\OptionStore;

#[Title('General settings')]
class General extends Component
{
    protected OptionStore $options;

    public string $website_title = '';

    public string $website_description = '';

    public string $website_keyword = '';

    public string $website_favicon = '';

    public string $website_logo_dark = '';

    public string $website_logo_light = '';

    public string $website_logo_brand_dark = '';

    public string $website_logo_brand_light = '';

    public string $format_date = 'M d, Y';

    public string $format_datetime = 'M d, Y H:i';

    public string $app_timezone = 'UTC';

    public string $contact_company_name = '';

    public string $contact_company_website = '';

    public string $contact_email = '';

    public string $contact_phone_number = '';

    public string $contact_working_hours = '';

    public string $contact_location = '';

    public function boot(OptionStore $options): void
    {
        $this->options = $options;
    }

    public function mount(): void
    {
        $this->website_title = $this->optionOrDefault('website_title', config('site.title', config('app.name', 'Stackposts')));
        $this->website_description = $this->optionOrDefault('website_description', config('site.description', ''));
        $this->website_keyword = $this->optionOrDefault('website_keyword', config('site.keywords', ''));
        $this->website_favicon = (string) $this->options->get('website_favicon', 'public/img/favicon.png');
        $this->website_logo_dark = (string) $this->options->get('website_logo_dark', 'public/img/logo-dark.png');
        $this->website_logo_light = (string) $this->options->get('website_logo_light', 'public/img/logo-light.png');
        $this->website_logo_brand_dark = (string) $this->options->get('website_logo_brand_dark', 'public/img/logo-brand-dark.png');
        $this->website_logo_brand_light = (string) $this->options->get('website_logo_brand_light', 'public/img/logo-brand-light.png');
        $this->format_date = (string) $this->options->get('format_date', 'M d, Y');
        $this->format_datetime = (string) $this->options->get('format_datetime', 'M d, Y H:i');
        $this->app_timezone = (string) $this->options->get('app_timezone', config('app.timezone', 'UTC'));
        $this->contact_company_name = (string) $this->options->get('contact_company_name', 'Your Company Name');
        $this->contact_company_website = (string) $this->options->get('contact_company_website', 'https://yourcompany.com');
        $this->contact_email = (string) $this->options->get('contact_email', 'support@yourcompany.com');
        $this->contact_phone_number = (string) $this->options->get('contact_phone_number', '+1 234 567 890');
        $this->contact_working_hours = (string) $this->options->get('contact_working_hours', 'Mon - Fri: 09:00 AM - 06:00 PM');
        $this->contact_location = (string) $this->options->get('contact_location', '123 Main Street, City, Country');
    }

    public function save()
    {
        $validated = $this->validate([
            'website_title' => ['required', 'string', 'max:255'],
            'website_description' => ['nullable', 'string', 'max:500'],
            'website_keyword' => ['nullable', 'string', 'max:500'],
            'website_favicon' => ['nullable', 'string', 'max:2048'],
            'website_logo_dark' => ['nullable', 'string', 'max:2048'],
            'website_logo_light' => ['nullable', 'string', 'max:2048'],
            'website_logo_brand_dark' => ['nullable', 'string', 'max:2048'],
            'website_logo_brand_light' => ['nullable', 'string', 'max:2048'],
            'format_date' => ['required', 'string', 'max:100'],
            'format_datetime' => ['required', 'string', 'max:100'],
            'app_timezone' => ['required', 'timezone:all', Rule::in(timezone_options())],
            'contact_company_name' => ['nullable', 'string', 'max:255'],
            'contact_company_website' => ['nullable', 'url', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone_number' => ['nullable', 'string', 'max:100'],
            'contact_working_hours' => ['nullable', 'string', 'max:255'],
            'contact_location' => ['nullable', 'string', 'max:255'],
        ]);

        foreach ($validated as $key => $value) {
            if (str_starts_with($key, 'website_logo') || $key === 'website_favicon') {
                $value = $this->normalizeMediaAssetValue((string) $value);
            }

            $this->options->set($key, $value);
        }

        $this->dispatch('settings-saved');
    }

    protected function normalizeMediaAssetValue(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $value)) {
            $host = parse_url($value, PHP_URL_HOST);
            $appHost = parse_url((string) config('app.url'), PHP_URL_HOST) ?: request()->getHost();

            if ($host !== null && $appHost !== null && strcasecmp($host, $appHost) !== 0) {
                return $value;
            }
        }

        $path = parse_url($value, PHP_URL_PATH) ?: $value;
        $query = parse_url($value, PHP_URL_QUERY);
        $basePath = trim((string) request()->getBaseUrl(), '/');

        $path = ltrim($path, '/');

        if ($basePath !== '' && str_starts_with($path, $basePath.'/')) {
            $path = substr($path, strlen($basePath) + 1);
        }

        foreach (['storage/app/public/', 'public/storage/', 'storage/'] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                $path = substr($path, strlen($prefix));
                break;
            }
        }

        return $query ? $path.'?'.$query : $path;
    }

    /**
     * @return array<int, array{value:string,label:string}>
     */
    protected function dateFormatOptions(): array
    {
        return [
            ['value' => 'M d, Y', 'label' => now()->format('M d, Y').' (M d, Y)'],
            ['value' => 'd/m/Y', 'label' => now()->format('d/m/Y').' (d/m/Y)'],
            ['value' => 'm/d/Y', 'label' => now()->format('m/d/Y').' (m/d/Y)'],
            ['value' => 'Y-m-d', 'label' => now()->format('Y-m-d').' (Y-m-d)'],
            ['value' => 'd M Y', 'label' => now()->format('d M Y').' (d M Y)'],
        ];
    }

    /**
     * @return array<int, array{value:string,label:string}>
     */
    protected function dateTimeFormatOptions(): array
    {
        return [
            ['value' => 'M d, Y H:i', 'label' => now()->format('M d, Y H:i').' (M d, Y H:i)'],
            ['value' => 'd/m/Y H:i', 'label' => now()->format('d/m/Y H:i').' (d/m/Y H:i)'],
            ['value' => 'm/d/Y h:i A', 'label' => now()->format('m/d/Y h:i A').' (m/d/Y h:i A)'],
            ['value' => 'Y-m-d H:i:s', 'label' => now()->format('Y-m-d H:i:s').' (Y-m-d H:i:s)'],
            ['value' => 'd M Y H:i', 'label' => now()->format('d M Y H:i').' (d M Y H:i)'],
        ];
    }

    protected function optionOrDefault(string $key, string $default = ''): string
    {
        $value = $this->options->get($key);

        if ($value === null) {
            return $default;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : $default;
    }

    public function render(): View
    {
        return view('adminsettings::livewire.general', [
            'dateFormatOptions' => $this->dateFormatOptions(),
            'dateTimeFormatOptions' => $this->dateTimeFormatOptions(),
            'timezoneOptions' => timezone_select_options(),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('General settings'),
        ]);
    }
}
