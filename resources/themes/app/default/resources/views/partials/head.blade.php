<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="{{ csrf_token() }}" />
@php
    $gaOptions = app(\Modules\AdminSettings\Support\OptionStore::class);
    $gaEnabled = (string) $gaOptions->get('google_analytics_status', '0') === '1';
    $gaMeasurementId = trim((string) $gaOptions->get('google_analytics_measurement_id', ''));
    $gaTrackApp = (string) $gaOptions->get('google_analytics_track_app', '0') === '1';
    $siteFavicon = url((string) $gaOptions->get('website_favicon', 'public/img/favicon.png'));
@endphp

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

@if ($gaEnabled && $gaTrackApp && $gaMeasurementId !== '')
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $gaMeasurementId }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', @js($gaMeasurementId));
    </script>
@endif

@include(theme_view('partials.embed-code-head', 'app'))

<link rel="icon" href="{{ $siteFavicon }}" type="image/png">
<link rel="apple-touch-icon" href="{{ $siteFavicon }}">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />
@php
    $cardRadius = theme_setting('card_radius', 'app', 16);
    $inputRadius = theme_setting('input_radius', 'app', 12);
    $buttonRadius = theme_setting('button_radius', 'app', 12);
    $pageMaxWidth = theme_setting('page_max_width', 'app', '105rem');
    $sectionSpacing = theme_setting('section_spacing', 'app', '1.5rem');
    $lightAccent = theme_setting('accent_color', 'app', '#4f46e5');
    $lightAccentRgb = theme_color_rgb('accent_color', 'app', '#4f46e5');
    $lightSidebarBg = theme_setting('sidebar_bg_color', 'app', '#f5f7fb');
    $lightSidebarBgRgb = theme_color_rgb('sidebar_bg_color', 'app', '#f5f7fb');
    $lightHeaderBg = theme_setting('header_bg_color', 'app', '#ffffff');
    $lightHeaderBgRgb = theme_color_rgb('header_bg_color', 'app', '#ffffff');
    $lightHeaderActive = theme_setting('header_active_color', 'app', '#0f172a');
    $lightHeaderActiveRgb = theme_color_rgb('header_active_color', 'app', '#0f172a');
    $lightLink = theme_setting('link_color', 'app', '#4f46e5');
    $lightLinkHover = theme_setting('link_hover_color', 'app', '#4338ca');
    $lightBorder = theme_setting('border_color', 'app', '#cbd5e1');
    $lightBorderRgb = theme_color_rgb('border_color', 'app', '#cbd5e1');
    $lightMuted = theme_setting('muted_text_color', 'app', '#64748b');
    $lightSidebarText = theme_setting('sidebar_text_color', 'app', '#475569');
    $lightHeaderText = theme_setting('header_text_color', 'app', '#0f172a');
    $lightSuccess = theme_setting('success_color', 'app', '#059669');
    $lightSuccessRgb = theme_color_rgb('success_color', 'app', '#059669');
    $lightWarning = theme_setting('warning_color', 'app', '#d97706');
    $lightWarningRgb = theme_color_rgb('warning_color', 'app', '#d97706');
    $lightDanger = theme_setting('danger_color', 'app', '#dc2626');
    $lightDangerRgb = theme_color_rgb('danger_color', 'app', '#dc2626');
    $darkAccent = theme_setting('dark_accent_color', 'app', $lightAccent);
    $darkAccentRgb = theme_color_rgb('dark_accent_color', 'app', $lightAccent);
    $darkSidebarBg = theme_setting('dark_sidebar_bg_color', 'app', '#0d131c');
    $darkSidebarBgRgb = theme_color_rgb('dark_sidebar_bg_color', 'app', '#0d131c');
    $darkHeaderBg = theme_setting('dark_header_bg_color', 'app', '#111827');
    $darkHeaderBgRgb = theme_color_rgb('dark_header_bg_color', 'app', '#111827');
    $darkHeaderActive = theme_setting('dark_header_active_color', 'app', '#f8fafc');
    $darkHeaderActiveRgb = theme_color_rgb('dark_header_active_color', 'app', '#f8fafc');
    $darkLink = theme_setting('dark_link_color', 'app', $darkAccent);
    $darkLinkHover = theme_setting('dark_link_hover_color', 'app', '#c7d2fe');
    $darkBorder = theme_setting('dark_border_color', 'app', '#334155');
    $darkBorderRgb = theme_color_rgb('dark_border_color', 'app', '#334155');
    $darkMuted = theme_setting('dark_muted_text_color', 'app', '#94a3b8');
    $darkSidebarText = theme_setting('dark_sidebar_text_color', 'app', '#cbd5e1');
    $darkHeaderText = theme_setting('dark_header_text_color', 'app', '#f8fafc');
    $darkSuccess = theme_setting('dark_success_color', 'app', '#34d399');
    $darkSuccessRgb = theme_color_rgb('dark_success_color', 'app', '#34d399');
    $darkWarning = theme_setting('dark_warning_color', 'app', '#f59e0b');
    $darkWarningRgb = theme_color_rgb('dark_warning_color', 'app', '#f59e0b');
    $darkDanger = theme_setting('dark_danger_color', 'app', '#f87171');
    $darkDangerRgb = theme_color_rgb('dark_danger_color', 'app', '#f87171');
