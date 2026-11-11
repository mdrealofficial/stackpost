<?php

namespace Modules\AdminSettings\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\AdminSettings\Support\OptionStore;

#[Title('Static pages')]
class StaticPages extends Component
{
    protected OptionStore $options;

    protected const SOCIAL_NETWORKS = [
        'facebook' => ['label' => 'Facebook', 'icon' => 'fa-brands fa-facebook-f'],
        'instagram' => ['label' => 'Instagram', 'icon' => 'fa-brands fa-instagram'],
        'linkedin' => ['label' => 'LinkedIn', 'icon' => 'fa-brands fa-linkedin-in'],
        'x' => ['label' => 'X', 'icon' => 'fa-brands fa-x-twitter'],
        'youtube' => ['label' => 'YouTube', 'icon' => 'fa-brands fa-youtube'],
        'tiktok' => ['label' => 'TikTok', 'icon' => 'fa-brands fa-tiktok'],
        'telegram' => ['label' => 'Telegram', 'icon' => 'fa-brands fa-telegram'],
        'whatsapp' => ['label' => 'WhatsApp', 'icon' => 'fa-brands fa-whatsapp'],
    ];

    public string $privacy_policy_title = '';

    public string $privacy_policy_content = '';

    public string $terms_of_use_title = '';

    public string $terms_of_use_content = '';

    public string $social_pages_title = '';

    public string $social_pages_intro = '';

    public string $social_facebook_url = '';

    public string $social_instagram_url = '';

    public string $social_linkedin_url = '';

    public string $social_x_url = '';

    public string $social_youtube_url = '';

    public string $social_tiktok_url = '';

    public string $social_telegram_url = '';

    public string $social_whatsapp_url = '';

    public function boot(OptionStore $options): void
    {
        $this->options = $options;
    }

    public function mount(): void
    {
        foreach ($this->pageDefinitions() as $page) {
            $key = $page['key'];
            $this->{$key.'_title'} = (string) $this->options->get($key.'_title', $page['default_title']);
        }

        $this->privacy_policy_content = (string) $this->options->get('privacy_policy_content', '');
        $this->terms_of_use_content = (string) $this->options->get('terms_of_use_content', '');
        $this->social_pages_intro = (string) $this->options->get('social_pages_intro', '');

        foreach (array_keys(self::SOCIAL_NETWORKS) as $network) {
            $property = 'social_'.$network.'_url';
            $this->{$property} = (string) $this->options->get($property, '');
        }
    }

    public function save(): void
    {
        $rules = [
            'privacy_policy_title' => ['required', 'string', 'max:255'],
            'privacy_policy_content' => ['nullable', 'string'],
            'terms_of_use_title' => ['required', 'string', 'max:255'],
            'terms_of_use_content' => ['nullable', 'string'],
            'social_pages_title' => ['required', 'string', 'max:255'],
            'social_pages_intro' => ['nullable', 'string', 'max:2000'],
        ];

        foreach (array_keys(self::SOCIAL_NETWORKS) as $network) {
            $rules['social_'.$network.'_url'] = ['nullable', 'url', 'max:255'];
        }

        $validated = $this->validate($rules);

        foreach ($validated as $key => $value) {
            $this->options->set($key, $value);
        }

        $this->dispatch('settings-saved');
    }

    public function render(): View
    {
        return view('adminsettings::livewire.static-pages', [
            'pages' => $this->pageDefinitions(),
            'socialNetworks' => $this->socialNetworks(),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Static pages'),
        ]);
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function pageDefinitions(): array
    {
        return [
            [
                'key' => 'privacy_policy',
                'route_name' => 'guest.privacy-policy',
                'default_title' => __('Privacy Policy'),
                'description' => __('Public privacy page content. You can paste plain text or HTML here.'),
                'type' => 'html',
            ],
            [
                'key' => 'terms_of_use',
                'route_name' => 'guest.terms-of-use',
                'default_title' => __('Terms of Use'),
                'description' => __('Public terms page content. You can paste plain text or HTML here.'),
                'type' => 'html',
            ],
            [
                'key' => 'social_pages',
                'route_name' => 'guest.social-pages',
                'default_title' => __('Social Pages'),
                'description' => __('Add social profile links that should appear on the public social pages screen.'),
                'type' => 'social',
            ],
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function socialNetworks(): array
    {
        return collect(self::SOCIAL_NETWORKS)
            ->map(fn (array $network, string $key): array => [
                'key' => $key,
                'label' => $network['label'],
                'icon' => $network['icon'],
                'property' => 'social_'.$key.'_url',
            ])
            ->values()
            ->all();
    }
}
