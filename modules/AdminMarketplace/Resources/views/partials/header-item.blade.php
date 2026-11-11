@php
    $isActive = request()->routeIs('admin-marketplace.*');
    $itemClasses = $isActive
        ? 'inline-flex h-11 w-11 items-center justify-center rounded-[0.75rem] border text-white shadow-[0_12px_24px_-18px_rgba(var(--theme-header-active-rgb),0.45)] transition'
        : 'inline-flex h-11 w-11 items-center justify-center rounded-[0.75rem] border text-slate-500 transition hover:bg-slate-50 hover:text-slate-950 dark:text-slate-400 dark:hover:bg-slate-800/80 dark:hover:text-white';
    $itemStyle = $isActive
        ? 'background-color: var(--theme-header-active); border-color: rgba(var(--theme-header-active-rgb), 0.22);'
        : 'background-color: rgba(var(--theme-header-surface-rgb), 0.9); border-color: var(--theme-shell-border-color);';
@endphp

<div
    x-data="{ open: false }"
    class="relative inline-flex"
    x-on:mouseenter="open = true"
    x-on:mouseleave="open = false"
    x-on:focusin="open = true"
    x-on:focusout="open = false"
>
    <a
        href="{{ route('admin-marketplace.index') }}"
        class="relative {{ $itemClasses }}"
        style="{{ $itemStyle }}"
        aria-label="{{ __('Marketplace') }}"
        wire:navigate
    >
        <i class="fa-light fa-store text-[15px]"></i>
        <span
            id="admin-marketplace-header-badge"
            class="absolute -right-1 -top-1 inline-flex h-5 w-5 items-center justify-center rounded-full bg-amber-500 text-[11px] font-semibold leading-none text-white shadow-sm"
            style="display: none;"
            aria-label="{{ __('Checking marketplace updates...') }}"
        >
            <span id="admin-marketplace-header-badge-count">0</span>
        </span>
    </a>

    <div
        x-cloak
        x-show="open"
        x-transition.opacity.duration.120ms
        class="pointer-events-none absolute left-1/2 top-full z-[80] mt-2 -translate-x-1/2"
        style="max-width: calc(100vw - 1.5rem);"
    >
        <div
            class="relative inline-flex rounded-[0.8rem] px-3 py-2 text-[0.82rem] font-medium leading-5 text-white shadow-[0_18px_40px_-20px_rgba(15,23,42,0.55)]"
            style="background-color: rgba(15, 15, 18, 0.96);"
        >
            <span class="absolute -top-1 left-1/2 h-2.5 w-2.5 -translate-x-1/2 rotate-45 rounded-[2px]" style="background-color: rgba(15, 15, 18, 0.96);"></span>
            <div id="admin-marketplace-header-tooltip-content" class="whitespace-nowrap">{{ __('Marketplace') }}</div>
        </div>
    </div>
</div>

@once
    <script>
        window.adminMarketplaceUpdateNoticeStore = window.adminMarketplaceUpdateNoticeStore || {
            endpoint: @js(route('admin-marketplace.dashboard.update-notice')),
            cached: null,
            cachedAt: 0,
            pending: null,
            ttl: 300000,
            async fetch(force = false) {
                const now = Date.now();

                if (!force && this.cached && (now - this.cachedAt) < this.ttl) {
                    return this.cached;
                }

                if (!force && this.pending) {
                    return this.pending;
                }

                this.pending = fetch(this.endpoint, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                })
                    .then(async (response) => {
                        if (!response.ok) {
                            return this.cached || {};
                        }

                        const result = await response.json().catch(() => ({}));
                        const data = result?.data || {};

                        this.cached = data;
                        this.cachedAt = Date.now();

                        return data;
                    })
                    .finally(() => {
                        this.pending = null;
                    });

                return this.pending;
            },
        };

        window.adminMarketplaceHeaderUpdates = window.adminMarketplaceHeaderUpdates || {
            endpoint: @js(route('admin-marketplace.dashboard.update-notice')),
            intervalId: null,
            marketplaceLabel: @js(__('Marketplace')),
            updatesAvailableLabel: @js(__('Updates available')),
            updateBadge(data) {
                const badge = document.getElementById('admin-marketplace-header-badge');
                const badgeCount = document.getElementById('admin-marketplace-header-badge-count');
                const tooltipContent = document.getElementById('admin-marketplace-header-tooltip-content');

                if (!badge || !badgeCount || !tooltipContent) {
                    return;
                }

                const count = Number(data?.count || 0);
                const tooltip = count > 0
                    ? `<div class="whitespace-nowrap text-[0.84rem] font-semibold text-white">${this.updatesAvailableLabel}</div>`
                    : `<div class="whitespace-nowrap text-[0.84rem] font-semibold text-white">${this.marketplaceLabel}</div>`;

                badgeCount.textContent = count;
                badge.style.display = count > 0 ? 'inline-flex' : 'none';
                badge.setAttribute('aria-label', count > 0 ? this.updatesAvailableLabel : this.marketplaceLabel);
                tooltipContent.innerHTML = tooltip;
            },
            async refresh() {
                try {
                    const data = await window.adminMarketplaceUpdateNoticeStore.fetch();
                    this.updateBadge(data || {});
                } catch (error) {
                    console.warn('Marketplace header update check failed.', error);
                }
            },
            start() {
                const bootRefresh = () => window.setTimeout(() => this.refresh(), 1200);

                if ('requestIdleCallback' in window) {
                    window.requestIdleCallback(bootRefresh, { timeout: 2500 });
                } else {
                    bootRefresh();
                }

                if (this.intervalId) {
                    return;
                }

                this.intervalId = window.setInterval(() => this.refresh(), 300000);
            },
        };

        document.addEventListener('DOMContentLoaded', () => {
            window.adminMarketplaceHeaderUpdates?.start();
        });

        document.addEventListener('livewire:navigated', () => {
            window.adminMarketplaceHeaderUpdates?.refresh();
        });
    </script>
@endonce