@endphp
<style>
    :root {
        --theme-accent: {{ $lightAccent }};
        --theme-accent-rgb: {{ $lightAccentRgb }};
        --theme-sidebar-bg: {{ $lightSidebarBg }};
        --theme-sidebar-bg-rgb: {{ $lightSidebarBgRgb }};
        --theme-header-bg: {{ $lightHeaderBg }};
        --theme-header-bg-rgb: {{ $lightHeaderBgRgb }};
        --theme-header-surface: {{ $lightHeaderBg }};
        --theme-header-surface-rgb: {{ $lightHeaderBgRgb }};
        --theme-header-active: {{ $lightHeaderActive }};
        --theme-header-active-rgb: {{ $lightHeaderActiveRgb }};
        --theme-link-color: {{ $lightLink }};
        --theme-link-hover-color: {{ $lightLinkHover }};
        --theme-border-color-raw: {{ $lightBorder }};
        --theme-border-color-rgb: {{ $lightBorderRgb }};
        --theme-border-color: {{ $lightBorder }};
        --theme-shell-border-color: {{ $lightBorder }};
        --theme-muted-text-color-raw: {{ $lightMuted }};
        --theme-muted-text-color: {{ $lightMuted }};
        --theme-sidebar-text-color-raw: {{ $lightSidebarText }};
        --theme-sidebar-text-color: {{ $lightSidebarText }};
        --theme-header-text-color-raw: {{ $lightHeaderText }};
        --theme-header-text-color: {{ $lightHeaderText }};
        --theme-success-color: {{ $lightSuccess }};
        --theme-success-color-rgb: {{ $lightSuccessRgb }};
        --theme-warning-color: {{ $lightWarning }};
        --theme-warning-color-rgb: {{ $lightWarningRgb }};
        --theme-danger-color: {{ $lightDanger }};
        --theme-danger-color-rgb: {{ $lightDangerRgb }};
        --theme-surface-base: #ffffff;
        --theme-surface-base-rgb: 255, 255, 255;
        --theme-surface-soft: #f8fafc;
        --theme-surface-soft-rgb: 248, 250, 252;
        --theme-surface-overlay: #ffffff;
        --theme-surface-overlay-rgb: 255, 255, 255;
        --theme-surface-subtle: #f3f6fb;
        --theme-surface-subtle-rgb: 243, 246, 251;
        --theme-input-surface: #ffffff;
        --theme-input-surface-rgb: 255, 255, 255;
        --theme-input-text: #0f172a;
        --theme-input-placeholder: #94a3b8;
        --theme-button-soft-bg: #f1f5f9;
        --theme-button-soft-hover: #e2e8f0;
        --theme-button-soft-text: #0f172a;
        --theme-button-outline-hover: #f8fafc;
        --theme-table-head-bg: rgba(248, 250, 252, 0.92);
        --theme-table-row-hover-bg: rgba(248, 250, 252, 0.75);
        --theme-empty-bg: rgba(248, 250, 252, 0.72);
        --theme-empty-icon-bg: #ffffff;
        --theme-chart-surface: #f8fafc;
        --theme-chart-grid: rgba(148, 163, 184, 0.22);
        --theme-card-contrast-bg: #0f172a;
        --theme-card-contrast-text: #ffffff;
        --theme-app-canvas: #f6f7f9;
        --theme-app-grid: transparent;
        --theme-app-panel-shadow: none;
        --theme-card-border-width: 1px;
        --theme-card-border-color: color-mix(in srgb, var(--theme-border-color) 78%, transparent);
        --theme-divider-border-color: color-mix(in srgb, var(--theme-border-color) 68%, transparent);
        --theme-control-border-width: 1px;
        --theme-control-border-color: color-mix(in srgb, var(--theme-border-color) 84%, transparent);
        --theme-card-radius: {{ is_numeric($cardRadius) ? $cardRadius.'px' : $cardRadius }};
        --theme-input-radius: {{ is_numeric($inputRadius) ? $inputRadius.'px' : $inputRadius }};
        --theme-button-radius: {{ is_numeric($buttonRadius) ? $buttonRadius.'px' : $buttonRadius }};
        --theme-page-max-width: {{ $pageMaxWidth }};
        --theme-section-spacing: {{ $sectionSpacing }};
        --theme-font-sans: {!! theme_font_stack('app') !!};
    }

    button,
    [type="button"],
    [type="submit"],
    [type="reset"],
    [role="button"] {
        cursor: pointer;
    }

    button:disabled,
    [type="button"]:disabled,
    [type="submit"]:disabled,
    [type="reset"]:disabled,
    [role="button"][aria-disabled="true"] {
        cursor: not-allowed;
    }

    .dark {
        --theme-accent: {{ $darkAccent }};
        --theme-accent-rgb: {{ $darkAccentRgb }};
        --theme-sidebar-bg: {{ $darkSidebarBg }};
        --theme-sidebar-bg-rgb: {{ $darkSidebarBgRgb }};
        --theme-header-bg: {{ $darkHeaderBg }};
        --theme-header-bg-rgb: {{ $darkHeaderBgRgb }};
        --theme-header-surface: {{ $darkHeaderBg }};
        --theme-header-surface-rgb: {{ $darkHeaderBgRgb }};
        --theme-header-active: {{ $darkHeaderActive }};
        --theme-header-active-rgb: {{ $darkHeaderActiveRgb }};
        --theme-link-color: {{ $darkLink }};
        --theme-link-hover-color: {{ $darkLinkHover }};
        --theme-border-color-raw: {{ $darkBorder }};
        --theme-border-color-rgb: {{ $darkBorderRgb }};
        --theme-border-color: {{ $darkBorder }};
        --theme-shell-border-color: {{ $darkBorder }};
        --theme-muted-text-color-raw: {{ $darkMuted }};
        --theme-muted-text-color: {{ $darkMuted }};
        --theme-sidebar-text-color-raw: {{ $darkSidebarText }};
        --theme-sidebar-text-color: {{ $darkSidebarText }};
        --theme-header-text-color-raw: {{ $darkHeaderText }};
        --theme-header-text-color: {{ $darkHeaderText }};
        --theme-success-color: {{ $darkSuccess }};
        --theme-success-color-rgb: {{ $darkSuccessRgb }};
        --theme-warning-color: {{ $darkWarning }};
        --theme-warning-color-rgb: {{ $darkWarningRgb }};
        --theme-danger-color: {{ $darkDanger }};
        --theme-danger-color-rgb: {{ $darkDangerRgb }};
        --theme-surface-base: #162033;
        --theme-surface-base-rgb: 22, 32, 51;
        --theme-surface-soft: #121b2b;
        --theme-surface-soft-rgb: 18, 27, 43;
        --theme-surface-overlay: #182235;
        --theme-surface-overlay-rgb: 24, 34, 53;
        --theme-surface-subtle: #10192a;
        --theme-surface-subtle-rgb: 16, 25, 42;
        --theme-input-surface: #020617;
        --theme-input-surface-rgb: 2, 6, 23;
        --theme-input-text: #f8fafc;
        --theme-input-placeholder: #64748b;
        --theme-button-soft-bg: #1a2436;
        --theme-button-soft-hover: #222f46;
        --theme-button-soft-text: #f8fafc;
        --theme-button-outline-hover: #162033;
        --theme-table-head-bg: rgba(2, 6, 23, 0.72);
        --theme-table-row-hover-bg: rgba(2, 6, 23, 0.55);
        --theme-empty-bg: rgba(2, 6, 23, 0.42);
        --theme-empty-icon-bg: #0f172a;
        --theme-chart-surface: #121b2b;
        --theme-chart-grid: rgba(71, 85, 105, 0.34);
        --theme-card-contrast-bg: #ffffff;
        --theme-card-contrast-text: #0f172a;
        --theme-app-canvas: #0b1220;
        --theme-app-grid: transparent;
        --theme-app-panel-shadow: none;
    }

    .app-theme-content a.theme-link {
        color: var(--theme-link-color);
    }

    .app-theme-content a.theme-link:hover {
        color: var(--theme-link-hover-color);
    }

    .app-theme-shell {
        max-width: min(calc(100vw - 1rem), max(var(--theme-page-max-width), 88vw));
    }

    .app-theme-stack {
        gap: var(--theme-section-spacing);
    }

    html {
        scrollbar-width: thin;
        scrollbar-color: rgba(var(--theme-border-color-rgb), 0.9) color-mix(in srgb, var(--theme-surface-soft) 88%, transparent);
    }

    *::-webkit-scrollbar {
        width: 12px;
        height: 12px;
    }

    *::-webkit-scrollbar-track {
        background: color-mix(in srgb, var(--theme-surface-soft) 88%, transparent);
    }

    *::-webkit-scrollbar-thumb {
        border: 3px solid color-mix(in srgb, var(--theme-surface-soft) 88%, transparent);
        border-radius: 999px;
        background: color-mix(in srgb, var(--theme-border-color) 82%, var(--theme-surface-base) 18%);
    }

    *::-webkit-scrollbar-thumb:hover {
        background: color-mix(in srgb, var(--theme-accent) 28%, var(--theme-border-color) 72%);
    }

    *::-webkit-scrollbar-corner {
        background: color-mix(in srgb, var(--theme-surface-soft) 88%, transparent);
    }

    .app-default-body {
        background-color: var(--theme-app-canvas);
        background-image: none;
    }

    .app-default-header {
        box-shadow: none;
    }

    .app-default-header > div {
        min-height: 75px;
    }

    .app-default-sidebar-panel {
        border: 0;
        border-right: 1px solid color-mix(in srgb, var(--theme-border-color) 84%, transparent);
        border-radius: 0;
        box-shadow: none;
    }

    .app-theme-content:not(.max-w-none) {
        position: relative;
        z-index: 1;
        animation: appDefaultPageIn 420ms cubic-bezier(.16, 1, .3, 1) both;
    }

    .app-default-body .app-ui-card,
    .app-default-body .app-ui-table {
        border-color: color-mix(in srgb, var(--theme-border-color) 78%, transparent) !important;
        box-shadow: none !important;
    }

    .app-default-body .app-ui-control {
        border-color: color-mix(in srgb, var(--theme-border-color) 86%, transparent) !important;
        box-shadow: none !important;
    }

    .app-default-body .app-ui-control:hover:not(:disabled) {
        border-color: color-mix(in srgb, var(--theme-accent) 28%, var(--theme-border-color)) !important;
    }

    .app-default-body .app-ui-button {
        min-height: 40px;
        box-shadow: none !important;
        transform: none !important;
    }

    .app-default-body .app-page-hero {
        position: relative;
        border: 1px solid color-mix(in srgb, var(--theme-border-color) 76%, transparent) !important;
        isolation: isolate;
    }

    .app-default-body .app-page-hero::after {
        display: none;
    }

    .app-dashboard-board > section {
        min-width: 0;
    }

    .app-dashboard .grid > * {
        min-width: 0;
        max-width: 100%;
    }

    .app-dashboard .app-ui-table {
        max-width: 100%;
        overflow: hidden !important;
    }

    .app-dashboard .app-ui-table > div {
        max-width: 100%;
        overflow-x: auto;
    }

    @keyframes appDefaultPageIn {
        from { opacity: 0; transform: translateY(9px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @media (max-width: 1023px) {
        .app-theme-shell { max-width: none; }
    }

    @media (prefers-reduced-motion: reduce) {
        .app-theme-content:not(.max-w-none) { animation: none; }
    }

    [class*="backdrop-blur"],
    [style*="backdrop-filter"],
    [style*="-webkit-backdrop-filter"] {
        -webkit-backdrop-filter: none !important;
        backdrop-filter: none !important;
    }

    ::backdrop {
        -webkit-backdrop-filter: none !important;
        backdrop-filter: none !important;
    }
</style>
@if (filled(theme_setting('custom_css', 'app')))
    <style>{!! theme_setting('custom_css', 'app') !!}</style>
@endif
<script>
    (() => {
        const storageKey = 'appearance';
        const media = window.matchMedia('(prefers-color-scheme: dark)');
        const supportsDark = @js((string) theme_setting('supports_dark_mode', 'app', '1')) !== '0';
        const allowToggle = @js((string) theme_setting('allow_user_appearance_toggle', 'app', '1')) !== '0';
        const defaultMode = @js(theme_setting('default_appearance', 'app', theme_setting('appearance', 'app', 'system')));

        const normalizeMode = (mode) => {
            const allowedModes = supportsDark ? ['light', 'dark', 'system'] : ['light'];
            const fallback = supportsDark ? (defaultMode || 'system') : 'light';

            return allowedModes.includes(mode) ? mode : fallback;
        };

        const getMode = () => {
            if (!allowToggle) {
                return normalizeMode(defaultMode || 'system');
            }

            return normalizeMode(localStorage.getItem(storageKey) || defaultMode || 'system');
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
@if (filled(theme_setting('custom_js', 'app')))
    <script>{!! theme_setting('custom_js', 'app') !!}</script>
@endif
<link rel="stylesheet" href="{{ theme_shared_asset('plugins/fontawesome/css/fontawesome.css') }}">
<link rel="stylesheet" href="{{ theme_shared_asset('plugins/fontawesome/css/brands.css') }}">
<link rel="stylesheet" href="{{ theme_shared_asset('plugins/fontawesome/css/light.css') }}">
<link rel="stylesheet" href="{{ theme_shared_asset('plugins/fontawesome/css/regular.css') }}">
<link rel="stylesheet" href="{{ theme_shared_asset('plugins/fontawesome/css/solid.css') }}">
<link rel="stylesheet" href="{{ theme_shared_asset('plugins/fontawesome/css/thin.css') }}">
<link rel="stylesheet" href="{{ theme_shared_asset('plugins/fontawesome/css/duotone.css') }}">
<link rel="stylesheet" href="{{ theme_shared_asset('plugins/fontawesome/css/duotone-light.css') }}">
<link rel="stylesheet" href="{{ theme_shared_asset('plugins/fontawesome/css/duotone-regular.css') }}">
<link rel="stylesheet" href="{{ theme_shared_asset('plugins/fontawesome/css/duotone-thin.css') }}">
<link rel="stylesheet" href="{{ theme_shared_asset('plugins/fontawesome/css/sharp-light.css') }}">
<link rel="stylesheet" href="{{ theme_shared_asset('plugins/fontawesome/css/sharp-regular.css') }}">
<link rel="stylesheet" href="{{ theme_shared_asset('plugins/fontawesome/css/sharp-solid.css') }}">
<link rel="stylesheet" href="{{ theme_shared_asset('plugins/fontawesome/css/sharp-thin.css') }}">
<link rel="stylesheet" href="{{ theme_shared_asset('plugins/fontawesome/css/sharp-duotone-light.css') }}">
<link rel="stylesheet" href="{{ theme_shared_asset('plugins/fontawesome/css/sharp-duotone-regular.css') }}">
<link rel="stylesheet" href="{{ theme_shared_asset('plugins/fontawesome/css/sharp-duotone-solid.css') }}">
<link rel="stylesheet" href="{{ theme_shared_asset('plugins/fontawesome/css/sharp-duotone-thin.css') }}">
<link rel="stylesheet" href="{{ theme_shared_asset('plugins/fontawesome/css/chisel-regular.css') }}">
<link rel="stylesheet" href="{{ theme_shared_asset('plugins/fontawesome/css/etch-solid.css') }}">
<link rel="stylesheet" href="{{ theme_shared_asset('plugins/fontawesome/css/graphite-thin.css') }}">
<link rel="stylesheet" href="{{ theme_shared_asset('plugins/fontawesome/css/jelly-regular.css') }}">
<link rel="stylesheet" href="{{ theme_shared_asset('plugins/fontawesome/css/jelly-fill-regular.css') }}">
<link rel="stylesheet" href="{{ theme_shared_asset('plugins/fontawesome/css/jelly-duo-regular.css') }}">
<link rel="stylesheet" href="{{ theme_shared_asset('plugins/fontawesome/css/notdog-solid.css') }}">
<link rel="stylesheet" href="{{ theme_shared_asset('plugins/fontawesome/css/notdog-duo-solid.css') }}">
<link rel="stylesheet" href="{{ theme_shared_asset('plugins/fontawesome/css/slab-regular.css') }}">
<link rel="stylesheet" href="{{ theme_shared_asset('plugins/fontawesome/css/slab-press-regular.css') }}">
<link rel="stylesheet" href="{{ theme_shared_asset('plugins/fontawesome/css/thumbprint-light.css') }}">
<link rel="stylesheet" href="{{ theme_shared_asset('plugins/fontawesome/css/utility-semibold.css') }}">
<link rel="stylesheet" href="{{ theme_shared_asset('plugins/fontawesome/css/utility-fill-semibold.css') }}">
<link rel="stylesheet" href="{{ theme_shared_asset('plugins/fontawesome/css/utility-duo-semibold.css') }}">
<link rel="stylesheet" href="{{ theme_shared_asset('plugins/fontawesome/css/whiteboard-semibold.css') }}">
<link rel="stylesheet" href="{{ theme_shared_asset('plugins/flags/flag-icon.css') }}">
@livewireStyles
<script src="{{ theme_shared_asset('plugins/highcharts/highcharts.js') }}"></script>
<script>
    (() => {
        if (window.StackpostsHighcharts || !window.Highcharts) {
            return;
        }

        const cssVar = (name, fallback) => getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback;

        window.StackpostsHighcharts = {
            lib: window.Highcharts,
            render: (elOrId, options = {}) => {
                const element = typeof elOrId === 'string' ? document.getElementById(elOrId) : elOrId;

                if (!element) {
                    return null;
                }

                return window.Highcharts.chart(element, window.Highcharts.merge({
                    chart: {
                        backgroundColor: 'transparent',
                        style: {
                            fontFamily: cssVar('--theme-font-sans', 'Inter, sans-serif'),
                        },
                    },
                    title: { text: null },
                    credits: { enabled: false },
                    xAxis: {
                        labels: {
                            style: {
                                color: cssVar('--theme-muted-text-color', '#64748b'),
                            },
                        },
                    },
                    yAxis: {
                        title: { text: null },
                        labels: {
                            style: {
                                color: cssVar('--theme-muted-text-color', '#64748b'),
                            },
                        },
                    },
                    legend: {
                        itemStyle: {
                            color: cssVar('--theme-header-text-color', '#0f172a'),
                        },
                    },
                }, options));
            },
        };

        window.dispatchEvent(new CustomEvent('stackposts:highcharts-ready'));
    })();
</script>

{!! theme_vite('app', ['assets/js/app.js', 'resources/themes/shared/js/highcharts.js', 'resources/themes/shared/js/image-editor.js']) !!}

<link rel="stylesheet" href="{{ theme_asset('assets/css/workspace.css', 'app') }}?v={{ filemtime(resource_path('themes/app/default/assets/css/workspace.css')) }}">
