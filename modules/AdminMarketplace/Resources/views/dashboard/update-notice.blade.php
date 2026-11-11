<section
    id="marketplace-dashboard-update-notice"
    data-endpoint="{{ $endpoint }}"
    class="hidden overflow-hidden rounded-[1.4rem] border p-4 lg:p-5"
    style="border-color: rgba(245,158,11,0.26); background:
        radial-gradient(circle at top right, rgba(245,158,11,0.12), transparent 32%),
        linear-gradient(180deg, rgba(255,251,235,0.96), rgba(255,248,235,0.94));"
>
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div class="space-y-3">
            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.22em]" style="border-color: rgba(245,158,11,0.26); background: rgba(245,158,11,0.14); color: rgb(146 64 14);">
                    <i class="fa-light fa-arrows-rotate text-[11px]"></i>
                    {{ __('New update') }}
                </span>
                <span
                    id="marketplace-dashboard-update-notice-count"
                    class="inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-semibold"
                    style="border-color: rgba(245,158,11,0.2); color: rgb(120 53 15); background: rgba(255,255,255,0.72);"
                ></span>
            </div>

            <div id="marketplace-dashboard-update-notice-items" class="space-y-2"></div>
            <p id="marketplace-dashboard-update-notice-more" class="hidden text-sm" style="color: rgb(146 64 14);">
                {{ __('Additional updates are available in Marketplace.') }}
            </p>
        </div>

        <div class="flex shrink-0 flex-wrap items-center gap-3">
            <x-ui.button :href="route('admin-marketplace.index')" variant="warning" wire:navigate>
                {{ __('Open marketplace') }}
            </x-ui.button>
            <x-ui.button :href="route('admin-marketplace.packages.index')" variant="outline" wire:navigate>
                {{ __('View packages') }}
            </x-ui.button>
        </div>
    </div>
</section>

<script>
    (() => {
        const root = document.getElementById('marketplace-dashboard-update-notice');
        if (!root || root.dataset.bound === '1') return;
        root.dataset.bound = '1';

        const endpoint = root.dataset.endpoint;
        const countNode = document.getElementById('marketplace-dashboard-update-notice-count');
        const itemsNode = document.getElementById('marketplace-dashboard-update-notice-items');
        const moreNode = document.getElementById('marketplace-dashboard-update-notice-more');

        const render = (notice) => {
            const count = Number(notice?.count || 0);
            const items = Array.isArray(notice?.items) ? notice.items : [];

            if (count < 1) {
                root.classList.add('hidden');
                return;
            }

            root.classList.remove('hidden');

            if (countNode) {
                countNode.textContent = count === 1
                    ? '1 package has a newer version available.'
                    : `${count} packages have newer versions available.`;
            }

            if (itemsNode) {
                itemsNode.innerHTML = items.map((item) => `
                    <div class="flex flex-wrap items-center gap-2 text-sm" style="color: rgb(120 53 15);">
                        <span class="font-semibold">${item.title || ''}</span>
                        <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs" style="border-color: rgba(180,83,9,0.18); background: rgba(255,255,255,0.7);">
                            ${item.installed_version || ''} → ${item.latest_version || ''}
                        </span>
                    </div>
                `).join('');
            }

            if (moreNode) {
                moreNode.classList.toggle('hidden', count <= items.length);
            }
        };

        const fetchNotice = async () => {
            if (window.adminMarketplaceUpdateNoticeStore?.fetch) {
                return window.adminMarketplaceUpdateNoticeStore.fetch();
            }

            const response = await fetch(endpoint, {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin',
            });
            const json = await response.json().catch(() => ({}));

            if (!response.ok || json.status === 0) {
                return {};
            }

            return json.data || {};
        };

        const load = async () => {
            try {
                render(await fetchNotice());
            } catch (_) {
                root.classList.add('hidden');
            }
        };

        const bootLoad = () => window.setTimeout(load, 1200);

        if ('requestIdleCallback' in window) {
            window.requestIdleCallback(bootLoad, { timeout: 2500 });
        } else {
            bootLoad();
        }

        window.setInterval(load, 300000);
    })();
</script>
