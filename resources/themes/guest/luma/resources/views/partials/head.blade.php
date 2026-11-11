<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
@php
    $options = app(\Modules\AdminSettings\Support\OptionStore::class);
    $gaEnabled = (string) $options->get('google_analytics_status', '0') === '1';
    $gaMeasurementId = trim((string) $options->get('google_analytics_measurement_id', ''));
    $gaTrackGuest = (string) $options->get('google_analytics_track_guest', '1') === '1';
    $siteFavicon = url((string) $options->get('website_favicon', 'public/img/favicon.png'));
    $siteTitle = trim((string) $options->get('website_title', ''));
    $siteTitle = $siteTitle !== '' ? $siteTitle : 'Your Company Name';
    $cardRadius = theme_setting('card_radius', 'guest', 18);
    $inputRadius = theme_setting('input_radius', 'guest', 14);
    $buttonRadius = theme_setting('button_radius', 'guest', 14);
    $pageMaxWidth = theme_setting('page_max_width', 'guest', '86rem');
    $sectionSpacing = theme_setting('section_spacing', 'guest', '5rem');
@endphp

<title>{{ filled($title ?? null) ? $title.' - '.$siteTitle : $siteTitle }}</title>

@php
    $metaDescription = trim((string) ($pageDescription ?? $options->get('website_description', '')));
@endphp
@if($metaDescription !== '')
<meta name="description" content="{{ $metaDescription }}">
<meta property="og:description" content="{{ $metaDescription }}">
@endif
<link rel="canonical" href="{{ url()->current() }}">
<meta property="og:title" content="{{ filled($title ?? null) ? $title.' - '.$siteTitle : $siteTitle }}">
<meta property="og:url" content="{{ url()->current() }}">
<meta property="og:type" content="{{ request()->routeIs('guest.blog-show') ? 'article' : 'website' }}">
@if(filled($pageImage ?? null))
<meta property="og:image" content="{{ url($pageImage) }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:image" content="{{ url($pageImage) }}">
@else
<meta name="twitter:card" content="summary">
@endif

@if ($gaEnabled && $gaTrackGuest && $gaMeasurementId !== '')
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $gaMeasurementId }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', @js($gaMeasurementId));
    </script>
@endif

@include(theme_view('partials.embed-code-head', 'guest'))

<link rel="icon" href="{{ $siteFavicon }}" type="image/png">
<link rel="apple-touch-icon" href="{{ $siteFavicon }}">
<link rel="preconnect" href="https://fonts.bunny.net">
@php
    $guestFont = (string) theme_setting('font_family', 'guest', 'inter');
    $guestFont = in_array($guestFont, ['inter', 'instrument-sans', 'plus-jakarta-sans', 'manrope', 'outfit', 'sora'], true) ? $guestFont : 'inter';
@endphp
<link href="https://fonts.bunny.net/css?family={{ $guestFont }}:400,500,600,700,800&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="{{ theme_shared_asset('plugins/fontawesome/css/fontawesome.css') }}">
<link rel="stylesheet" href="{{ theme_shared_asset('plugins/fontawesome/css/brands.css') }}">
<link rel="stylesheet" href="{{ theme_shared_asset('plugins/fontawesome/css/light.css') }}">
<link rel="stylesheet" href="{{ theme_shared_asset('plugins/fontawesome/css/regular.css') }}">
<link rel="stylesheet" href="{{ theme_shared_asset('plugins/fontawesome/css/solid.css') }}">
<link rel="stylesheet" href="{{ theme_shared_asset('plugins/flags/flag-icon.css') }}">

