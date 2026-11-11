<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include(theme_view('partials.head', 'guest'))
        @livewireStyles
        <style>
            html[data-theme-resolved='light'] .auth-brand-logo-dark {
                display: block;
            }

            html[data-theme-resolved='light'] .auth-brand-logo-light {
                display: none;
            }

            html[data-theme-resolved='dark'] .auth-brand-logo-light {
                display: block;
            }

            html[data-theme-resolved='dark'] .auth-brand-logo-dark {
                display: none;
            }
        </style>
    </head>
    @php
        $brandOptions = app(\Modules\AdminSettings\Support\OptionStore::class);
        $authLogoDarkPath = (string) ($brandOptions->get('website_logo_brand_dark')
            ?: $brandOptions->get('website_logo_dark')
            ?: 'public/img/logo-brand-dark.png');
        $authLogoLightPath = (string) ($brandOptions->get('website_logo_brand_light')
            ?: $brandOptions->get('website_logo_light')
            ?: 'public/img/logo-brand-light.png');
        $resolvedAuthLogoDark = url($authLogoDarkPath);
        $resolvedAuthLogoLight = url($authLogoLightPath);
        $fallbackAuthLogoDark = url('public/img/logo-brand-dark.png');
        $fallbackAuthLogoLight = url('public/img/logo-brand-light.png');
    @endphp
    <body class="min-h-screen antialiased" style="background-color: var(--theme-body-bg); color: var(--theme-header-text-color); font-family: var(--theme-font-sans);">
        <div class="relative min-h-screen overflow-hidden px-6 py-8 lg:px-8">
            <div class="absolute inset-0" style="background: var(--theme-top-bg);"></div>
            <div class="absolute inset-0 opacity-[0.2]" style="background-image: radial-gradient(circle at 22% 18%, rgba(139,92,246,0.14), transparent 24%), radial-gradient(circle at 82% 14%, rgba(34,211,238,0.14), transparent 22%);"></div>
            <div class="absolute left-[8%] top-[12%] h-64 w-64 rounded-full bg-violet-500/10 blur-3xl"></div>
            <div class="absolute right-[10%] top-[18%] h-64 w-64 rounded-full bg-cyan-400/10 blur-3xl"></div>

            <div class="relative mx-auto flex min-h-[calc(100vh-4rem)] max-w-5xl flex-col">
                <div class="flex flex-1 items-center justify-center">
                    <div class="w-full max-w-[34rem]">
                        <div class="mx-auto mb-8 flex w-full max-w-[34rem] items-start justify-between gap-4">
                            <a href="{{ route('home') }}" class="flex items-center gap-4 text-white/80 transition hover:text-white" wire:navigate>
                                <img src="{{ $resolvedAuthLogoDark }}" alt="{{ config('app.name', 'StackPosts') }}" class="auth-brand-logo-dark block h-11 w-auto object-contain" onerror="this.onerror=null;this.src='{{ $fallbackAuthLogoDark }}';">
                                <img src="{{ $resolvedAuthLogoLight }}" alt="{{ config('app.name', 'StackPosts') }}" class="auth-brand-logo-light block h-11 w-auto object-contain" onerror="this.onerror=null;this.src='{{ $fallbackAuthLogoLight }}';">
                            </a>

                            <div class="flex items-center gap-3">
                                @include(theme_view('partials.appearance-toggle', 'guest'))
                                @include(theme_view('partials.auth-language-switcher', 'guest'))
                            </div>
                        </div>

                        <div class="relative overflow-hidden rounded-[2.2rem] border p-6 backdrop-blur sm:p-8 lg:p-10" style="border-color: var(--theme-panel-border); background: var(--theme-panel-bg); box-shadow: var(--theme-panel-shadow), inset 0 1px 0 rgba(255,255,255,0.08);">
                            <div class="absolute inset-x-0 top-0 h-20 opacity-60" style="background: radial-gradient(circle at top, rgba(99,102,241,0.12), transparent 62%);"></div>
                            {{ $slot }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @include(theme_view('partials.embed-code-body', 'guest'))
        @livewireScripts
    </body>
</html>
