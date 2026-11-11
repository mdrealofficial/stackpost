<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
@php
    $gaOptions = app(\Modules\AdminSettings\Support\OptionStore::class);
    $gaEnabled = (string) $gaOptions->get('google_analytics_status', '0') === '1';
    $gaMeasurementId = trim((string) $gaOptions->get('google_analytics_measurement_id', ''));
    $gaTrackGuest = (string) $gaOptions->get('google_analytics_track_guest', '1') === '1';
    $siteFavicon = url((string) $gaOptions->get('website_favicon', 'public/img/favicon.png'));
    $siteTitle = trim((string) $gaOptions->get('website_title', ''));
    $siteTitle = $siteTitle !== '' ? $siteTitle : config('site.title', config('app.name', 'Laravel'));
@endphp

<title>
    {{ filled($title ?? null) ? $title.' - '.$siteTitle : $siteTitle }}
</title>

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
<link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800|instrument-sans:400,500,600,700|plus-jakarta-sans:400,500,600,700,800|manrope:400,500,600,700,800|outfit:400,500,600,700,800|sora:400,500,600,700,800|space-grotesk:400,500,600,700|public-sans:400,500,600,700,800|ibm-plex-sans:400,500,600,700|dm-sans:400,500,600,700,800" rel="stylesheet" />
<link rel="stylesheet" href="{{ theme_shared_asset('plugins/fontawesome/css/fontawesome.css') }}">
<link rel="stylesheet" href="{{ theme_shared_asset('plugins/fontawesome/css/brands.css') }}">
<link rel="stylesheet" href="{{ theme_shared_asset('plugins/fontawesome/css/light.css') }}">
<link rel="stylesheet" href="{{ theme_shared_asset('plugins/fontawesome/css/regular.css') }}">
<link rel="stylesheet" href="{{ theme_shared_asset('plugins/fontawesome/css/solid.css') }}">
<link rel="stylesheet" href="{{ theme_shared_asset('plugins/fontawesome/css/thin.css') }}">
<link rel="stylesheet" href="{{ theme_shared_asset('plugins/flags/flag-icon.css') }}">
@php
    $cardRadius = theme_setting('card_radius', 'guest', 14);
    $inputRadius = theme_setting('input_radius', 'guest', 10);
    $buttonRadius = theme_setting('button_radius', 'guest', 12);
    $pageMaxWidth = theme_setting('page_max_width', 'guest', '120rem');
    $sectionSpacing = theme_setting('section_spacing', 'guest', '5rem');
