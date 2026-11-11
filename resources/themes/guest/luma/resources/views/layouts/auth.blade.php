<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ current_locale_direction() }}">
<head>
    @include(theme_view('partials.head', 'guest'))
    @livewireStyles

    <style>
        .lm-auth-v2 {
            --lm-auth-ink: #101010;
            --lm-auth-muted: #6f7178;
            --lm-auth-paper: #fff;
            --lm-auth-soft: #f7f7f8;
            --lm-auth-line: #e8e8eb;
            --lm-auth-accent: var(--theme-accent, #ff6b6b);
            --lm-auth-accent-rgb: var(--theme-accent-rgb, 255, 107, 107);
            min-height: 100vh;
            margin: 0;
            overflow-x: hidden;
            background: var(--lm-auth-paper) !important;
            color: var(--lm-auth-ink) !important;
            font-family: var(--theme-font-sans, Inter, ui-sans-serif, system-ui, sans-serif);
        }

        .lm-auth-v2 *, .lm-auth-v2 *::before, .lm-auth-v2 *::after { box-sizing: border-box; }
        .lm-auth-v2 a { color: inherit; text-decoration: none; }

        .lm-auth-shell { position: relative; display: grid; grid-template-columns: minmax(0, .92fr) minmax(480px, 1.08fr); min-height: 100vh; }
        .lm-auth-aside { position: relative; display: flex; flex-direction: column; min-width: 0; overflow: hidden; padding: 42px clamp(38px, 5vw, 76px) 48px; border-right: 1px solid var(--lm-auth-line); background: color-mix(in srgb, var(--lm-auth-soft) 62%, var(--lm-auth-paper)); }
        .lm-auth-aside::before { content: ""; position: absolute; top: -120px; left: -130px; width: 420px; height: 420px; border-radius: 50%; background: radial-gradient(circle, rgba(var(--lm-auth-accent-rgb), .12), transparent 68%); }
        .lm-auth-aside::after { content: ""; position: absolute; right: -180px; bottom: -210px; width: 520px; height: 520px; border-radius: 50%; border: 1px solid color-mix(in srgb, var(--lm-auth-accent) 18%, var(--lm-auth-line)); opacity: .7; }
        .lm-auth-brand { position: relative; z-index: 2; display: inline-flex; align-items: center; width: max-content; }
        .lm-auth-brand img { display: block; width: auto; max-width: 190px; height: 36px; object-fit: contain; }
        .lm-auth-story { position: relative; z-index: 2; max-width: 620px; margin: auto 0; padding: 64px 0; }
        .lm-auth-eyebrow { display: inline-flex; align-items: center; gap: 8px; min-height: 29px; padding: 0 11px; border: 1px solid color-mix(in srgb, var(--lm-auth-accent) 24%, var(--lm-auth-line)); border-radius: 999px; background: color-mix(in srgb, var(--lm-auth-accent) 8%, var(--lm-auth-paper)); color: var(--lm-auth-muted); font-size: 10px; font-weight: 800; letter-spacing: .09em; text-transform: uppercase; }
        .lm-auth-eyebrow i { color: var(--lm-auth-accent); }
        .lm-auth-title { max-width: 670px; margin: 25px 0 0; font-size: clamp(42px, 4.7vw, 68px); line-height: 1.01; letter-spacing: -.058em; font-weight: 800; }
        .lm-auth-copy { max-width: 590px; margin: 20px 0 0; color: var(--lm-auth-muted); font-size: 16px; line-height: 1.75; }

        .lm-auth-flow { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; margin-top: 38px; }
        .lm-auth-flow-card { position: relative; min-height: 154px; padding: 18px; border: 1px solid var(--lm-auth-line); border-radius: 15px; background: color-mix(in srgb, var(--lm-auth-paper) 92%, transparent); box-shadow: 0 22px 44px -38px rgba(0,0,0,.3); }
        .lm-auth-flow-icon { display: inline-flex; flex: 0 0 36px; align-items: center; justify-content: center; width: 36px; height: 36px; margin: 0; border-radius: 10px; background: color-mix(in srgb, var(--lm-auth-accent) 10%, var(--lm-auth-paper)); color: var(--lm-auth-accent); font-size: 15px; line-height: 1; }
        .lm-auth-flow-icon > i { display: block; width: 1em; height: 1em; margin: 0; font-size: 1em; line-height: 1; text-align: center; }
        .lm-auth-flow-card strong { display: block; margin-top: 24px; font-size: 12px; }
        .lm-auth-flow-card > div > span { display: block; margin-top: 6px; color: var(--lm-auth-muted); font-size: 10px; line-height: 1.55; }
        .lm-auth-disclaimer { position: relative; z-index: 2; display: flex; align-items: center; gap: 8px; color: var(--lm-auth-muted); font-size: 11px; }
        .lm-auth-disclaimer i { color: var(--lm-auth-accent); }

        .lm-auth-main { position: relative; display: grid; place-items: center; min-width: 0; padding: 36px clamp(24px, 5vw, 72px); background: var(--lm-auth-paper); }
        .lm-auth-main::before { content: ""; position: absolute; top: 12%; right: -130px; width: 360px; height: 360px; border-radius: 50%; background: radial-gradient(circle, rgba(var(--lm-auth-accent-rgb), .075), transparent 70%); pointer-events: none; }
        .lm-auth-card-wrap { position: relative; z-index: 2; width: min(100%, 510px); }
        .lm-auth-mobile-head { display: none; align-items: center; justify-content: space-between; gap: 18px; margin-bottom: 28px; }
        .lm-auth-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 28px; }
        .lm-auth-back { display: inline-flex; align-items: center; gap: 8px; color: var(--lm-auth-muted); font-size: 12px; font-weight: 700; transition: color .2s ease; }
        .lm-auth-back:hover { color: var(--lm-auth-ink); }
        .lm-auth-tools { display: flex; align-items: center; gap: 8px; }
        .lm-auth-card { width: 100%; padding: clamp(26px, 4vw, 42px); border: 1px solid var(--lm-auth-line); border-radius: 19px; background: var(--lm-auth-paper); box-shadow: 0 30px 80px -55px rgba(0,0,0,.42); }
        .lm-auth-window-dots { display: flex; gap: 6px; margin-bottom: 28px; }
        .lm-auth-window-dot { width: 8px; height: 8px; border-radius: 50%; background: #d8d8dc; }
        .lm-auth-window-dot:nth-child(1) { background: #ff6b6b; }
        .lm-auth-window-dot:nth-child(2) { background: #f5bd4f; }
        .lm-auth-window-dot:nth-child(3) { background: #61c454; }
        .lm-auth-legal { display: flex; align-items: center; justify-content: center; flex-wrap: wrap; gap: 10px 16px; margin-top: 20px; color: var(--lm-auth-muted); font-size: 11px; font-weight: 650; }
        .lm-auth-legal a:hover { color: var(--lm-auth-ink); }
        .lm-auth-legal-dot { width: 3px; height: 3px; border-radius: 50%; background: var(--lm-auth-line); }

        .lm-auth-v2 .lb-serif { font-family: var(--theme-font-sans, Inter, ui-sans-serif, system-ui, sans-serif) !important; font-weight: 800; letter-spacing: -.045em; }
        .lm-auth-v2 .lb-copy { color: var(--lm-auth-muted) !important; }
        .lm-auth-v2 .lb-button { border: 1px solid #111 !important; border-radius: 12px !important; background: #111 !important; color: #fff !important; box-shadow: 0 16px 30px -22px rgba(0,0,0,.68) !important; transition: transform .2s ease, box-shadow .2s ease, background .2s ease; }
        .lm-auth-v2 .lb-button:hover { transform: translateY(-2px); background: #292929 !important; box-shadow: 0 20px 36px -22px rgba(0,0,0,.72) !important; }
        .lm-auth-v2 input[type='text'], .lm-auth-v2 input[type='email'], .lm-auth-v2 input[type='password'] { min-height: 48px !important; border-width: 1px !important; border-color: var(--lm-auth-line) !important; border-radius: 11px !important; background: var(--lm-auth-soft) !important; color: var(--lm-auth-ink) !important; box-shadow: none !important; }
        .lm-auth-v2 input:focus { border-color: var(--lm-auth-accent) !important; background: var(--lm-auth-paper) !important; box-shadow: 0 0 0 4px rgba(var(--lm-auth-accent-rgb), .09) !important; }
        .lm-auth-v2 label { color: var(--lm-auth-ink) !important; }
        .lm-auth-v2 .theme-logo-dark, .lm-auth-v2 .theme-logo-light { max-height: 36px; }

        .lm-auth-motion .lm-auth-brand,
        .lm-auth-motion .lm-auth-story > *,
        .lm-auth-motion .lm-auth-flow-card,
        .lm-auth-motion .lm-auth-disclaimer,
        .lm-auth-motion .lm-auth-card-wrap { opacity: 0; }
        .lm-auth-motion .lm-auth-brand { animation: lmAuthEnter .7s cubic-bezier(.2,.72,.2,1) .04s forwards; }
        .lm-auth-motion .lm-auth-story > .lm-auth-eyebrow { animation: lmAuthEnter .66s cubic-bezier(.2,.72,.2,1) .12s forwards; }
        .lm-auth-motion .lm-auth-title { animation: lmAuthEnter .78s cubic-bezier(.2,.72,.2,1) .2s forwards; }
        .lm-auth-motion .lm-auth-copy { animation: lmAuthEnter .72s cubic-bezier(.2,.72,.2,1) .3s forwards; }
        .lm-auth-motion .lm-auth-flow { opacity: 1; }
        .lm-auth-motion .lm-auth-flow-card:nth-child(1) { animation: lmAuthEnter .68s cubic-bezier(.2,.72,.2,1) .4s forwards; }
        .lm-auth-motion .lm-auth-flow-card:nth-child(2) { animation: lmAuthEnter .68s cubic-bezier(.2,.72,.2,1) .48s forwards; }
        .lm-auth-motion .lm-auth-flow-card:nth-child(3) { animation: lmAuthEnter .68s cubic-bezier(.2,.72,.2,1) .56s forwards; }
        .lm-auth-motion .lm-auth-disclaimer { animation: lmAuthEnter .66s cubic-bezier(.2,.72,.2,1) .64s forwards; }
        .lm-auth-motion .lm-auth-card-wrap { animation: lmAuthPanel .84s cubic-bezier(.16,.76,.2,1) .15s forwards; }
        .lm-auth-window-dot { animation: lmAuthDot 3.2s ease-in-out infinite; }
        .lm-auth-window-dot:nth-child(2) { animation-delay: .2s; }
        .lm-auth-window-dot:nth-child(3) { animation-delay: .4s; }

        @keyframes lmAuthEnter { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes lmAuthPanel { from { opacity: 0; transform: translateX(24px) scale(.985); } to { opacity: 1; transform: translateX(0) scale(1); } }
        @keyframes lmAuthDot { 0%,70%,100% { transform: scale(1); } 78% { transform: scale(1.32); } }

        html[data-theme-resolved='dark'] .lm-auth-v2 { --lm-auth-ink: #f4f4f5; --lm-auth-muted: #a1a1aa; --lm-auth-paper: #0b0d10; --lm-auth-soft: #14171c; --lm-auth-line: #24282f; background: var(--lm-auth-paper) !important; color: var(--lm-auth-ink) !important; }
        html[data-theme-resolved='dark'] .lm-auth-v2 .lb-button { border-color: #f4f4f5 !important; background: #f4f4f5 !important; color: #111 !important; }
        html[data-theme-resolved='dark'] .lm-auth-v2 input[type='text'], html[data-theme-resolved='dark'] .lm-auth-v2 input[type='email'], html[data-theme-resolved='dark'] .lm-auth-v2 input[type='password'] { background: var(--lm-auth-soft) !important; color: var(--lm-auth-ink) !important; }

        @media (max-width: 1100px) {
            .lm-auth-shell { grid-template-columns: minmax(0, .8fr) minmax(450px, 1.2fr); }
            .lm-auth-flow { grid-template-columns: 1fr; }
            .lm-auth-flow-card { display: flex; align-items: center; gap: 14px; min-height: auto; }
            .lm-auth-flow-card strong { margin-top: 0; }
        }

        @media (max-width: 860px) {
            .lm-auth-shell { display: block; }
            .lm-auth-aside { display: none; }
            .lm-auth-main { min-height: 100vh; padding: 28px 20px; }
            .lm-auth-mobile-head { display: flex; }
            .lm-auth-toolbar { display: none; }
            .lm-auth-card { padding: 28px; }
        }

        @media (max-width: 520px) {
            .lm-auth-main { align-items: start; padding: 22px 14px 30px; }
            .lm-auth-card { padding: 24px 18px; border-radius: 15px; }
            .lm-auth-brand img { max-width: 150px; height: 30px; }
        }

        @media (prefers-reduced-motion: reduce) {
            .lm-auth-motion .lm-auth-brand,
            .lm-auth-motion .lm-auth-story > *,
            .lm-auth-motion .lm-auth-flow-card,
            .lm-auth-motion .lm-auth-disclaimer,
            .lm-auth-motion .lm-auth-card-wrap { opacity: 1 !important; transform: none !important; animation: none !important; }
            .lm-auth-window-dot { animation: none !important; }
        }
    </style>
</head>

@php
    $brandOptions = app(\Modules\AdminSettings\Support\OptionStore::class);
    $authSiteTitle = trim((string) $brandOptions->get('website_title', config('app.name', 'Your Company Name')));
    $authDarkLogoPath = (string) ($brandOptions->get('website_logo_brand_dark') ?: $brandOptions->get('website_logo_dark') ?: $brandOptions->get('website_logo') ?: 'public/img/logo-brand-dark.png');
    $authLightLogoPath = (string) ($brandOptions->get('website_logo_brand_light') ?: $brandOptions->get('website_logo_light') ?: $brandOptions->get('website_logo') ?: 'public/img/logo-brand-light.png');
    $resolvedAuthDarkLogo = url($authDarkLogoPath);
    $resolvedAuthLightLogo = url($authLightLogoPath);
@endphp

<body class="lm-auth-v2 lm-auth-motion">
    <main class="lm-auth-shell">
        <section class="lm-auth-aside">
            <a href="{{ route('home') }}" class="lm-auth-brand" wire:navigate>
                <img src="{{ $resolvedAuthDarkLogo }}" alt="{{ $authSiteTitle }}" class="theme-logo-dark">
                <img src="{{ $resolvedAuthLightLogo }}" alt="{{ $authSiteTitle }}" class="theme-logo-light">
            </a>

            <div class="lm-auth-story">
                <span class="lm-auth-eyebrow"><i class="fa-light fa-sparkles"></i>{{ __('Your social publishing workspace') }}</span>
                <h1 class="lm-auth-title">{{ __('Keep every social channel moving from one place.') }}</h1>
                <p class="lm-auth-copy">{{ __('Return to your calendar, connected profiles, AI content tools, publishing workflow, and supported analytics.') }}</p>

                <div class="lm-auth-flow">
                    @foreach ([
                        ['fa-calendar-days', __('Plan'), __('Organize drafts and scheduled posts.')],
                        ['fa-paper-plane-top', __('Publish'), __('Prepare content for connected channels.')],
                        ['fa-chart-line', __('Analyze'), __('Review supported performance data.')],
                    ] as $item)
                        <article class="lm-auth-flow-card"><span class="lm-auth-flow-icon"><i class="fa-light {{ $item[0] }}"></i></span><div><strong>{{ $item[1] }}</strong><span>{{ $item[2] }}</span></div></article>
                    @endforeach
                </div>
            </div>

            <p class="lm-auth-disclaimer"><i class="fa-light fa-shield-check"></i>{{ __('Workspace access follows your account and plan permissions.') }}</p>
        </section>

        <section class="lm-auth-main">
            <div class="lm-auth-card-wrap">
                <div class="lm-auth-mobile-head">
                    <a href="{{ route('home') }}" class="lm-auth-brand" wire:navigate>
                        <img src="{{ $resolvedAuthDarkLogo }}" alt="{{ $authSiteTitle }}" class="theme-logo-dark">
                        <img src="{{ $resolvedAuthLightLogo }}" alt="{{ $authSiteTitle }}" class="theme-logo-light">
                    </a>
                    <div class="lm-auth-tools">@include(theme_view('partials.appearance-toggle', 'guest')) @include(theme_view('partials.auth-language-switcher', 'guest'))</div>
                </div>

                <div class="lm-auth-toolbar">
                    <a href="{{ route('home') }}" class="lm-auth-back" wire:navigate><i class="fa-light fa-arrow-left"></i>{{ __('Back to home') }}</a>
                    <div class="lm-auth-tools">@include(theme_view('partials.appearance-toggle', 'guest')) @include(theme_view('partials.auth-language-switcher', 'guest'))</div>
                </div>

                <div class="lm-auth-card">
                    <div class="lm-auth-window-dots"><span class="lm-auth-window-dot"></span><span class="lm-auth-window-dot"></span><span class="lm-auth-window-dot"></span></div>
                    {{ $slot }}
                </div>

                <div class="lm-auth-legal">
                    <a href="{{ route('guest.privacy-policy') }}" wire:navigate>{{ __('Privacy Policy') }}</a>
                    <span class="lm-auth-legal-dot"></span>
                    <a href="{{ route('guest.terms-of-use') }}" wire:navigate>{{ __('Terms of Use') }}</a>
                </div>
            </div>
        </section>
    </main>

    @include(theme_view('partials.embed-code-body', 'guest'))
    @livewireScripts
</body>
</html>
