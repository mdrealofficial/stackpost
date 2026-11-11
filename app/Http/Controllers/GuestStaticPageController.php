<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\AdminSettings\Support\OptionStore;

class GuestStaticPageController extends Controller
{
    public function __construct(
        protected OptionStore $options,
    ) {}

    public function privacyPolicy(Request $request): View
    {
        return $this->renderPage(
            'privacy_policy',
            'guest.privacy-policy',
            __('Privacy Policy'),
        );
    }

    public function termsOfUse(Request $request): View
    {
        return $this->renderPage(
            'terms_of_use',
            'guest.terms-of-use',
            __('Terms of Use'),
        );
    }

    public function socialPages(Request $request): View
    {
        $links = collect([
            ['label' => 'Facebook', 'icon' => 'fa-brands fa-facebook-f', 'url' => (string) $this->options->get('social_facebook_url', '')],
            ['label' => 'Instagram', 'icon' => 'fa-brands fa-instagram', 'url' => (string) $this->options->get('social_instagram_url', '')],
            ['label' => 'LinkedIn', 'icon' => 'fa-brands fa-linkedin-in', 'url' => (string) $this->options->get('social_linkedin_url', '')],
            ['label' => 'X', 'icon' => 'fa-brands fa-x-twitter', 'url' => (string) $this->options->get('social_x_url', '')],
            ['label' => 'YouTube', 'icon' => 'fa-brands fa-youtube', 'url' => (string) $this->options->get('social_youtube_url', '')],
            ['label' => 'TikTok', 'icon' => 'fa-brands fa-tiktok', 'url' => (string) $this->options->get('social_tiktok_url', '')],
            ['label' => 'Telegram', 'icon' => 'fa-brands fa-telegram', 'url' => (string) $this->options->get('social_telegram_url', '')],
            ['label' => 'WhatsApp', 'icon' => 'fa-brands fa-whatsapp', 'url' => (string) $this->options->get('social_whatsapp_url', '')],
        ])->filter(fn (array $link): bool => trim($link['url']) !== '')->values();

        return view(theme_view('pages.static-page', 'guest'), [
            'pageTitle' => (string) $this->options->get('social_pages_title', __('Social Pages')),
            'pageContent' => (string) $this->options->get('social_pages_intro', ''),
            'routeName' => 'guest.social-pages',
            'socialLinks' => $links->all(),
            'pageType' => 'social',
        ]);
    }

    protected function renderPage(string $key, string $routeName, string $defaultTitle): View
    {
        return view(theme_view('pages.static-page', 'guest'), [
            'pageTitle' => (string) $this->options->get($key.'_title', $defaultTitle),
            'pageContent' => (string) $this->options->get($key.'_content', ''),
            'routeName' => $routeName,
            'socialLinks' => [],
            'pageType' => 'html',
        ]);
    }
}
