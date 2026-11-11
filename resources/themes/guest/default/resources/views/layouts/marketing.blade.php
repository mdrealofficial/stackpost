<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ current_locale_direction() }}">
<head>
    @include(theme_view('partials.head', 'guest'), ['title' => $pageTitle ?? null])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>
</head>
@php
    $languages = available_languages();
    $options = app(\Modules\AdminSettings\Support\OptionStore::class);
    $siteTitle = trim((string) $options->get('website_title', ''));
    $siteDescription = trim((string) $options->get('website_description', ''));
    $siteTitle = $siteTitle !== '' ? $siteTitle : config('site.title', config('app.name', 'Stackposts'));
    $siteDescription = $siteDescription !== '' ? $siteDescription : config('site.description', __('Run publishing, AI content, bulk posts, RSS schedules, media handling, and team workflows from one system.'));
    $signupEnabled = (string) $options->get('auth_signup_page_status', '1') === '1';
    $contactEmail = trim((string) $options->get('contact_email', ''));
    $contactPhone = trim((string) $options->get('contact_phone_number', ''));
    $socialLinks = collect([
        ['label' => 'Facebook', 'key' => 'social_page_facebook'],
        ['label' => 'Instagram', 'key' => 'social_page_instagram'],
        ['label' => 'LinkedIn', 'key' => 'social_page_linkedin'],
        ['label' => 'X', 'key' => 'social_page_x'],
        ['label' => 'YouTube', 'key' => 'social_page_youtube'],
        ['label' => 'TikTok', 'key' => 'social_page_tiktok'],
        ['label' => 'Telegram', 'key' => 'social_page_telegram'],
        ['label' => 'WhatsApp', 'key' => 'social_page_whatsapp'],
    ])->map(function (array $item) use ($options): ?array {
        $url = trim((string) $options->get($item['key'], ''));

        return $url !== '' ? ['label' => $item['label'], 'url' => $url] : null;
    })->filter()->values();
    $navItems = [
        [
            'key' => 'home',
            'label' => __('Home'),
            'href' => route('home'),
            'active' => false,
            'hash' => null,
        ],
        [
            'key' => 'features',
            'label' => __('Features'),
            'href' => route('home').'#capabilities',
            'active' => false,
            'hash' => '#capabilities',
        ],
        [
            'key' => 'pricing',
            'label' => __('Pricing'),
            'href' => route('guest.pricing'),
            'active' => request()->routeIs('guest.pricing'),
            'hash' => null,
        ],
        [
            'key' => 'faqs',
            'label' => __('FAQs'),
            'href' => route('guest.faqs'),
            'active' => request()->routeIs('guest.faqs'),
            'hash' => null,
        ],
        [
            'key' => 'blog',
            'label' => __('Blog'),
            'href' => route('guest.blogs'),
            'active' => request()->routeIs('guest.blogs') || request()->routeIs('guest.blog*'),
            'hash' => null,
        ],
        [
            'key' => 'contact',
            'label' => __('Contact'),
            'href' => url('contact'),
            'active' => request()->is('contact') || request()->is('contact/*'),
            'hash' => null,
        ],
    ];
    $footerExplore = [
        ['label' => __('Home'), 'href' => route('home')],
        ['label' => __('Features'), 'href' => route('home').'#capabilities'],
        ['label' => __('Pricing'), 'href' => route('guest.pricing')],
        ['label' => __('FAQs'), 'href' => route('guest.faqs')],
        ['label' => __('Blog'), 'href' => route('guest.blogs')],
        ['label' => __('Contact'), 'href' => url('contact')],
    ];
    $footerLegal = [
        ['label' => __('Privacy Policy'), 'href' => route('guest.privacy-policy')],
        ['label' => __('Terms of Use'), 'href' => route('guest.terms-of-use')],
    ];
