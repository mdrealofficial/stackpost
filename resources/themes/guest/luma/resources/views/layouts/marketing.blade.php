<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ current_locale_direction() }}">
<head>
    @include(theme_view('partials.head', 'guest'), ['title' => $pageTitle ?? null, 'pageDescription' => $pageDescription ?? null, 'pageImage' => $pageImage ?? null])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>

    @include(theme_view('partials.stylesheet', 'guest'), ['stylesheet' => 'layout'])

    
    @stack('styles')
</head>

@php
    $languages = available_languages();
    $options = app(\Modules\AdminSettings\Support\OptionStore::class);
    $settingsTitle = trim((string) $options->get('website_title', ''));
    $siteTitle = $settingsTitle !== '' ? $settingsTitle : 'Your Company Name';
    $siteLogoDark = trim((string) ($options->get('website_logo_brand_dark') ?: $options->get('website_logo_dark') ?: $options->get('website_logo') ?: 'public/img/logo-brand-dark.png'));
    $siteLogoLight = trim((string) ($options->get('website_logo_brand_light') ?: $options->get('website_logo_light') ?: $options->get('website_logo') ?: 'public/img/logo-brand-light.png'));
    $siteLogoDarkUrl = url($siteLogoDark);
    $siteLogoLightUrl = url($siteLogoLight);
    $siteDescription = trim((string) $options->get('website_description', ''));
    $siteDescription = $siteDescription !== '' ? $siteDescription : __('Plan, create, publish, and analyze social content from one connected workspace.');
    $signupEnabled = (string) $options->get('auth_signup_page_status', '1') === '1';
    $contactEmail = trim((string) $options->get('contact_email', ''));
    $activeLanguage = $languages->firstWhere('code', app()->getLocale()) ?? $languages->first();
    $navItems = [
        ['label' => __('Home'), 'href' => route('home'), 'active' => request()->routeIs('home')],
        ['label' => __('Features'), 'href' => route('home').'#features', 'active' => false],
        ['label' => __('Pricing'), 'href' => route('guest.pricing'), 'active' => request()->routeIs('guest.pricing')],
        ['label' => __('Blog'), 'href' => route('guest.blogs'), 'active' => request()->routeIs('guest.blogs') || request()->routeIs('guest.blog*')],
        ['label' => __('FAQs'), 'href' => route('guest.faqs'), 'active' => request()->routeIs('guest.faqs')],
        ['label' => __('Contact'), 'href' => route('guest.contact'), 'active' => request()->routeIs('guest.contact')],
    ];
    $socialLinks = collect([
        ['icon' => 'fa-brands fa-facebook-f', 'label' => 'Facebook', 'url' => trim((string) $options->get('social_page_facebook', ''))],
        ['icon' => 'fa-brands fa-instagram', 'label' => 'Instagram', 'url' => trim((string) $options->get('social_page_instagram', ''))],
        ['icon' => 'fa-brands fa-x-twitter', 'label' => 'X', 'url' => trim((string) $options->get('social_page_x', ''))],
        ['icon' => 'fa-brands fa-youtube', 'label' => 'YouTube', 'url' => trim((string) $options->get('social_page_youtube', ''))],
        ['icon' => 'fa-brands fa-tiktok', 'label' => 'TikTok', 'url' => trim((string) $options->get('social_page_tiktok', ''))],
        ['icon' => 'fa-brands fa-pinterest-p', 'label' => 'Pinterest', 'url' => trim((string) $options->get('social_page_pinterest', ''))],
    ])->filter(fn (array $item) => $item['url'] !== '');
@endphp

