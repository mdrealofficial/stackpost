@props([
    'sections' => [],
    'dashboardSwitch' => null,
    'planCard' => null,
    'homeHref' => null,
])

@php
    $storageDriverManager = app(\App\Support\Storage\StorageDriverManager::class);
    $activeBrandDisk = $storageDriverManager->activeDisk();

    $resolveBrandAssetUrl = static function (string $value, string $fallback) use ($storageDriverManager, $activeBrandDisk): string {
        $value = trim($value);

        if ($value === '' || str_starts_with($value, 'public/img/')) {
            return $fallback;
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

        if (preg_match('#^media/(public|amazon|wasabi|contabo)/(.+)$#', $path, $matches)) {
            $url = $storageDriverManager->publicUrl($matches[1], $matches[2]);

            return $query && $url ? $url.'?'.$query : ($url ?: $fallback);
        }

        if (str_starts_with($path, 'files/')) {
            $url = $storageDriverManager->publicUrl($activeBrandDisk, $path);

            return $query && $url ? $url.'?'.$query : ($url ?: $fallback);
        }

        return url($path).($query ? '?'.$query : '');
    };

    $sidebarBrandDarkUrl = $resolveBrandAssetUrl((string) get_option('website_logo_brand_dark', ''), theme_asset('assets/img/logo-brand-dark.png', 'app'));
    $sidebarBrandLightUrl = $resolveBrandAssetUrl((string) get_option('website_logo_brand_light', ''), theme_asset('assets/img/logo-brand-light.png', 'app'));
    $sidebarLogoFallback = theme_asset('assets/img/logo-brand-dark.png', 'app');
@endphp

<div
    x-cloak
    x-show="sidebarOpen"
    class="fixed inset-0 lg:hidden"
    style="z-index: 140;"
    x-on:keydown.escape.window="sidebarOpen = false"
>
    <div class="absolute inset-0 bg-slate-950/60" x-on:click="sidebarOpen = false"></div>

    <div
        class="relative flex h-full w-[16rem] max-w-[85vw] flex-col border-r border-slate-200 bg-white shadow-2xl dark:border-slate-800 dark:bg-slate-950"
        style="z-index: 141; color: var(--theme-sidebar-text-color); border-color: var(--theme-border-color); background: var(--theme-sidebar-bg);"
        x-data="{ sidebarContentVisible: true, sidebarCollapsed: false, sidebarPanelExpanded: true }"
    >
        <div class="sp-sidebar-brand relative h-16 px-5 py-3">
            <a
                href="{{ $homeHref ?: route('portal.dashboard') }}"
                wire:navigate
                class="flex h-10 items-center justify-start rounded-[0.85rem] outline-none transition hover:opacity-85 focus-visible:ring-2 focus-visible:ring-[var(--theme-accent)] focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--theme-sidebar-bg)]"
                aria-label="{{ __('Open dashboard') }}"
                x-on:click="sidebarOpen = false"
            >
                <img
                    x-show="appearanceResolved !== 'dark'"
                    src="{{ $sidebarBrandDarkUrl }}"
                    alt="{{ config('app.name', 'Stackposts') }}"
                    class="block h-7 w-auto max-w-[180px] shrink-0"
                    onerror="this.onerror=null;this.src='{{ $sidebarLogoFallback }}';"
                >
                <img
                    x-show="appearanceResolved === 'dark'"
                    src="{{ $sidebarBrandLightUrl }}"
                    alt="{{ config('app.name', 'Stackposts') }}"
                    class="block h-7 w-auto max-w-[180px] shrink-0"
                    onerror="this.onerror=null;this.src='{{ $sidebarLogoFallback }}';"
                >
            </a>

            <button
                type="button"
                class="absolute -right-3 top-1/2 inline-flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded-md border border-slate-300/80 bg-white text-slate-500 shadow-[0_6px_14px_-12px_rgba(15,23,42,0.2)] transition hover:border-slate-400 hover:bg-slate-50 hover:text-slate-800 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-slate-600 dark:hover:text-slate-200"
                x-on:click="sidebarOpen = false"
                aria-label="{{ __('Close main menu') }}" title="{{ __('Close menu') }}"
            >
                <i class="fa-light fa-xmark text-[11px]"></i>
            </button>
        </div>

        <div class="app-sidebar-scroll min-h-0 flex-1 overflow-y-auto px-3 pt-2 pb-3">
            <x-layout.sidebar-menu :sections="$sections" mode="desktop" />

            @if ($dashboardSwitch || $planCard)
                <div class="mt-4 border-t pt-4" style="border-color: var(--theme-border-color);">
                    @if ($dashboardSwitch)
                        <x-ui.button
                            :href="$dashboardSwitch['route']"
                            class="w-full justify-center"
                            size="sm"
                            wire:navigate
                        >
                            <i class="fa-light fa-arrow-right-arrow-left mr-2 text-[12px]"></i>
                            {{ __($dashboardSwitch['label']) }}
                        </x-ui.button>
                    @endif

                    @if ($planCard)
                        <div
                            class="mt-3 rounded-2xl border p-3"
                            style="border-color: var(--theme-border-color); background: color-mix(in srgb, var(--theme-sidebar-bg) 84%, white 16%);"
                        >
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Your plan') }}</p>

                            <div class="mt-3 flex items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold" style="color: var(--theme-header-text-color);">
                                        <i class="fa-solid fa-crown mr-1 text-[11px]"></i>{{ $planCard['name'] }}
                                    </p>
                                    <div class="mt-2 flex items-center gap-1 text-xs whitespace-nowrap" style="color: var(--theme-muted-text-color);">
                                        <span>{{ __('Expire:') }}</span>
                                        <span class="font-semibold" style="color: var(--theme-header-text-color);">{{ $planCard['expiry'] }}</span>
                                    </div>
                                </div>

                                <span class="inline-flex shrink-0 items-center rounded-full px-2.5 py-1 text-[11px] font-semibold whitespace-nowrap {{ $planCard['badge_tone'] === 'success' ? 'bg-emerald-400/12 text-emerald-500' : 'bg-slate-400/10 text-slate-500 dark:text-slate-300' }}">
                                    {{ $planCard['badge'] }}
                                </span>
                            </div>

                            <div class="mt-3 flex items-center justify-between gap-2 text-xs">
                                <span style="color: var(--theme-muted-text-color);">{{ __('Plan credits used') }}</span>
                                <span style="color: var(--theme-header-text-color);">{{ $planCard['credits_used_label'] }} / {{ $planCard['credits_limit_label'] }}</span>
                            </div>

                            <div class="mt-2 h-2 overflow-hidden rounded-full" style="background: color-mix(in srgb, var(--theme-sidebar-bg) 72%, black 28%);">
                                <div
                                    class="h-full rounded-full"
                                    style="width: {{ $planCard['unlimited'] ? 100 : ($planCard['credits_percent'] ?? 0) }}%; background-color: var(--theme-accent,#2563eb);"
                                ></div>
                            </div>

                            <x-ui.button href="{{ $planCard['details_route'] }}" class="mt-4 w-full justify-center" size="sm" wire:navigate>
                                {{ __('Upgrade / Details') }}
                            </x-ui.button>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