<style>
    :root,
    html[data-theme-resolved='light'],
    html[data-theme-resolved='dark'] {
        --theme-accent: {{ theme_setting('accent_color', 'guest', '#2454E8') }};
        --theme-accent-rgb: {{ theme_accent_rgb('guest') }};
        --theme-body-bg: {{ theme_setting('body_bg_color', 'guest', '#F7FAFC') }};
        --theme-body-bg-rgb: {{ theme_color_rgb('body_bg_color', 'guest', '#F7FAFC') }};
        --theme-surface-bg: {{ theme_setting('surface_bg_color', 'guest', '#FFFFFF') }};
        --theme-surface-bg-rgb: {{ theme_color_rgb('surface_bg_color', 'guest', '#FFFFFF') }};
        --theme-header-bg: {{ theme_setting('header_bg_color', 'guest', '#FFFFFF') }};
        --theme-header-bg-rgb: {{ theme_color_rgb('header_bg_color', 'guest', '#FFFFFF') }};
        --theme-header-text-color: {{ theme_setting('header_text_color', 'guest', '#0F172A') }};
        --theme-link-color: {{ theme_setting('link_color', 'guest', '#2454E8') }};
        --theme-link-hover-color: {{ theme_setting('link_hover_color', 'guest', '#0F766E') }};
        --theme-border-color: {{ theme_setting('border_color', 'guest', '#DCE6F3') }};
        --theme-border-color-rgb: {{ theme_color_rgb('border_color', 'guest', '#DCE6F3') }};
        --theme-muted-text-color: {{ theme_setting('muted_text_color', 'guest', '#64748B') }};
        --theme-success-color: {{ theme_setting('success_color', 'guest', '#059669') }};
        --theme-warning-color: {{ theme_setting('warning_color', 'guest', '#D97706') }};
        --theme-danger-color: {{ theme_setting('danger_color', 'guest', '#DC2626') }};
        --theme-card-radius: {{ is_numeric($cardRadius) ? $cardRadius.'px' : $cardRadius }};
        --theme-input-radius: {{ is_numeric($inputRadius) ? $inputRadius.'px' : $inputRadius }};
        --theme-button-radius: {{ is_numeric($buttonRadius) ? $buttonRadius.'px' : $buttonRadius }};
        --theme-page-max-width: {{ $pageMaxWidth }};
        --theme-section-spacing: {{ $sectionSpacing }};
        --theme-font-sans: {!! theme_font_stack('guest') !!};
    }

</style>
@include(theme_view('partials.stylesheet', 'guest'), ['stylesheet' => 'base'])

@if (filled(theme_setting('custom_css', 'guest')))
    <style>{!! theme_setting('custom_css', 'guest') !!}</style>
@endif

<script>
    (() => {
        const storageKey = 'localboost-theme-mode';
        const supportsDark = @js((string) theme_setting('supports_dark_mode', 'guest', '1')) !== '0';
        const allowToggle = @js((string) theme_setting('allow_user_appearance_toggle', 'guest', '1')) !== '0';
        const defaultMode = @js(theme_setting('default_appearance', 'guest', 'light'));
        const normalize = (mode) => supportsDark && mode === 'dark' ? 'dark' : 'light';
        const stored = () => normalize(localStorage.getItem(storageKey) || defaultMode || 'light');
        const apply = (mode, notify = true) => {
            const nextMode = normalize(mode);
            const resolved = nextMode;
            document.documentElement.dataset.themeMode = nextMode;
            document.documentElement.dataset.themeResolved = resolved;
            document.documentElement.classList.toggle('dark', resolved === 'dark');

            const state = { mode: nextMode, resolved };

            if (notify) {
                window.dispatchEvent(new CustomEvent('theme-mode-changed', { detail: state }));
            }

            return state;
        };
        const restore = (notify = true) => apply(stored(), notify);
        const storedMode = stored();
        window.themeMode = {
            getMode: () => document.documentElement.dataset.themeMode || stored(),
            getResolved: () => document.documentElement.dataset.themeResolved || stored(),
            supportsDark: () => supportsDark,
            allowToggle: () => allowToggle,
            setMode: (mode) => {
                const nextMode = normalize(mode);
                localStorage.setItem(storageKey, nextMode);
                return apply(nextMode);
            },
            toggle: () => {
                const nextMode = (document.documentElement.dataset.themeResolved || storedMode) === 'dark' ? 'light' : 'dark';
                localStorage.setItem(storageKey, nextMode);
                return apply(nextMode);
            },
            restore,
            apply,
        };
        apply(storedMode, false);
        document.addEventListener('livewire:navigating', () => restore(false));
        document.addEventListener('livewire:navigated', () => requestAnimationFrame(() => restore(true)));
        window.addEventListener('pageshow', () => restore(false));
    })();
</script>
<script>
    (() => {
        const initReveal = () => {
            const nodes = document.querySelectorAll('[data-reveal]');

            if (!nodes.length) {
                return;
            }

            if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                nodes.forEach((node) => node.classList.add('localboost-reveal', 'is-visible'));
                return;
            }

            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) {
                        return;
                    }

                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                });
            }, { threshold: 0.14 });

            nodes.forEach((node, index) => {
                node.classList.add('localboost-reveal');
                node.style.setProperty('--localboost-reveal-delay', `${Math.min((index % 6) * 70, 280)}ms`);
                observer.observe(node);
            });
        };

        document.addEventListener('DOMContentLoaded', initReveal);
        document.addEventListener('livewire:navigated', initReveal);
    })();
</script>
@if (filled(theme_setting('custom_js', 'guest')))
    <script>{!! theme_setting('custom_js', 'guest') !!}</script>
@endif
{!! theme_vite('guest', ['assets/js/app.js']) !!}