@endphp
<body class="min-h-screen antialiased" style="background-color: var(--theme-body-bg); color: var(--theme-header-text-color); font-family: var(--theme-font-sans);">
    <div class="relative isolate">
        <div class="absolute inset-0 -z-30" style="background: var(--theme-marketing-bg);"></div>
        <div class="absolute inset-0 -z-20 opacity-90" style="background-image: var(--theme-marketing-overlay);"></div>
        <div class="absolute left-[-10rem] top-12 -z-10 h-[24rem] w-[24rem] rounded-full bg-violet-600/18 blur-3xl"></div>
        <div class="absolute right-[-8rem] top-10 -z-10 h-[26rem] w-[26rem] rounded-full bg-cyan-400/14 blur-3xl"></div>

        <header
            x-data="{ open: false, scrolled: false, currentHash: window.location.hash || '' }"
            x-init="
                scrolled = window.scrollY > 12;
                window.addEventListener('scroll', () => { scrolled = window.scrollY > 12 }, { passive: true });
                window.addEventListener('hashchange', () => { currentHash = window.location.hash || '' });
            "
            class="guest-marketing-header guest-marketing-shell sticky top-0 z-40 mx-auto transition-all duration-200"
            x-bind:class="scrolled ? 'px-0 pt-0 lg:px-0 lg:pt-0' : 'px-5 pt-5 lg:px-8 lg:pt-7'"
        >
            <div
                class="guest-marketing-header-panel border px-4 py-4 backdrop-blur-xl transition-all duration-200 lg:px-6"
                x-bind:class="scrolled ? 'rounded-t-none rounded-b-[1.6rem] border-t-0 border-l-0 border-r-0' : 'rounded-[2rem]'"
                style="border-color: var(--theme-header-panel-border); background: var(--theme-header-panel-bg); box-shadow: var(--theme-panel-shadow);"
            >
                <div class="flex items-center justify-between gap-4 lg:gap-6">
                    <a href="{{ route('home') }}" class="flex items-center transition hover:opacity-90">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-[1rem] ring-1 ring-white/10 shadow-[0_0_30px_rgba(139,92,246,0.28)]" style="background: linear-gradient(135deg, rgba(139,92,246,0.88) 0%, rgba(79,70,229,0.92) 58%, rgba(34,211,238,0.84) 100%);">
                            <img src="{{ url(get_option('website_logo_light', 'public/img/logo-light.png')) }}" alt="{{ $siteTitle }}" class="h-7 w-auto object-contain">
                        </span>
                    </a>

                    <nav class="hidden flex-1 justify-center lg:flex">
                        <div class="guest-marketing-header-nav flex items-center gap-1 rounded-full border border-white/8 bg-white/[0.03] px-2 py-2 shadow-[inset_0_1px_0_rgba(255,255,255,0.04)]">
                            @foreach ($navItems as $item)
                                <a
                                    href="{{ $item['href'] }}"
                                    class="rounded-full px-4 py-2 text-sm font-medium transition"
                                    x-bind:class="(() => {
                                        const itemKey = @js($item['key']);
                                        const itemHash = @js($item['hash']);
                                        const isFeatures = itemHash && currentHash === itemHash;
                                        const isHome = itemKey === 'home' && @js(request()->routeIs('home')) && currentHash !== '#capabilities';
                                        const isServerActive = @js($item['active']);
                                        return (isFeatures || isHome || isServerActive)
                                            ? 'bg-white/[0.08] text-white'
                                            : 'text-white/54 hover:bg-white/[0.05] hover:text-white';
                                    })()"
                                >{{ $item['label'] }}</a>
                            @endforeach
                        </div>
                    </nav>

                    <div class="hidden items-center gap-2.5 lg:flex">
                        @include(theme_view('partials.appearance-toggle', 'guest'))
                        @if ($languages->isNotEmpty())
                            @php
                                $activeLanguage = $languages->firstWhere('code', app()->getLocale()) ?? $languages->first();
                                $activeCode = strtolower((string) ($activeLanguage->code ?? 'en'));
                                $activeLabel = $activeLanguage->name ?? strtoupper($activeCode);
                            @endphp
                            <div x-data="{ open: false }" class="relative">
                                <button x-on:click="open = !open" x-on:click.outside="open = false" type="button" class="guest-marketing-action-button guest-marketing-locale-button inline-flex h-11 items-center gap-2 rounded-full border border-white/8 bg-white/[0.03] px-3.5 text-sm font-medium text-white/88 transition hover:bg-white/[0.05]">
                                    <span class="{{ language_flag_class($activeLanguage ?? app()->getLocale()) }} rounded-sm text-[18px]"></span>
                                    <i class="fa-light fa-chevron-down text-xs text-white/44"></i>
                                </button>

                                <div x-cloak x-show="open" x-transition.origin.top.right class="guest-marketing-language-dropdown absolute right-0 z-30 mt-3 w-56 overflow-hidden rounded-[1.4rem] border border-white/10 bg-[linear-gradient(180deg,rgba(16,18,32,0.99)_0%,rgba(9,12,24,0.98)_100%)] p-2 shadow-[0_30px_80px_-34px_rgba(0,0,0,0.9)] backdrop-blur-xl">
                                    @foreach ($languages as $language)
                                        @php
                                            $code = strtolower((string) $language->code);
                                            $label = $language->name ?? strtoupper($code);
                                            $isActiveLanguage = app()->getLocale() === $language->code;
                                        @endphp
                                        <a href="{{ route('language.switch', $language->code) }}" class="guest-marketing-language-option no-theme-link {{ $isActiveLanguage ? 'bg-emerald-500 text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.08)]' : 'text-white/86 hover:bg-white/[0.05] hover:text-white' }} flex items-center gap-3 rounded-[1rem] px-3 py-3 text-sm font-medium transition">
                                            <span class="{{ language_flag_class($language) }} rounded-sm text-[18px]"></span>
                                            <span class="flex-1">{{ $label }}</span>
                                            @if ($isActiveLanguage)
                                                <i class="fa-light fa-check text-xs"></i>
                                            @endif
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @auth
                            <a href="{{ route('portal.dashboard') }}" class="guest-marketing-action-button guest-marketing-dashboard-button inline-flex h-11 items-center rounded-full border border-white/10 bg-white/[0.03] px-5 text-sm font-medium text-white/78 transition hover:bg-white/8 hover:text-white">{{ __('Dashboard') }}</a>
                        @else
                            <a href="{{ route('login') }}" class="guest-marketing-action-button inline-flex h-11 items-center rounded-full border border-white/10 bg-white/[0.03] px-5 text-sm font-medium text-white/72 transition hover:bg-white/8 hover:text-white">{{ __('Log in') }}</a>
                            @if ($signupEnabled)
                                <a href="{{ route('register') }}" class="guest-marketing-action-button inline-flex h-11 items-center rounded-full px-5 text-sm font-semibold text-white shadow-[0_0_28px_rgba(99,102,241,0.32),0_20px_46px_-24px_rgba(34,211,238,0.45)] transition hover:scale-[1.01]" style="background: linear-gradient(135deg, #8b5cf6 0%, #4f46e5 52%, #22d3ee 100%);">{{ __('Get Started Free') }}</a>
                            @endif
                        @endauth
                    </div>

                    <div class="flex items-center gap-2 lg:hidden">
                        @include(theme_view('partials.appearance-toggle', 'guest'))
                        @if ($languages->isNotEmpty())
                            @php
                                $activeLanguage = $languages->firstWhere('code', app()->getLocale()) ?? $languages->first();
                            @endphp
                            <div x-data="{ openLanguage: false }" class="relative">
                                <button x-on:click="openLanguage = !openLanguage" x-on:click.outside="openLanguage = false" type="button" class="guest-marketing-action-button guest-marketing-locale-button inline-flex h-11 items-center gap-2 rounded-[1rem] border border-white/10 bg-white/[0.03] px-3 text-sm font-medium text-white/88">
                                    <span class="{{ language_flag_class($activeLanguage ?? app()->getLocale()) }} rounded-sm text-[18px]"></span>
                                    <i class="fa-light fa-chevron-down text-[10px] text-white/44"></i>
                                </button>

                                <div x-cloak x-show="openLanguage" x-transition.origin.top.right class="guest-marketing-language-dropdown absolute right-0 z-30 mt-3 w-52 overflow-hidden rounded-[1.2rem] border border-white/10 bg-[linear-gradient(180deg,rgba(16,18,32,0.99)_0%,rgba(9,12,24,0.98)_100%)] p-2 shadow-[0_30px_80px_-34px_rgba(0,0,0,0.9)] backdrop-blur-xl">
                                    @foreach ($languages as $language)
                                        @php
                                            $isActiveLanguage = app()->getLocale() === $language->code;
                                        @endphp
                                        <a href="{{ route('language.switch', $language->code) }}" class="guest-marketing-language-option no-theme-link {{ $isActiveLanguage ? 'bg-emerald-500 text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.08)]' : 'text-white/86 hover:bg-white/[0.05] hover:text-white' }} flex items-center gap-3 rounded-[1rem] px-3 py-3 text-sm font-medium transition">
                                            <span class="{{ language_flag_class($language) }} rounded-sm text-[18px]"></span>
                                            <span class="flex-1">{{ $language->name ?? strtoupper((string) $language->code) }}</span>
                                            @if ($isActiveLanguage)
                                                <i class="fa-light fa-check text-xs"></i>
                                            @endif
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        <button x-on:click="open = !open" type="button" class="inline-flex h-11 w-11 items-center justify-center rounded-[1rem] border border-white/10 bg-white/[0.03] text-white/72">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 12h16M4 17h16" /></svg>
                        </button>
                    </div>
                </div>

                <div x-cloak x-show="open" x-transition class="mt-4 space-y-4 border-t border-white/8 pt-4 lg:hidden">
                    <nav class="grid gap-2 text-sm font-medium text-white/70">
                        @foreach ($navItems as $item)
                            <a href="{{ $item['href'] }}" class="{{ $item['active'] ? 'bg-white/[0.08] text-white' : 'hover:bg-white/5 hover:text-white' }} rounded-2xl px-3 py-2 transition">{{ $item['label'] }}</a>
                        @endforeach
                    </nav>
                    @if ($languages->isNotEmpty())
                        <div class="grid gap-2">
                            <p class="px-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-white/34">{{ __('Language') }}</p>
                            <div class="grid gap-2 sm:grid-cols-2">
                                @foreach ($languages as $language)
                                    @php
                                        $isActiveLanguage = app()->getLocale() === $language->code;
                                    @endphp
                                    <a href="{{ route('language.switch', $language->code) }}" class="{{ $isActiveLanguage ? 'bg-white/[0.08] text-white border-white/14' : 'text-white/72 border-white/10 hover:bg-white/[0.05] hover:text-white' }} inline-flex items-center gap-3 rounded-2xl border px-4 py-3 text-sm font-medium transition">
                                        <span class="{{ language_flag_class($language) }} rounded-sm text-[18px]"></span>
                                        <span>{{ $language->name ?? strtoupper((string) $language->code) }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    <div class="grid gap-2 sm:grid-cols-2">
                        @auth
                            <a href="{{ route('portal.dashboard') }}" class="inline-flex items-center justify-center rounded-2xl border border-white/10 px-4 py-3 text-sm font-medium text-white/80">{{ __('Dashboard') }}</a>
                        @else
                            <a href="{{ route('login') }}" class="inline-flex items-center justify-center rounded-2xl border border-white/10 px-4 py-3 text-sm font-medium text-white/80">{{ __('Log in') }}</a>
                            @if ($signupEnabled)
                                <a href="{{ route('register') }}" class="inline-flex items-center justify-center rounded-2xl px-4 py-3 text-sm font-semibold text-white shadow-[0_0_24px_rgba(99,102,241,0.24)]" style="background: linear-gradient(135deg, #8b5cf6 0%, #4f46e5 52%, #22d3ee 100%);">{{ __('Get Started Free') }}</a>
                            @endif
                        @endauth
                    </div>
                </div>
            </div>
        </header>

        <main class="pt-6 lg:pt-8">
        @hasSection('content')
            @yield('content')
        @else
            {{ $slot ?? '' }}
        @endif
        </main>

        <footer class="guest-marketing-shell mx-auto px-5 pb-3 lg:px-8 lg:pb-4">
            <div class="overflow-hidden rounded-[2rem] border backdrop-blur-xl" style="border-color: var(--theme-header-panel-border); background: var(--theme-panel-bg); box-shadow: var(--theme-panel-shadow);">
                <div class="grid gap-10 px-6 py-8 lg:grid-cols-[1.2fr_1.5fr_0.7fr] lg:px-8 lg:py-10">
                    <div class="relative max-w-md">
                        <div class="pointer-events-none absolute -left-6 top-0 h-28 w-28 rounded-full bg-violet-500/8 blur-3xl"></div>
                        <div class="pointer-events-none absolute left-16 top-10 h-20 w-20 rounded-full bg-cyan-400/8 blur-3xl"></div>
                        <div class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-xl ring-1 ring-white/10 shadow-[0_0_24px_rgba(139,92,246,0.18)]" style="background: linear-gradient(135deg, rgba(139,92,246,0.88) 0%, rgba(79,70,229,0.92) 58%, rgba(34,211,238,0.84) 100%);">
                            <img src="{{ url(get_option('website_logo_light', 'public/img/logo-light.png')) }}" alt="{{ $siteTitle }}" class="h-7 w-auto object-contain">
                        </div>
                        <p class="mt-5 max-w-[19rem] text-[1.16rem] font-semibold leading-[1.22] tracking-[-0.02em] text-white lg:text-[1.24rem]">
                            {{ __('Create better content and publish with less manual work.') }}
                        </p>
                        <p class="mt-4 text-sm text-white/44">{{ $siteTitle }}, {{ date('Y') }}.</p>
                    </div>

                    <div class="grid gap-8 sm:grid-cols-3">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-white/34">{{ __('Platform') }}</p>
                            <div class="mt-4 grid gap-3 text-sm text-white/66">
                                <a href="{{ route('guest.pricing') }}" class="no-theme-link transition hover:text-white" style="color: inherit;">{{ __('Plans & Pricing') }}</a>
                                <a href="{{ route('home') }}#capabilities" class="no-theme-link transition hover:text-white" style="color: inherit;">{{ __('Features') }}</a>
                                <a href="{{ route('guest.contact') }}" class="no-theme-link transition hover:text-white" style="color: inherit;">{{ __('Contact') }}</a>
                            </div>
                        </div>

                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-white/34">{{ __('Company') }}</p>
                            <div class="mt-4 grid gap-3 text-sm text-white/66">
                                <a href="{{ route('guest.blogs') }}" class="no-theme-link transition hover:text-white" style="color: inherit;">{{ __('Blog') }}</a>
                                <a href="{{ route('guest.faqs') }}" class="no-theme-link transition hover:text-white" style="color: inherit;">{{ __('FAQs') }}</a>
                                <a href="{{ route('guest.contact') }}" class="no-theme-link transition hover:text-white" style="color: inherit;">{{ __('Support') }}</a>
                            </div>
                        </div>

                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-white/34">{{ __('Resources') }}</p>
                            <div class="mt-4 grid gap-3 text-sm text-white/66">
                                @foreach ($footerLegal as $item)
                                    <a href="{{ $item['href'] }}" class="no-theme-link transition hover:text-white" style="color: inherit;">{{ $item['label'] }}</a>
                                @endforeach
                                @if ($socialLinks->isNotEmpty())
                                    <a href="{{ $socialLinks->first()['url'] }}" target="_blank" rel="noopener noreferrer" class="no-theme-link transition hover:text-white" style="color: inherit;">{{ __('Social') }}</a>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="lg:justify-self-end">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-white/34">{{ __('Get in touch') }}</p>
                        <div class="mt-4 flex flex-col gap-3">
                            @if ($contactEmail !== '')
                                <a href="mailto:{{ $contactEmail }}" class="no-theme-link inline-flex items-center justify-center rounded-full border border-white/10 px-4 py-2.5 text-sm font-medium text-white/72 transition hover:bg-white/8 hover:text-white" style="color: inherit;">{{ $contactEmail }}</a>
                            @endif
                            @if ($contactPhone !== '')
                                <a href="tel:{{ preg_replace('/\s+/', '', $contactPhone) }}" class="no-theme-link inline-flex items-center justify-center rounded-full border border-white/10 px-4 py-2.5 text-sm font-medium text-white/72 transition hover:bg-white/8 hover:text-white" style="color: inherit;">{{ $contactPhone }}</a>
                            @endif
                            @if ($contactEmail === '' && $contactPhone === '')
                                <a href="{{ route('guest.contact') }}" class="no-theme-link inline-flex items-center justify-center rounded-full border border-white/10 px-4 py-2.5 text-sm font-medium text-white/72 transition hover:bg-white/8 hover:text-white" style="color: inherit;">{{ __('Contact us') }}</a>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="border-t border-white/8 bg-white/[0.03]">
                    <div class="flex flex-col gap-3 px-6 py-4 text-[12px] font-medium text-white/44 sm:flex-row sm:items-center sm:justify-between lg:px-8">
                        <p>&copy; {{ date('Y') }} {{ $siteTitle }}. {{ __('All rights reserved.') }}</p>
                        <div class="flex flex-wrap items-center gap-x-5 gap-y-2">
                            @foreach ($footerLegal as $item)
                                <a href="{{ $item['href'] }}" class="no-theme-link transition hover:text-white" style="color: inherit;">{{ $item['label'] }}</a>
                            @endforeach
                            <a href="{{ route('guest.contact') }}" class="no-theme-link transition hover:text-white" style="color: inherit;">{{ __('Contact') }}</a>
                        </div>
                    </div>
                </div>
            </div>
        </footer>
    </div>
    @include(theme_view('partials.embed-code-body', 'guest'))
</body>
</html>
