@props([
    'sections' => [],
    'area' => 'user',
    'workspaceName' => null,
    'footer' => null,
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
    $sidebarLogoDarkUrl = $resolveBrandAssetUrl((string) get_option('website_logo_dark', ''), theme_asset('assets/img/logo-dark.png', 'app'));
    $sidebarLogoLightUrl = $resolveBrandAssetUrl((string) get_option('website_logo_light', ''), theme_asset('assets/img/logo-light.png', 'app'));
    $sidebarLogoFallback = theme_asset('assets/img/logo-brand-dark.png', 'app');
@endphp

<aside
    class="app-default-sidebar fixed inset-y-0 left-0 z-[140] hidden overflow-visible transition-[width] duration-[520ms] ease-[cubic-bezier(0.16,1,0.3,1)] will-change-[width] lg:block"
    x-bind:class="!sidebarPanelExpanded ? 'w-[70px] min-w-[70px] max-w-[70px]' : 'w-[16rem] min-w-[16rem] max-w-[16rem]'"
>
    <style>
        .app-sidebar-scroll {
            scrollbar-gutter: stable;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .app-sidebar-scroll::-webkit-scrollbar {
            display: none;
        }
    </style>

    <div
        class="app-default-sidebar-panel flex h-full w-full flex-col overflow-visible bg-[var(--theme-sidebar-bg)] transition-[box-shadow] duration-[520ms] ease-[cubic-bezier(0.16,1,0.3,1)] dark:bg-[var(--theme-sidebar-bg)]"
        style="color: var(--theme-sidebar-text-color); border-color: var(--theme-border-color);"
    >
        <div class="sp-sidebar-brand relative h-16 px-5 py-3" x-bind:class="sidebarPanelExpanded ? 'px-5 py-4' : 'px-3 py-4'">
            <a
                href="{{ $homeHref ?: route('portal.dashboard') }}"
                wire:navigate
                class="flex h-10 items-center rounded-[0.85rem] outline-none transition hover:opacity-85 focus-visible:ring-2 focus-visible:ring-[var(--theme-accent)] focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--theme-sidebar-bg)]"
                x-bind:class="sidebarPanelExpanded ? 'justify-start' : 'justify-center'"
                aria-label="{{ __('Open dashboard') }}"
            >
                <div x-cloak x-show="sidebarContentVisible">
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
                </div>

                <div x-cloak x-show="!sidebarContentVisible">
                    <img
                        x-show="appearanceResolved !== 'dark'"
                        src="{{ $sidebarLogoDarkUrl }}"
                        alt="{{ config('app.name', 'Stackposts') }}"
                        class="block h-8 w-8 max-w-none shrink-0"
                        onerror="this.onerror=null;this.src='{{ $sidebarLogoFallback }}';"
                    >
                    <img
                        x-show="appearanceResolved === 'dark'"
                        src="{{ $sidebarLogoLightUrl }}"
                        alt="{{ config('app.name', 'Stackposts') }}"
                        class="block h-8 w-8 max-w-none shrink-0"
                        onerror="this.onerror=null;this.src='{{ $sidebarLogoFallback }}';"
                    >
                </div>
            </a>

            <button
                type="button"
                class="absolute -right-3 top-1/2 inline-flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded-md border border-slate-300/80 bg-white text-slate-500 shadow-[0_6px_14px_-12px_rgba(15,23,42,0.2)] transition hover:border-slate-400 hover:bg-slate-50 hover:text-slate-800 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-slate-600 dark:hover:text-slate-200"
                x-on:click="toggleSidebar"
                x-bind:aria-label="sidebarCollapsed ? 'Expand menu' : 'Collapse menu'" x-bind:title="sidebarCollapsed ? 'Expand menu' : 'Collapse menu'"
            >
                <i class="fa-light text-[7px]" x-bind:class="sidebarCollapsed ? 'fa-angles-right' : 'fa-angles-left'"></i>
            </button>
        </div>

        <div class="app-sidebar-scroll flex-1 overflow-y-auto px-3 pt-2 pb-2">
            <x-layout.sidebar-menu :sections="$sections" mode="desktop" />
        </div>

        @if ($footer)
            <div class="border-t border-slate-300/70 dark:border-slate-800" style="border-color: var(--theme-border-color);" x-bind:class="sidebarContentVisible ? 'p-4' : 'flex justify-center p-1.5'">
                {{ $footer }}
            </div>
        @endif
    </div>
</aside>