@endphp
<style>
    :root,
    html[data-theme-resolved='dark'] {
        --theme-accent: {{ theme_setting('accent_color', 'guest', '#8b5cf6') }};
        --theme-accent-rgb: {{ theme_accent_rgb('guest') }};
        --theme-body-bg: {{ theme_setting('body_bg_color', 'guest', '#070814') }};
        --theme-body-bg-rgb: {{ theme_color_rgb('body_bg_color', 'guest', '#070814') }};
        --theme-surface-bg: {{ theme_setting('surface_bg_color', 'guest', '#0f1224') }};
        --theme-surface-bg-rgb: {{ theme_color_rgb('surface_bg_color', 'guest', '#0f1224') }};
        --theme-header-bg: {{ theme_setting('header_bg_color', 'guest', '#0c1020') }};
        --theme-header-bg-rgb: {{ theme_color_rgb('header_bg_color', 'guest', '#0c1020') }};
        --theme-header-active: {{ theme_setting('header_active_color', 'guest', '#f8fafc') }};
        --theme-header-active-rgb: {{ theme_color_rgb('header_active_color', 'guest', '#f8fafc') }};
        --theme-header-text-color: {{ theme_setting('header_text_color', 'guest', '#f8fafc') }};
        --theme-link-color: {{ theme_setting('link_color', 'guest', '#8b5cf6') }};
        --theme-link-hover-color: {{ theme_setting('link_hover_color', 'guest', '#22d3ee') }};
        --theme-border-color: {{ theme_setting('border_color', 'guest', '#1f2440') }};
        --theme-muted-text-color: {{ theme_setting('muted_text_color', 'guest', '#aab6d3') }};
        --theme-success-color: {{ theme_setting('success_color', 'guest', '#059669') }};
        --theme-warning-color: {{ theme_setting('warning_color', 'guest', '#d97706') }};
        --theme-danger-color: {{ theme_setting('danger_color', 'guest', '#dc2626') }};
        --theme-input-surface: rgba(10, 14, 28, 0.82);
        --theme-input-text: #f8fafc;
        --theme-input-placeholder: #8ea0c5;
        --theme-card-radius: {{ is_numeric($cardRadius) ? $cardRadius.'px' : $cardRadius }};
        --theme-input-radius: {{ is_numeric($inputRadius) ? $inputRadius.'px' : $inputRadius }};
        --theme-button-radius: {{ is_numeric($buttonRadius) ? $buttonRadius.'px' : $buttonRadius }};
        --theme-page-max-width: {{ $pageMaxWidth }};
        --theme-section-spacing: {{ $sectionSpacing }};
        --theme-font-sans: {!! theme_font_stack('guest') !!};
        --theme-panel-bg: linear-gradient(180deg, rgba(12,14,27,0.94) 0%, rgba(11,13,25,0.82) 100%);
        --theme-panel-border: rgba(255, 255, 255, 0.10);
        --theme-panel-shadow: 0 30px 90px -56px rgba(0,0,0,0.74);
        --theme-header-panel-bg: linear-gradient(180deg, rgba(13,15,29,0.96) 0%, rgba(10,12,24,0.84) 100%);
        --theme-header-panel-border: rgba(255, 255, 255, 0.08);
        --theme-soft-surface: rgba(255,255,255,0.03);
        --theme-soft-surface-hover: rgba(255,255,255,0.06);
        --theme-top-bg: radial-gradient(circle at top, rgba(139,92,246,0.16), transparent 20%), linear-gradient(180deg,#04050d 0%,#070814 32%,#090b19 100%);
        --theme-marketing-bg: radial-gradient(circle at top, rgba(139,92,246,0.14), transparent 18%), linear-gradient(180deg,#04050d 0%,#070814 26%,#090b19 60%,#05060f 100%);
        --theme-marketing-overlay: radial-gradient(circle at 22% 14%, rgba(139, 92, 246, 0.24), transparent 20%), radial-gradient(circle at 78% 12%, rgba(34, 211, 238, 0.2), transparent 22%), radial-gradient(circle at 50% 42%, rgba(99, 102, 241, 0.12), transparent 28%);
        --nova-card-bg: linear-gradient(180deg, rgba(14,17,31,0.94) 0%, rgba(10,13,25,0.86) 100%);
        --nova-card-border: rgba(255,255,255,0.08);
        --nova-card-shadow: 0 24px 60px -42px rgba(0,0,0,0.66);
        --nova-soft-bg: rgba(255,255,255,0.03);
        --nova-soft-border: rgba(255,255,255,0.08);
        --nova-deep-bg: linear-gradient(180deg, rgba(6,8,18,0.98) 0%, rgba(9,12,24,0.98) 100%);
        --nova-deep-border: rgba(255,255,255,0.06);
        --nova-inverse-bg: linear-gradient(180deg, rgba(14,18,34,0.98) 0%, rgba(10,12,24,0.96) 100%);
        --nova-inverse-border: rgba(255,255,255,0.08);
        --nova-inverse-shadow: 0 40px 120px -60px rgba(0,0,0,0.95);
        --nova-inverse-soft-bg: linear-gradient(180deg, rgba(255,255,255,0.04) 0%, rgba(255,255,255,0.02) 100%);
        --nova-inverse-soft-border: rgba(255,255,255,0.06);
        --nova-inverse-deep-bg: rgba(0,0,0,0.18);
        --nova-inverse-deep-border: rgba(255,255,255,0.06);
        --nova-popover-bg: linear-gradient(180deg, rgba(13,17,32,0.99) 0%, rgba(10,12,24,0.98) 100%);
        --nova-popover-border: rgba(255,255,255,0.10);
        --nova-popover-shadow: 0 30px 80px -40px rgba(0,0,0,0.9);
        --nova-popover-soft-bg: rgba(255,255,255,0.03);
        --nova-popover-soft-border: rgba(255,255,255,0.07);
        --nova-violet-surface-bg: linear-gradient(135deg, rgba(38,20,64,0.74) 0%, rgba(18,18,34,0.94) 48%, rgba(10,12,24,0.98) 100%);
        --nova-violet-surface-border: rgba(167,139,250,0.14);
        --nova-cyan-surface-bg: linear-gradient(135deg, rgba(10,52,74,0.46) 0%, rgba(12,20,36,0.96) 55%, rgba(9,12,24,0.98) 100%);
        --nova-cyan-surface-border: rgba(34,211,238,0.14);
        --nova-emerald-surface-bg: linear-gradient(135deg, rgba(8,64,58,0.42) 0%, rgba(11,20,28,0.96) 56%, rgba(10,12,24,0.98) 100%);
        --nova-emerald-surface-border: rgba(52,211,153,0.14);
        --nova-amber-surface-bg: linear-gradient(135deg, rgba(84,50,12,0.34) 0%, rgba(28,18,17,0.96) 52%, rgba(10,12,24,0.98) 100%);
        --nova-amber-surface-border: rgba(251,191,36,0.14);
    }

    html[data-theme-resolved='light'] {
        --theme-body-bg: #f4f7fb;
        --theme-body-bg-rgb: 244, 247, 251;
        --theme-surface-bg: #ffffff;
        --theme-surface-bg-rgb: 255, 255, 255;
        --theme-header-bg: #ffffff;
        --theme-header-bg-rgb: 255, 255, 255;
        --theme-header-active: #0f172a;
        --theme-header-active-rgb: 15, 23, 42;
        --theme-header-text-color: #0f172a;
        --theme-link-color: #4f46e5;
        --theme-link-hover-color: #0891b2;
        --theme-border-color: #dbe3f0;
        --theme-muted-text-color: #64748b;
        --theme-input-surface: #ffffff;
        --theme-input-text: #0f172a;
        --theme-input-placeholder: #94a3b8;
        --theme-panel-bg: linear-gradient(180deg, rgba(255,255,255,0.98) 0%, rgba(248,250,252,0.96) 100%);
        --theme-panel-border: rgba(148, 163, 184, 0.22);
        --theme-panel-shadow: 0 26px 70px -42px rgba(15, 23, 42, 0.18);
        --theme-header-panel-bg: linear-gradient(180deg, rgba(255,255,255,0.96) 0%, rgba(248,250,252,0.94) 100%);
        --theme-header-panel-border: rgba(148, 163, 184, 0.18);
        --theme-soft-surface: rgba(15,23,42,0.035);
        --theme-soft-surface-hover: rgba(15,23,42,0.07);
        --theme-top-bg: radial-gradient(circle at top, rgba(79,70,229,0.10), transparent 20%), linear-gradient(180deg,#f8fbff 0%,#f4f7fb 42%,#edf3fb 100%);
        --theme-marketing-bg: radial-gradient(circle at top, rgba(79,70,229,0.09), transparent 18%), linear-gradient(180deg,#f8fbff 0%,#f4f7fb 34%,#eef4fb 100%);
        --theme-marketing-overlay: radial-gradient(circle at 22% 14%, rgba(79, 70, 229, 0.12), transparent 20%), radial-gradient(circle at 78% 12%, rgba(8, 145, 178, 0.10), transparent 22%), radial-gradient(circle at 50% 42%, rgba(99, 102, 241, 0.07), transparent 28%);
        --nova-card-bg: linear-gradient(180deg, rgba(255,255,255,0.98) 0%, rgba(246,249,253,0.96) 100%);
        --nova-card-border: rgba(148,163,184,0.20);
        --nova-card-shadow: 0 26px 70px -44px rgba(15,23,42,0.16);
        --nova-soft-bg: rgba(15,23,42,0.04);
        --nova-soft-border: rgba(148,163,184,0.16);
        --nova-deep-bg: linear-gradient(180deg, rgba(241,245,249,0.96) 0%, rgba(233,239,247,0.96) 100%);
        --nova-deep-border: rgba(148,163,184,0.16);
        --nova-inverse-bg: linear-gradient(180deg, rgba(20,24,41,0.98) 0%, rgba(12,16,29,0.97) 100%);
        --nova-inverse-border: rgba(91,102,130,0.26);
        --nova-inverse-shadow: 0 38px 110px -62px rgba(15,23,42,0.42);
        --nova-inverse-soft-bg: linear-gradient(180deg, rgba(255,255,255,0.04) 0%, rgba(255,255,255,0.02) 100%);
        --nova-inverse-soft-border: rgba(255,255,255,0.08);
        --nova-inverse-deep-bg: rgba(0,0,0,0.18);
        --nova-inverse-deep-border: rgba(255,255,255,0.06);
        --nova-popover-bg: linear-gradient(180deg, rgba(255,255,255,0.99) 0%, rgba(244,247,251,0.98) 100%);
        --nova-popover-border: rgba(148,163,184,0.22);
        --nova-popover-shadow: 0 28px 70px -42px rgba(15,23,42,0.18);
        --nova-popover-soft-bg: rgba(15,23,42,0.04);
        --nova-popover-soft-border: rgba(148,163,184,0.16);
        --nova-violet-surface-bg: linear-gradient(180deg, rgba(245,240,255,0.98) 0%, rgba(238,233,255,0.96) 100%);
        --nova-violet-surface-border: rgba(167,139,250,0.24);
        --nova-cyan-surface-bg: linear-gradient(180deg, rgba(236,252,255,0.98) 0%, rgba(227,247,252,0.96) 100%);
        --nova-cyan-surface-border: rgba(34,211,238,0.24);
        --nova-emerald-surface-bg: linear-gradient(180deg, rgba(236,253,245,0.98) 0%, rgba(226,247,239,0.96) 100%);
        --nova-emerald-surface-border: rgba(52,211,153,0.24);
        --nova-amber-surface-bg: linear-gradient(180deg, rgba(255,251,235,0.98) 0%, rgba(250,241,219,0.96) 100%);
        --nova-amber-surface-border: rgba(251,191,36,0.24);
    }

    .guest-page-shell {
        max-width: var(--theme-page-max-width);
    }

    .guest-marketing-shell {
        width: min(calc(100% - 1.5rem), var(--theme-page-max-width));
        max-width: none;
    }

    @media (min-width: 1024px) {
        .guest-marketing-shell {
            width: min(calc(100% - 3rem), var(--theme-page-max-width));
        }
    }

    .guest-section-space {
        padding-top: var(--theme-section-spacing);
        padding-bottom: var(--theme-section-spacing);
    }

    ::selection {
        background: rgba(var(--theme-accent-rgb), 0.28);
        color: #fff;
    }

    a:not(.no-theme-link):not(.inline-flex):not(.rounded-full):not(.group):not(nav a) {
        color: var(--theme-link-color);
    }

    a:not(.no-theme-link):not(.inline-flex):not(.rounded-full):not(.group):not(nav a):hover {
        color: var(--theme-link-hover-color);
    }

    html[data-theme-resolved='light'] body {
        color: var(--theme-header-text-color);
    }

    html[data-theme-resolved='light'] .guest-marketing-header-panel {
        background: linear-gradient(180deg, rgba(255,255,255,0.82) 0%, rgba(248,250,252,0.92) 100%) !important;
        border-color: rgba(148, 163, 184, 0.22) !important;
        box-shadow: 0 20px 54px -36px rgba(15, 23, 42, 0.18), inset 0 1px 0 rgba(255,255,255,0.7) !important;
    }

    html[data-theme-resolved='light'] .guest-marketing-header-nav {
        background: rgba(241, 245, 249, 0.88) !important;
        border-color: rgba(148, 163, 184, 0.18) !important;
        box-shadow: inset 0 1px 0 rgba(255,255,255,0.8) !important;
    }

    html[data-theme-resolved='light'] .guest-marketing-header nav a {
        color: rgba(51, 65, 85, 0.8) !important;
        background: transparent !important;
        box-shadow: none !important;
    }

    html[data-theme-resolved='light'] .guest-marketing-header nav a:hover {
        background: rgba(15,23,42,0.04) !important;
        color: #0f172a !important;
    }

    html[data-theme-resolved='light'] .guest-marketing-header nav a.bg-white\/\[0\.08\],
    html[data-theme-resolved='light'] .guest-marketing-header nav a.text-white {
        background: linear-gradient(180deg, rgba(255,255,255,0.96) 0%, rgba(248,250,252,0.98) 100%) !important;
        color: #0f172a !important;
        box-shadow: 0 10px 24px -18px rgba(15,23,42,0.22), inset 0 1px 0 rgba(255,255,255,0.85) !important;
    }

    html[data-theme-resolved='light'] .guest-marketing-action-button {
        box-shadow: 0 8px 22px -20px rgba(15, 23, 42, 0.2), inset 0 1px 0 rgba(255,255,255,0.82) !important;
    }

    html[data-theme-resolved='light'] .guest-marketing-locale-button {
        background: rgba(255,255,255,0.72) !important;
        border-color: rgba(148, 163, 184, 0.2) !important;
        color: #334155 !important;
    }

    html[data-theme-resolved='light'] .guest-marketing-header .guest-marketing-dashboard-button {
        background: #000000 !important;
        border-color: #000000 !important;
        color: #ffffff !important;
        box-shadow: 0 16px 34px -22px rgba(0, 0, 0, 0.5) !important;
    }

    html[data-theme-resolved='light'] .guest-marketing-header .guest-marketing-dashboard-button:hover {
        background: #000000 !important;
        border-color: #000000 !important;
        color: #ffffff !important;
    }

    html[data-theme-resolved='light'] .guest-marketing-language-dropdown {
        background: linear-gradient(180deg, rgba(255,255,255,0.98) 0%, rgba(248,250,252,0.98) 100%) !important;
        border-color: rgba(148, 163, 184, 0.22) !important;
        box-shadow: 0 26px 60px -36px rgba(15, 23, 42, 0.28), inset 0 1px 0 rgba(255,255,255,0.88) !important;
    }

    html[data-theme-resolved='light'] .guest-marketing-language-dropdown .guest-marketing-language-option {
        color: #334155 !important;
    }

    html[data-theme-resolved='light'] .guest-marketing-language-dropdown .guest-marketing-language-option:hover {
        background: rgba(15, 23, 42, 0.05) !important;
        color: #0f172a !important;
    }

    html[data-theme-resolved='light'] .guest-marketing-language-dropdown .guest-marketing-language-option.bg-emerald-500 {
        background: #111827 !important;
        color: #ffffff !important;
        box-shadow: 0 14px 30px -22px rgba(17, 24, 39, 0.45), inset 0 1px 0 rgba(255,255,255,0.08) !important;
    }

    html,
    body {
        max-width: 100%;
        overflow-x: clip;
    }

    html[data-theme-resolved='light'] .text-white,
    html[data-theme-resolved='light'] [class~='text-white'] {
        color: var(--theme-header-text-color) !important;
    }

    html[data-theme-resolved='light'] [class*='text-white/'] {
        color: color-mix(in srgb, var(--theme-header-text-color) 68%, transparent) !important;
    }

    html[data-theme-resolved='light'] [class*='border-white/'] {
        border-color: rgba(148, 163, 184, 0.22) !important;
    }

    html[data-theme-resolved='light'] [class*='bg-white/[0.03]'],
    html[data-theme-resolved='light'] [class*='bg-white/[0.05]'],
    html[data-theme-resolved='light'] [class*='bg-white/[0.08]'] {
        background-color: rgba(15, 23, 42, 0.04) !important;
    }

    html[data-theme-resolved='light'] [class*='hover:bg-white/[0.05]']:hover,
    html[data-theme-resolved='light'] [class*='hover:bg-white/8']:hover {
        background-color: rgba(15, 23, 42, 0.08) !important;
    }

    html[data-theme-resolved='light'] [class*='shadow-[0_30px_90px_-56px_rgba(0,0,0,0.74)]'],
    html[data-theme-resolved='light'] [class*='shadow-[0_30px_90px_-46px_rgba(0,0,0,0.78)]'],
    html[data-theme-resolved='light'] [class*='shadow-[0_24px_80px_-54px_rgba(0,0,0,0.82)]'] {
        box-shadow: var(--theme-panel-shadow) !important;
    }

    .nova-card-surface {
        border-color: var(--nova-card-border) !important;
        background: var(--nova-card-bg) !important;
        box-shadow: var(--nova-card-shadow) !important;
    }

    .nova-soft-surface {
        border-color: var(--nova-soft-border) !important;
        background: var(--nova-soft-bg) !important;
    }

    .nova-deep-surface {
        border-color: var(--nova-deep-border) !important;
        background: var(--nova-deep-bg) !important;
    }

    .nova-inverse-surface {
        border-color: var(--nova-inverse-border) !important;
        background: var(--nova-inverse-bg) !important;
        box-shadow: var(--nova-inverse-shadow) !important;
    }

    .nova-inverse-soft {
        border-color: var(--nova-inverse-soft-border) !important;
        background: var(--nova-inverse-soft-bg) !important;
    }

    .nova-inverse-deep {
        border-color: var(--nova-inverse-deep-border) !important;
        background: var(--nova-inverse-deep-bg) !important;
    }

    .nova-accent-surface--violet {
        border-color: var(--nova-violet-surface-border) !important;
        background: var(--nova-violet-surface-bg) !important;
        box-shadow: var(--nova-card-shadow) !important;
    }

    .nova-accent-surface--cyan {
        border-color: var(--nova-cyan-surface-border) !important;
        background: var(--nova-cyan-surface-bg) !important;
        box-shadow: var(--nova-card-shadow) !important;
    }

    .nova-accent-surface--emerald {
        border-color: var(--nova-emerald-surface-border) !important;
        background: var(--nova-emerald-surface-bg) !important;
        box-shadow: var(--nova-card-shadow) !important;
    }

    .nova-accent-surface--amber {
        border-color: var(--nova-amber-surface-border) !important;
        background: var(--nova-amber-surface-bg) !important;
        box-shadow: var(--nova-card-shadow) !important;
    }

    .nova-popover-surface {
        border-color: var(--nova-popover-border) !important;
        background: var(--nova-popover-bg) !important;
        box-shadow: var(--nova-popover-shadow) !important;
    }

    .nova-popover-soft {
        border-color: var(--nova-popover-soft-border) !important;
        background: var(--nova-popover-soft-bg) !important;
    }

    html[data-theme-resolved='light'] .nova-inverse,
    html[data-theme-resolved='light'] .nova-inverse a,
    html[data-theme-resolved='light'] .nova-inverse p,
    html[data-theme-resolved='light'] .nova-inverse h2,
    html[data-theme-resolved='light'] .nova-inverse h3,
    html[data-theme-resolved='light'] .nova-inverse span {
        color: #f8fafc;
    }

    html[data-theme-resolved='light'] .nova-inverse [class~='text-white'],
    html[data-theme-resolved='light'] .nova-inverse .text-white {
        color: #f8fafc !important;
    }

    html[data-theme-resolved='light'] .nova-inverse [class*='text-white/'] {
        color: color-mix(in srgb, #f8fafc 72%, transparent) !important;
    }

    html[data-theme-resolved='light'] [class*='text-violet-100'],
    html[data-theme-resolved='light'] [class*='text-violet-200'] {
        color: #6d28d9 !important;
    }

    html[data-theme-resolved='light'] [class*='text-cyan-100'],
    html[data-theme-resolved='light'] [class*='text-cyan-200'],
    html[data-theme-resolved='light'] [class*='text-sky-100'],
    html[data-theme-resolved='light'] [class*='text-sky-200'],
    html[data-theme-resolved='light'] [class*='text-teal-100'] {
        color: #0f766e !important;
    }

    html[data-theme-resolved='light'] [class*='text-emerald-100'],
    html[data-theme-resolved='light'] [class*='text-emerald-200'] {
        color: #047857 !important;
    }

    html[data-theme-resolved='light'] [class*='text-amber-100'],
    html[data-theme-resolved='light'] [class*='text-amber-200'] {
        color: #b45309 !important;
    }

    html[data-theme-resolved='light'] [class*='text-fuchsia-100'],
    html[data-theme-resolved='light'] [class*='text-pink-200'],
    html[data-theme-resolved='light'] [class*='text-rose-100'] {
        color: #be185d !important;
    }

    html[data-theme-resolved='light'] [class*='bg-violet-500/10'],
    html[data-theme-resolved='light'] [class*='bg-violet-500/12'],
    html[data-theme-resolved='light'] [class*='bg-violet-500/14'],
    html[data-theme-resolved='light'] [class*='bg-violet-400/10'] {
        background-color: rgba(109, 40, 217, 0.12) !important;
    }

    html[data-theme-resolved='light'] [class*='bg-cyan-400/10'],
    html[data-theme-resolved='light'] [class*='bg-cyan-400/14'],
    html[data-theme-resolved='light'] [class*='bg-sky-400/14'],
    html[data-theme-resolved='light'] [class*='bg-teal-400/14'] {
        background-color: rgba(15, 118, 110, 0.12) !important;
    }

    html[data-theme-resolved='light'] [class*='bg-emerald-400/10'],
    html[data-theme-resolved='light'] [class*='bg-emerald-400/12'],
    html[data-theme-resolved='light'] [class*='bg-emerald-400/14'] {
        background-color: rgba(4, 120, 87, 0.12) !important;
    }

    html[data-theme-resolved='light'] [class*='bg-amber-400/10'],
    html[data-theme-resolved='light'] [class*='bg-amber-400/14'] {
        background-color: rgba(180, 83, 9, 0.12) !important;
    }

    html[data-theme-resolved='light'] [class*='bg-fuchsia-500/14'],
    html[data-theme-resolved='light'] [class*='bg-pink-400/10'],
    html[data-theme-resolved='light'] [class*='bg-rose-400/14'] {
        background-color: rgba(190, 24, 93, 0.12) !important;
    }

    html[data-theme-resolved='light'] [class*='border-violet-400/'],
    html[data-theme-resolved='light'] [class*='border-violet-300/'] {
        border-color: rgba(109, 40, 217, 0.18) !important;
    }

    html[data-theme-resolved='light'] [class*='border-cyan-400/'],
    html[data-theme-resolved='light'] [class*='border-sky-400/'],
    html[data-theme-resolved='light'] [class*='border-teal-400/'] {
        border-color: rgba(15, 118, 110, 0.18) !important;
    }

    html[data-theme-resolved='light'] [class*='border-emerald-400/'],
    html[data-theme-resolved='light'] [class*='border-emerald-300/'] {
        border-color: rgba(4, 120, 87, 0.18) !important;
    }

    html[data-theme-resolved='light'] [class*='border-amber-400/'] {
        border-color: rgba(180, 83, 9, 0.18) !important;
    }

    html[data-theme-resolved='light'] [class*='border-pink-400/'],
    html[data-theme-resolved='light'] [class*='border-fuchsia-400/'],
    html[data-theme-resolved='light'] [class*='border-rose-400/'] {
        border-color: rgba(190, 24, 93, 0.18) !important;
    }

    .nova-reveal {
        opacity: 0;
        transform: translate3d(0, 28px, 0) scale(0.985);
        transition:
            opacity 700ms cubic-bezier(0.22, 1, 0.36, 1),
            transform 700ms cubic-bezier(0.22, 1, 0.36, 1),
            filter 700ms cubic-bezier(0.22, 1, 0.36, 1);
        transition-delay: var(--nova-delay, 0ms);
        filter: blur(10px);
        will-change: opacity, transform, filter;
    }

    .nova-reveal.is-visible {
        opacity: 1;
        transform: translate3d(0, 0, 0) scale(1);
        filter: blur(0);
    }

    .nova-stagger-1 { --nova-delay: 80ms; }
    .nova-stagger-2 { --nova-delay: 160ms; }
    .nova-stagger-3 { --nova-delay: 240ms; }
    .nova-stagger-4 { --nova-delay: 320ms; }
    .nova-stagger-5 { --nova-delay: 400ms; }
    .nova-stagger-6 { --nova-delay: 480ms; }

    .nova-float {
        animation: nova-float 7s ease-in-out infinite;
    }

    .nova-float-soft {
        animation: nova-float-soft 9s ease-in-out infinite;
    }

    .nova-pulse-glow {
        animation: nova-pulse-glow 4.6s ease-in-out infinite;
    }

    .nova-tilt-hover {
        transform-style: preserve-3d;
        transition:
            transform 320ms cubic-bezier(0.22, 1, 0.36, 1),
            border-color 320ms ease,
            box-shadow 320ms ease,
            background-color 320ms ease;
    }

    .nova-tilt-hover:hover {
        transform: translateY(-8px) scale(1.01);
        border-color: rgba(139, 92, 246, 0.28);
        box-shadow:
            0 28px 70px -38px rgba(0, 0, 0, 0.9),
            0 0 0 1px rgba(139, 92, 246, 0.08),
            0 0 42px rgba(34, 211, 238, 0.07);
    }

    .nova-sheen {
        position: relative;
        overflow: hidden;
        isolation: isolate;
    }

    .nova-sheen::after {
        content: "";
        position: absolute;
        inset: -140% auto -140% -35%;
        width: 38%;
        transform: rotate(18deg) translateX(-220%);
        background: linear-gradient(180deg, rgba(255,255,255,0), rgba(255,255,255,0.14), rgba(255,255,255,0));
        opacity: 0;
        pointer-events: none;
    }

    .nova-sheen:hover::after {
        opacity: 1;
        animation: nova-sheen 1.15s ease;
    }

    .nova-scrollbar {
        scrollbar-width: thin;
        scrollbar-color: rgba(var(--theme-accent-rgb), 0.72) rgba(255, 255, 255, 0.05);
    }

    .nova-scrollbar::-webkit-scrollbar {
        width: 10px;
    }

    .nova-scrollbar::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.04);
        border-radius: 999px;
    }

    .nova-scrollbar::-webkit-scrollbar-thumb {
        background: linear-gradient(180deg, rgba(var(--theme-accent-rgb), 0.96) 0%, rgba(var(--theme-accent-rgb), 0.68) 100%);
        border-radius: 999px;
        border: 2px solid rgba(13, 17, 32, 0.98);
    }

    .nova-scrollbar::-webkit-scrollbar-thumb:hover {
        background: linear-gradient(180deg, rgba(var(--theme-accent-rgb), 1) 0%, rgba(var(--theme-accent-rgb), 0.82) 100%);
    }

    .nova-grid-glow {
        position: relative;
    }

    .nova-grid-glow::before {
        content: "";
        position: absolute;
        inset: -1px;
        border-radius: inherit;
        background:
            radial-gradient(circle at 20% 20%, rgba(139, 92, 246, 0.18), transparent 36%),
            radial-gradient(circle at 80% 12%, rgba(34, 211, 238, 0.12), transparent 30%),
            radial-gradient(circle at 65% 86%, rgba(236, 72, 153, 0.12), transparent 30%);
        opacity: 0.8;
        z-index: -1;
        filter: blur(22px);
    }

    .nova-orbit-chip {
        position: absolute;
        z-index: 3;
        border: 1px solid rgba(255, 255, 255, 0.09);
        background: linear-gradient(180deg, rgba(18, 22, 38, 0.92) 0%, rgba(11, 14, 28, 0.84) 100%);
        box-shadow:
            0 20px 60px -36px rgba(0, 0, 0, 0.9),
            0 0 30px rgba(34, 211, 238, 0.08);
        backdrop-filter: blur(14px);
    }

    .nova-orbit-chip--violet {
        box-shadow:
            0 20px 60px -36px rgba(0, 0, 0, 0.9),
            0 0 34px rgba(139, 92, 246, 0.14);
    }

    .nova-orbit-chip--cyan {
        box-shadow:
            0 20px 60px -36px rgba(0, 0, 0, 0.9),
            0 0 34px rgba(34, 211, 238, 0.14);
    }

    .nova-orbit-chip--amber {
        box-shadow:
            0 20px 60px -36px rgba(0, 0, 0, 0.9),
            0 0 34px rgba(251, 191, 36, 0.12);
    }

    .nova-orbit-chip--delay-1 {
        animation-delay: -1.2s;
    }

    .nova-orbit-chip--delay-2 {
        animation-delay: -2.4s;
    }

    @keyframes nova-float {
        0%, 100% { transform: translate3d(0, 0, 0); }
        50% { transform: translate3d(0, -12px, 0); }
    }

    @keyframes nova-float-soft {
        0%, 100% { transform: translate3d(0, 0, 0); }
        50% { transform: translate3d(0, -7px, 0); }
    }

    @keyframes nova-pulse-glow {
        0%, 100% { box-shadow: 0 0 0 rgba(139, 92, 246, 0), 0 0 28px rgba(99, 102, 241, 0.2); }
        50% { box-shadow: 0 0 0 rgba(139, 92, 246, 0), 0 0 44px rgba(34, 211, 238, 0.24); }
    }

    @keyframes nova-sheen {
        0% { transform: rotate(18deg) translateX(-220%); }
        100% { transform: rotate(18deg) translateX(480%); }
    }

    @media (prefers-reduced-motion: reduce) {
        .nova-reveal,
        .nova-float,
        .nova-float-soft,
        .nova-pulse-glow,
        .nova-tilt-hover,
        .nova-sheen::after {
            animation: none !important;
            transition: none !important;
            transform: none !important;
            filter: none !important;
            opacity: 1 !important;
        }
    }
</style>
@if (filled(theme_setting('custom_css', 'guest')))
    <style>{!! theme_setting('custom_css', 'guest') !!}</style>
@endif
<script>
    (() => {
        const storageKey = 'appearance:guest';
        const syncKey = 'appearance:guest:sync';
        const media = window.matchMedia('(prefers-color-scheme: dark)');
        const supportsDark = @js((string) theme_setting('supports_dark_mode', 'guest', '1')) !== '0';
        const allowToggle = @js((string) theme_setting('allow_user_appearance_toggle', 'guest', '1')) !== '0';
        const defaultMode = @js(theme_setting('default_appearance', 'guest', theme_setting('appearance', 'guest', 'dark')));

        const normalizeMode = (mode) => {
            const allowedModes = supportsDark ? ['light', 'dark', 'system'] : ['light'];
            const fallback = supportsDark ? (defaultMode || 'dark') : 'light';

            return allowedModes.includes(mode) ? mode : fallback;
        };

        const syncSignature = [supportsDark ? '1' : '0', allowToggle ? '1' : '0', defaultMode || 'dark'].join('|');
        const previousSync = localStorage.getItem(syncKey);

        if (previousSync !== syncSignature) {
            localStorage.setItem(storageKey, normalizeMode(defaultMode || 'dark'));
            localStorage.setItem(syncKey, syncSignature);
        }

        const getMode = () => {
            if (!allowToggle) {
                return normalizeMode(defaultMode || 'dark');
            }

            return normalizeMode(localStorage.getItem(storageKey) || defaultMode || 'dark');
        };

        const resolve = (mode) => {
            const normalized = normalizeMode(mode);

            if (!supportsDark) {
                return false;
            }

            return normalized === 'dark' || (normalized === 'system' && media.matches);
        };

        const apply = (mode = getMode()) => {
            const normalized = getMode() === mode ? getMode() : normalizeMode(mode);
            const resolved = resolve(normalized) ? 'dark' : 'light';
            document.documentElement.classList.toggle('dark', resolved === 'dark');
            document.documentElement.dataset.themeMode = normalized;
            document.documentElement.dataset.themeResolved = resolved;

            return { mode: normalized, resolved };
        };

        window.themeMode = {
            getMode,
            getResolved: () => document.documentElement.dataset.themeResolved || (resolve(getMode()) ? 'dark' : 'light'),
            supportsDark: () => supportsDark,
            allowToggle: () => allowToggle && supportsDark,
            setMode(mode) {
                const normalized = normalizeMode(mode);

                if (allowToggle && supportsDark) {
                    localStorage.setItem(storageKey, normalized);
                }

                const state = apply(normalized);
                window.dispatchEvent(new CustomEvent('theme-mode-changed', { detail: state }));

                return state;
            },
            apply,
        };

        apply();

        media.addEventListener?.('change', () => {
            if (getMode() !== 'system') {
                return;
            }

            const state = apply('system');
            window.dispatchEvent(new CustomEvent('theme-mode-changed', { detail: state }));
        });

        document.addEventListener('livewire:navigated', () => {
            const state = apply();
            window.dispatchEvent(new CustomEvent('theme-mode-changed', { detail: state }));
        });
    })();
</script>
<script>
    (() => {
        const revealSelector = '[data-reveal]';
        let observer;

        const activate = (entry) => {
            entry.target.classList.add('nova-reveal', 'is-visible');
        };

        const initReveal = () => {
            const nodes = document.querySelectorAll(revealSelector);
            if (!nodes.length) {
                return;
            }

            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) {
                nodes.forEach((node) => node.classList.add('nova-reveal', 'is-visible'));
                return;
            }

            observer?.disconnect();
            observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) {
                        return;
                    }

                    activate(entry);
                    observer.unobserve(entry.target);
                });
            }, {
                threshold: 0.14,
                rootMargin: '0px 0px -8% 0px',
            });

            nodes.forEach((node) => {
                node.classList.add('nova-reveal');
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
{!! theme_vite('guest', ['assets/js/app.js', 'resources/themes/shared/js/highcharts.js', 'resources/themes/shared/js/image-editor.js']) !!}