<body class="lm-site">
    <a href="#main-content" class="lm-skip-link">{{ __('Skip to content') }}</a>
    <div x-data="{ mobileOpen: false, languageOpen: false, scrolled: false, resolved: window.themeMode?.getResolved?.() || 'light', syncTheme(state = null) { this.resolved = state?.resolved || window.themeMode?.getResolved?.() || 'light'; }, toggleTheme() { this.syncTheme(window.themeMode?.toggle?.()); } }" x-init="scrolled = window.scrollY > 8; syncTheme(); window.addEventListener('scroll', () => scrolled = window.scrollY > 8, { passive: true }); window.addEventListener('theme-mode-changed', event => syncTheme(event.detail));">
        <header class="lm-header" x-bind:class="scrolled ? 'is-scrolled' : ''">
            <div class="lm-container">
                <div class="lm-header-row">
                    <a href="{{ route('home') }}" class="lm-brand" aria-label="{{ $siteTitle }}">
                        <img class="theme-logo-dark" src="{{ $siteLogoDarkUrl }}" alt="{{ $siteTitle }}">
                        <img class="theme-logo-light" src="{{ $siteLogoLightUrl }}" alt="{{ $siteTitle }}">
                    </a>

                    <nav class="lm-nav" aria-label="{{ __('Main navigation') }}">
                        @foreach ($navItems as $item)
                            <a href="{{ $item['href'] }}" class="lm-nav-link {{ $item['active'] ? 'is-active' : '' }}">{{ $item['label'] }}</a>
                        @endforeach
                    </nav>

                    <div class="lm-actions">
                        <button type="button" class="lm-icon-button" x-on:click="toggleTheme()" x-bind:aria-label="resolved === 'dark' ? @js(__('Switch to light mode')) : @js(__('Switch to dark mode'))">
                            <i class="fa-light" x-bind:class="resolved === 'dark' ? 'fa-sun-bright' : 'fa-moon-stars'"></i>
                        </button>

                        @if ($languages->isNotEmpty())
                            <div class="lm-language">
                                <button type="button" class="lm-language-button" x-on:click="languageOpen = !languageOpen" x-on:click.outside="languageOpen = false" aria-haspopup="menu" aria-label="{{ __('Choose language') }}" x-bind:aria-expanded="languageOpen">
                                    <span class="{{ language_flag_class($activeLanguage ?? app()->getLocale()) }}"></span>
                                    <i class="fa-light fa-chevron-down text-[10px]"></i>
                                </button>
                                <div x-cloak x-show="languageOpen" x-transition.origin.top.right class="lm-language-menu" role="menu">
                                    @foreach ($languages as $language)
                                        @php $isActiveLanguage = app()->getLocale() === $language->code; @endphp
                                        <a href="{{ route('language.switch', $language->code) }}" class="lm-language-option {{ $isActiveLanguage ? 'is-active' : '' }}" role="menuitem">
                                            <span class="{{ language_flag_class($language) }}"></span>
                                            <span class="lm-language-name">{{ $language->name ?? strtoupper((string) $language->code) }}</span>
                                            @if ($isActiveLanguage)<i class="fa-light fa-check"></i>@endif
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @auth
                            <a href="{{ route('portal.dashboard') }}" class="lm-cta"><i class="fa-light fa-grid-2"></i>{{ __('Dashboard') }}</a>
                        @else
                            <a href="{{ route('login') }}" class="lm-login">{{ __('Log in') }}</a>
                            <a href="{{ $signupEnabled ? route('register') : route('login') }}" class="lm-cta">{{ $signupEnabled ? __('Get started') : __('Open workspace') }}</a>
                        @endauth
                    </div>

                    <button type="button" class="lm-mobile-toggle" x-on:click="mobileOpen = !mobileOpen" x-bind:aria-expanded="mobileOpen" aria-label="{{ __('Toggle navigation') }}">
                        <i class="fa-light" x-bind:class="mobileOpen ? 'fa-xmark' : 'fa-bars'"></i>
                    </button>
                </div>

                <div x-cloak x-show="mobileOpen" x-transition class="lm-mobile-panel">
                    <nav class="lm-mobile-nav" aria-label="{{ __('Mobile navigation') }}">
                        @foreach ($navItems as $item)
                            <a href="{{ $item['href'] }}" class="lm-nav-link {{ $item['active'] ? 'is-active' : '' }}" x-on:click="mobileOpen = false">
                                <span>{{ $item['label'] }}</span><i class="fa-light fa-arrow-up-right text-[11px]"></i>
                            </a>
                        @endforeach
                    </nav>
                    <div class="lm-mobile-actions">
                        @auth
                            <a href="{{ route('portal.dashboard') }}" class="lm-cta">{{ __('Dashboard') }}</a>
                        @else
                            <a href="{{ route('login') }}" class="lm-login">{{ __('Log in') }}</a>
                            <a href="{{ $signupEnabled ? route('register') : route('login') }}" class="lm-cta">{{ $signupEnabled ? __('Get started') : __('Open workspace') }}</a>
                        @endauth
                    </div>
                    <div class="lm-mobile-tools">
                        <button type="button" class="lm-language-button" x-on:click="toggleTheme()">
                            <i class="fa-light" x-bind:class="resolved === 'dark' ? 'fa-sun-bright' : 'fa-moon-stars'"></i>
                            <span x-text="resolved === 'dark' ? @js(__('Light mode')) : @js(__('Dark mode'))"></span>
                        </button>
                        @if ($activeLanguage)
                            <span class="lm-language-button"><span class="{{ language_flag_class($activeLanguage) }}"></span>{{ strtoupper((string) $activeLanguage->code) }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </header>

        <main id="main-content" class="lm-main" tabindex="-1">
            @hasSection('content')
                @yield('content')
            @else
                {{ $slot ?? '' }}
            @endif
        </main>

        <footer class="lm-footer">
            <div class="lm-container">
                <div class="lm-footer-grid">
                    <div>
                        <a href="{{ route('home') }}" class="lm-brand">
                            <img class="theme-logo-dark" src="{{ $siteLogoDarkUrl }}" alt="{{ $siteTitle }}">
                            <img class="theme-logo-light" src="{{ $siteLogoLightUrl }}" alt="{{ $siteTitle }}">
                        </a>
                        <p class="lm-footer-copy">{{ $siteDescription }}</p>
                        @if ($socialLinks->isNotEmpty())
                            <div class="lm-footer-socials">
                                @foreach ($socialLinks as $social)
                                    <a href="{{ $social['url'] }}" class="lm-footer-social" target="_blank" rel="noopener noreferrer" aria-label="{{ $social['label'] }}"><i class="{{ $social['icon'] }}"></i></a>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div>
                        <h2 class="lm-footer-title">{{ __('Product') }}</h2>
                        <div class="lm-footer-links">
                            <a href="{{ route('home') }}#features" class="lm-footer-link">{{ __('Features') }}</a>
                            <a href="{{ route('home') }}#workflow" class="lm-footer-link">{{ __('How it works') }}</a>
                            <a href="{{ route('guest.pricing') }}" class="lm-footer-link">{{ __('Pricing') }}</a>
                            <a href="{{ route('login') }}" class="lm-footer-link">{{ __('Workspace') }}</a>
                        </div>
                    </div>

                    <div>
                        <h2 class="lm-footer-title">{{ __('Resources') }}</h2>
                        <div class="lm-footer-links">
                            <a href="{{ route('guest.blogs') }}" class="lm-footer-link">{{ __('Blog') }}</a>
                            <a href="{{ route('guest.faqs') }}" class="lm-footer-link">{{ __('FAQs') }}</a>
                            <a href="{{ route('guest.contact') }}" class="lm-footer-link">{{ __('Contact') }}</a>
                            @if ($contactEmail !== '' && ! str_ends_with(strtolower($contactEmail), '@yourcompany.com'))<a href="mailto:{{ $contactEmail }}" class="lm-footer-link">{{ $contactEmail }}</a>@endif
                        </div>
                    </div>

                    <div>
                        <h2 class="lm-footer-title">{{ __('Legal') }}</h2>
                        <div class="lm-footer-links">
                            <a href="{{ route('guest.privacy-policy') }}" class="lm-footer-link">{{ __('Privacy Policy') }}</a>
                            <a href="{{ route('guest.terms-of-use') }}" class="lm-footer-link">{{ __('Terms of Use') }}</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="lm-footer-bottom">
                <div class="lm-container lm-footer-bottom-row">
                    <span>© {{ now()->year }} {{ $siteTitle }}. {{ __('All rights reserved.') }}</span>
                    <div class="lm-footer-badges">
                        <span class="lm-footer-badge"><i class="fa-light fa-calendar-check"></i>{{ __('Plan') }}</span>
                        <span class="lm-footer-badge"><i class="fa-light fa-paper-plane"></i>{{ __('Publish') }}</span>
                        <span class="lm-footer-badge"><i class="fa-light fa-chart-line"></i>{{ __('Analyze') }}</span>
                    </div>
                </div>
            </div>
        </footer>

        @include(theme_view('partials.gdpr-consent', 'guest'))
        @include(theme_view('partials.embed-code-body', 'guest'))
        @stack('scripts')
        <script>
            (() => {
                const initLumaPageMotion = () => {
                    document.documentElement.classList.add('lm-motion-ready');
                    const items = [...document.querySelectorAll('.lm-page-reveal:not(.is-visible)')];
                    if (!items.length) return;

                    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) {
                        items.forEach(item => item.classList.add('is-visible'));
                        return;
                    }

                    const observer = new IntersectionObserver(entries => {
                        entries.forEach(entry => {
                            if (!entry.isIntersecting) return;
                            entry.target.classList.add('is-visible');
                            observer.unobserve(entry.target);
                        });
                    }, { threshold: .12, rootMargin: '0px 0px -7% 0px' });

                    items.forEach(item => observer.observe(item));
                };

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initLumaPageMotion, { once: true });
                } else {
                    initLumaPageMotion();
                }
                document.addEventListener('livewire:navigated', initLumaPageMotion);
            })();
        </script>
    </div>
</body>
</html>
