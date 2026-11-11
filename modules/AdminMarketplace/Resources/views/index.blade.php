@php
    $metricCards = [
        ['label' => __('Installed packages'), 'value' => number_format($summary['total']), 'description' => __('Modules currently managed by Marketplace.'), 'icon' => 'fa-light fa-boxes-stacked', 'tone' => 'var(--theme-accent)'],
        ['label' => __('Active packages'), 'value' => number_format($summary['active']), 'description' => __('Packages currently booted through the marketplace provider bootstrap file.'), 'icon' => 'fa-light fa-toggle-on', 'tone' => 'rgb(5 150 105)'],
        ['label' => __('Catalog products'), 'value' => number_format($catalogSummary['total'] ?? 0), 'description' => __('Marketplace-ready products currently available in the synced catalog.'), 'icon' => 'fa-light fa-store', 'tone' => 'rgb(59 130 246)'],
        ['label' => __('Featured products'), 'value' => number_format($catalogSummary['featured'] ?? 0), 'description' => __('Featured releases highlighted in the marketplace catalog.'), 'icon' => 'fa-light fa-sparkles', 'tone' => 'rgb(217 119 6)'],
    ];
    $openInstallModal = request()->boolean('install') || $errors->has('module_zip') || $errors->has('purchase_code');
    $installMethod = old('install_method', $errors->has('module_zip') ? 'zip' : 'purchase');
@endphp

<div class="space-y-6">
        @if (session('status'))
            <x-ui.alert variant="success" :title="__('Updated')" :description="session('status')" dismissible />
        @endif

        @if ($errors->has('marketplace'))
            <x-ui.alert :title="__('Marketplace error')" :description="$errors->first('marketplace')" variant="danger" dismissible />
        @endif

        @if (! $catalogConfigured)
            <x-ui.alert
                :title="__('Catalog sync is unavailable')"
                :description="__('Marketplace can still manage installed packages, but the remote catalog source is currently unavailable so products could not be loaded.')"
                variant="warning"
                dismissible
            />
        @endif

        <x-ui.page-hero
            :eyebrow="__('Settings')"
            :title="__('Marketplace')"
            :description="__('Manage installed modules and browse the product catalog. Customers can add products to cart and buy directly from this page.')"
            :count="$summary['total']"
            icon="fa-light fa-store"
        />

        <div class="sticky top-[4.75rem] z-30 mb-6 xl:top-[4.75rem]">
                <div
                    class="flex w-full flex-wrap items-center justify-end gap-2 rounded-3xl border-x border-b px-4 py-3"
                    style="border-color: var(--theme-border-color); background: rgba(255,255,255,0.96);"
                >
                        <x-ui.modal
                            width="lg"
                            :title="__('Install package')"
                            :description="__('Install with a purchase code or upload a ZIP package manually.')"
                            :initially-open="$openInstallModal"
                        >
                            <x-slot:trigger>
                                <x-ui.button type="button" size="sm">
                                    <i class="fa-light fa-cloud-arrow-down text-sm"></i>
                                    {{ __('Install') }}
                                </x-ui.button>
                            </x-slot:trigger>

                            <div x-data="{ method: '{{ $installMethod }}', submitting: false }">
                                <form id="marketplace-install-form-inline" method="POST" action="{{ route('admin-marketplace.install') }}" enctype="multipart/form-data" class="space-y-5" x-on:submit="submitting = true">
                                    @csrf
                                    <input type="hidden" name="install_method" x-model="method">

                                    <div class="grid grid-cols-2 gap-3 rounded-2xl border p-2" style="border-color: var(--theme-border-color); background: rgba(15,23,42,0.02);">
                                        <button
                                            type="button"
                                            x-on:click="method = 'purchase'"
                                            class="rounded-2xl px-4 py-3 text-sm font-medium transition"
                                            :style="method === 'purchase'
                                                ? 'background: var(--theme-accent); color: white;'
                                                : 'background: transparent; color: var(--theme-header-text-color);'"
                                        >
                                            {{ __('Purchase code') }}
                                        </button>
                                        <button
                                            type="button"
                                            x-on:click="method = 'zip'"
                                            class="rounded-2xl px-4 py-3 text-sm font-medium transition"
                                            :style="method === 'zip'
                                                ? 'background: var(--theme-accent); color: white;'
                                                : 'background: transparent; color: var(--theme-header-text-color);'"
                                        >
                                            {{ __('ZIP file') }}
                                        </button>
                                    </div>

                                    <div x-show="method === 'purchase'" x-cloak class="space-y-3">
                                        <x-ui.input
                                            id="purchase_code"
                                            name="purchase_code"
                                            :label="__('Purchase code')"
                                            :placeholder="__('Enter your Envato purchase code')"
                                            :value="old('purchase_code')"
                                            :error="$errors->first('purchase_code')"
                                        />
                                        <div class="rounded-2xl border px-4 py-3 text-sm" style="border-color: var(--theme-border-color); background: rgba(59,130,246,0.04); color: var(--theme-muted-text-color);">
                                            {{ __('Use this when you want to verify a purchase code and install the package directly from the marketplace.') }}
                                        </div>
                                    </div>

                                    <div x-show="method === 'zip'" x-cloak class="space-y-3">
                                        <div class="space-y-2">
                                            <label for="module_zip" class="text-sm font-medium" style="color: var(--theme-header-text-color);">{{ __('Module ZIP') }}</label>
                                            <input
                                                id="module_zip"
                                                name="module_zip"
                                                type="file"
                                                accept=".zip"
                                                class="block w-full rounded-2xl border px-4 py-3 text-sm"
                                                style="border-color: var(--theme-border-color); background: var(--theme-card-background); color: var(--theme-header-text-color);"
                                            >
                                            <p class="text-xs" style="color: var(--theme-muted-text-color);">{{ __('The ZIP must extract into modules/YourModuleName and include module.json at the root of that folder.') }}</p>
                                            @error('module_zip')
                                                <p class="text-sm text-rose-600">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="flex justify-end gap-3 border-t pt-5" style="border-color: var(--theme-border-color);">
                                        <x-ui.button type="button" variant="outline" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                                        <x-ui.button type="submit" x-bind:disabled="submitting">
                                            <span x-show="!submitting">{{ __('Install package') }}</span>
                                            <span x-show="submitting" x-cloak>{{ __('Installing...') }}</span>
                                        </x-ui.button>
                                    </div>
                                </form>
                            </div>
                        </x-ui.modal>
                        <x-ui.button :href="route('admin-marketplace.packages.index')" variant="outline" size="sm" wire:navigate>
                            <i class="fa-light fa-boxes-stacked text-sm"></i>
                            {{ __('Packages') }}
                        </x-ui.button>

                        @if ($storefrontApiBase)
                            <div class="relative" id="marketplace-cart-shell">
                                <button
                                    type="button"
                                    id="marketplace-cart-toggle"
                                    class="relative inline-flex h-10 w-10 items-center justify-center rounded-full border shadow-sm"
                                    style="border-color: var(--theme-border-color); background: var(--theme-card-background); color: var(--theme-header-text-color);"
                                    aria-expanded="false"
                                    aria-controls="marketplace-cart-dropdown"
                                >
                                    <i class="fa-light fa-cart-shopping text-base"></i>
                                    <span
                                        id="marketplace-cart-count"
                                        class="absolute -right-1 -top-1 inline-flex min-w-[1.4rem] items-center justify-center rounded-full px-1.5 py-0.5 text-[11px] font-semibold"
                                        style="background: var(--theme-accent); color: white;"
                                    >
                                        0
                                    </span>
                                </button>

                                <div
                                    id="marketplace-cart-dropdown"
                                    class="hidden absolute right-0 z-20 mt-3 w-[360px] max-w-[calc(100vw-2rem)] rounded-3xl border p-4 shadow-[0_24px_80px_-36px_rgba(15,23,42,0.4)] backdrop-blur-sm"
                                    style="border-color: var(--theme-border-color); background: rgba(255,255,255,0.98);"
                                >
                                    <div class="space-y-4">
                                        <div class="flex items-center justify-between gap-3">
                                            <div>
                                                <p class="text-xs uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Cart') }}</p>
                                                <p class="text-base font-semibold" style="color: var(--theme-header-text-color);">{{ __('Your cart') }}</p>
                                            </div>
                                            <span class="text-xs font-medium" style="color: var(--theme-muted-text-color);">
                                                <span id="marketplace-cart-quantity">0</span> {{ __('items') }}
                                            </span>
                                        </div>

                                        <div id="marketplace-cart-items" class="max-h-[280px] space-y-3 overflow-y-auto pr-1"></div>

                                        <div class="grid gap-3 sm:grid-cols-3">
                                            <div class="rounded-2xl px-3 py-3" style="background: rgba(59,130,246,0.08);">
                                                <p class="text-[11px] uppercase tracking-[0.14em]" style="color: var(--theme-muted-text-color);">{{ __('Subtotal') }}</p>
                                                <p class="mt-2 text-sm font-semibold" style="color: var(--theme-header-text-color);" id="marketplace-cart-subtotal">$0.00</p>
                                            </div>
                                            <div class="rounded-2xl px-3 py-3" style="background: rgba(16,185,129,0.08);">
                                                <p class="text-[11px] uppercase tracking-[0.14em]" style="color: var(--theme-muted-text-color);">{{ __('Discount') }}</p>
                                                <p class="mt-2 text-sm font-semibold" style="color: var(--theme-header-text-color);" id="marketplace-cart-discount">$0.00</p>
                                            </div>
                                            <div class="rounded-2xl px-3 py-3" style="background: rgba(245,158,11,0.08);">
                                                <p class="text-[11px] uppercase tracking-[0.14em]" style="color: var(--theme-muted-text-color);">{{ __('Total') }}</p>
                                                <p class="mt-2 text-sm font-semibold" style="color: var(--theme-header-text-color);" id="marketplace-cart-total">$0.00</p>
                                            </div>
                                        </div>

                                        <div class="border-t pt-4" style="border-color: var(--theme-border-color);">
                                            <div class="grid gap-2 {{ $shopAppUrl ? 'grid-cols-2' : 'grid-cols-1' }}">
                                                <x-ui.dialog :title="__('Clear cart?')" :description="__('This will remove all items from the cart.')" width="sm" dismissible>
                                                    <x-slot:trigger>
                                                        <x-ui.button type="button" variant="danger" size="sm" class="w-full justify-center">
                                                            <i class="fa-light fa-trash text-sm"></i>
                                                            {{ __('Clear') }}
                                                        </x-ui.button>
                                                    </x-slot:trigger>

                                                    <x-slot:footer>
                                                        <div class="flex justify-end gap-3">
                                                            <x-ui.button type="button" variant="outline" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                                                            <x-ui.button type="button" variant="danger" id="marketplace-cart-clear" x-on:click="open = false">{{ __('Clear') }}</x-ui.button>
                                                        </div>
                                                    </x-slot:footer>
                                                </x-ui.dialog>
                                                @if ($shopAppUrl)
                                                    <x-ui.button type="button" data-open-shop-cart size="sm" class="w-full justify-center">
                                                        <i class="fa-light fa-bag-shopping text-sm"></i>
                                                        {{ __('Buy now') }}
                                                    </x-ui.button>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                </div>
        </div>

        <x-ui.metric-strip :items="$metricCards" :show-progress="false" columns="md:grid-cols-2 xl:grid-cols-4" />

        <x-ui.card>
            <div class="space-y-4">
                    <div class="space-y-3">
                        <div class="space-y-3">
                            <div class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em]" style="border-color: rgba(16,185,129,0.18); background: rgba(16,185,129,0.06); color: rgb(6 95 70);">
                                <i class="fa-light fa-store"></i>
                                <span>{{ __('Catalog') }}</span>
                            </div>

                            <div class="space-y-2">
                                <h2 class="text-[1.7rem] font-semibold tracking-[-0.03em] leading-tight" style="color: var(--theme-header-text-color);">{{ __('Product catalog') }}</h2>
                                <p class="max-w-3xl text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Customers can browse products and add them to cart directly on this page.') }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-4 pt-2 md:grid-cols-[minmax(0,1fr)_240px_auto] md:items-center">
                        <div>
                            <x-ui.input
                                wire:model.live.debounce.250ms="catalogSearch"
                                :label="false"
                                :placeholder="__('Product, module, category...')"
                            />
                        </div>

                        <div>
                            <x-ui.select wire:model.live="catalogCategory" :label="false">
                                <option value="all">{{ __('All categories') }}</option>
                                @foreach ($catalogCategories as $category)
                                    <option value="{{ $category['id'] }}">{{ $category['name'] }}</option>
                                @endforeach
                            </x-ui.select>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <x-ui.button type="button" variant="outline" wire:click="resetCatalogFilters">{{ __('Clear') }}</x-ui.button>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            wire:click="toggleCatalogFeatured"
                            class="inline-flex items-center justify-center rounded-full border px-3 py-2 text-sm font-medium"
                            style="border-color: {{ $catalogFilters['featured'] === 'featured' ? 'var(--theme-accent)' : 'var(--theme-border-color)' }}; color: {{ $catalogFilters['featured'] === 'featured' ? 'var(--theme-accent)' : 'var(--theme-header-text-color)' }};"
                        >
                            {{ __('Featured only') }}
                        </button>

                        <button
                            type="button"
                            wire:click="toggleCatalogType('main')"
                            class="inline-flex items-center justify-center rounded-full border px-3 py-2 text-sm font-medium"
                            style="border-color: {{ $catalogFilters['type'] === 'main' ? 'var(--theme-accent)' : 'var(--theme-border-color)' }}; color: {{ $catalogFilters['type'] === 'main' ? 'var(--theme-accent)' : 'var(--theme-header-text-color)' }};"
                        >
                            {{ __('Main only') }}
                        </button>

                        <button
                            type="button"
                            wire:click="toggleCatalogType('addon')"
                            class="inline-flex items-center justify-center rounded-full border px-3 py-2 text-sm font-medium"
                            style="border-color: {{ $catalogFilters['type'] === 'addon' ? 'var(--theme-accent)' : 'var(--theme-border-color)' }}; color: {{ $catalogFilters['type'] === 'addon' ? 'var(--theme-accent)' : 'var(--theme-header-text-color)' }};"
                        >
                            {{ __('Addons only') }}
                        </button>
                    </div>
            </div>
        </x-ui.card>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($catalogProducts as $product)
                <x-ui.card class="h-full overflow-hidden">
                    <div class="flex h-full flex-col space-y-5">
                        <div class="rounded-[1.6rem] border p-5" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background:
                            radial-gradient(circle at top right, rgba(59,130,246,0.12), transparent 35%),
                            linear-gradient(180deg, color-mix(in srgb, var(--theme-surface-base) 96%, rgba(16,185,129,0.04)), color-mix(in srgb, var(--theme-surface-overlay) 94%, rgba(59,130,246,0.03)));">
                            <div class="flex items-start gap-4">
                                <div class="relative h-20 w-20 shrink-0 overflow-hidden rounded-[1.2rem] border shadow-[0_18px_40px_-28px_rgba(15,23,42,0.22)]" style="border-color: rgba(var(--theme-border-color-rgb), 0.72); background:
                                    linear-gradient(135deg, rgba(255,255,255,0.92), rgba(239,246,255,0.92));">
                                    <div class="flex h-full w-full items-center justify-center text-3xl" style="color: var(--theme-muted-text-color); {{ $product['thumbnail_url'] ? 'display:none;' : '' }}" data-product-thumbnail-fallback>
                                        <i class="fa-light fa-cubes"></i>
                                    </div>
                                    @if ($product['thumbnail_url'])
                                        <img
                                            src="{{ $product['thumbnail_url'] }}"
                                            alt=""
                                            class="absolute inset-0 h-full w-full object-contain p-3"
                                            loading="lazy"
                                            decoding="async"
                                            onerror="this.style.display='none'; const fallback = this.parentElement.querySelector('[data-product-thumbnail-fallback]'); if (fallback) fallback.style.display='flex';"
                                        >
                                    @endif
                                </div>

                                <div class="min-w-0 flex-1 space-y-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <a
                                                href="{{ route('admin-marketplace.products.show', $product['slug']) }}"
                                                wire:navigate
                                                class="transition hover:opacity-80"
                                                style="color: var(--theme-header-text-color);"
                                            >
                                                <h3 class="text-[1.18rem] font-semibold leading-7 tracking-[-0.02em]">{{ $product['name'] }}</h3>
                                            </a>
                                            <p class="mt-1 truncate text-[11px] font-mono uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ $product['module_name'] ?: $product['slug'] }}</p>
                                        </div>
                                        @if ($product['version'])
                                            <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold" style="background: rgba(var(--theme-accent-rgb), 0.1); color: var(--theme-accent);">
                                                {{ $product['version'] }}
                                            </span>
                                        @endif
                                    </div>

                                    <div class="flex flex-wrap items-center gap-2">
                                        @if ($product['category_name'])
                                            <x-ui.badge variant="neutral">{{ $product['category_name'] }}</x-ui.badge>
                                        @endif
                                        @if ($product['is_featured'])
                                            <x-ui.badge variant="warning">{{ __('Featured') }}</x-ui.badge>
                                        @endif
                                        @if ((int) $product['status'] !== 1)
                                            <x-ui.badge variant="danger">{{ __('Unavailable') }}</x-ui.badge>
                                        @endif
                                        @if ($product['has_update'])
                                            <x-ui.badge variant="warning">{{ __('New update') }}</x-ui.badge>
                                        @endif
                                    </div>

                                    <div class="flex flex-wrap items-center gap-2">
                                        <x-ui.button :href="route('admin-marketplace.products.show', $product['slug'])" variant="outline" size="sm" wire:navigate>
                                            <i class="fa-light fa-arrow-up-right-and-arrow-down-left-from-center text-xs"></i>
                                            {{ __('Detail') }}
                                        </x-ui.button>

                                        @if ($product['demo_url'])
                                            <x-ui.button :href="$product['demo_url']" variant="outline" size="sm" target="_blank">
                                                <i class="fa-light fa-play text-xs"></i>
                                                {{ __('Demo') }}
                                            </x-ui.button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <p class="text-sm leading-7" style="color: var(--theme-muted-text-color);">{{ \Illuminate\Support\Str::limit($product['description'], 135) }}</p>
                        </div>

                        <div class="mt-auto space-y-5">
                        <div class="grid gap-2 sm:grid-cols-2">
                            <div class="rounded-2xl px-4 py-3" style="background: rgba(59,130,246,0.08);">
                                <p class="text-xs uppercase tracking-[0.16em]" style="color: var(--theme-muted-text-color);">{{ __('Regular') }}</p>
                                <p class="mt-2 text-lg font-semibold" style="color: var(--theme-header-text-color);">${{ number_format($product['price_regular_license'], 2) }}</p>
                            </div>
                            <div class="rounded-2xl px-4 py-3" style="background: rgba(16,185,129,0.08);">
                                <p class="text-xs uppercase tracking-[0.16em]" style="color: var(--theme-muted-text-color);">{{ __('Extended') }}</p>
                                <p class="mt-2 text-lg font-semibold" style="color: var(--theme-header-text-color);">${{ number_format($product['price_extended_license'], 2) }}</p>
                            </div>
                        </div>

                        <div class="space-y-3 border-t pt-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.62);">
                            @if ($product['matched_package']?->is_active)
                                @if ($product['has_update'] && $product['matched_package'])
                                    <form method="POST" action="{{ route('admin-marketplace.update', $product['matched_package']->id_secure) }}">
                                        @csrf
                                        <x-ui.button type="submit" variant="warning" class="inline-flex min-h-11 w-full items-center justify-center rounded-[1rem] px-4 py-3 text-sm font-semibold">
                                            <i class="fa-light fa-arrows-rotate text-sm"></i>
                                            {{ __('Update') }}
                                        </x-ui.button>
                                    </form>
                                @else
                                    <span class="inline-flex min-h-11 w-full items-center justify-center rounded-[1rem] border px-4 py-3 text-sm font-medium uppercase tracking-[0.16em]" style="border-color: rgba(16,185,129,0.28); color: rgb(6, 95, 70); background: rgba(16,185,129,0.08);">
                                        {{ __('INSTALLED') }}
                                    </span>
                                @endif
                            @elseif ($product['is_envato'] && $product['buy_url'] && (int) $product['status'] === 1)
                                <div>
                                    <x-ui.button :href="$product['buy_url']" class="inline-flex min-h-11 w-full items-center justify-center rounded-[1rem] px-4 py-3 text-sm font-semibold" target="_blank">
                                        <i class="fa-brands fa-envira text-sm"></i>
                                        {{ __('Buy on Envato') }}
                                    </x-ui.button>
                                </div>
                            @elseif ($storefrontApiBase && (int) $product['status'] === 1)
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <button
                                        type="button"
                                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-[1rem] px-4 py-3 text-sm font-semibold"
                                        style="background: var(--theme-accent); color: white; box-shadow: 0 16px 36px -24px rgba(var(--theme-accent-rgb), 0.55);"
                                        data-storefront-add
                                        data-product-id="{{ $product['id'] }}"
                                        data-license-type="regular"
                                    >
                                        <i class="fa-light fa-cart-plus text-sm"></i>
                                        {{ __('Add Regular') }}
                                    </button>

                                    <button
                                        type="button"
                                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-[1rem] border px-4 py-3 text-sm font-semibold"
                                        style="border-color: rgba(var(--theme-border-color-rgb), 0.72); color: var(--theme-header-text-color); background: color-mix(in srgb, var(--theme-surface-base) 94%, transparent);"
                                        data-storefront-add
                                        data-product-id="{{ $product['id'] }}"
                                        data-license-type="extended"
                                    >
                                        <i class="fa-light fa-bag-shopping-plus text-sm"></i>
                                        {{ __('Add Extended') }}
                                    </button>
                                </div>
                            @elseif ((int) $product['status'] !== 1)
                                <span class="inline-flex items-center justify-center rounded-[1rem] border px-4 py-3 text-sm font-medium" style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color);">
                                    {{ __('Not available for purchase') }}
                                </span>
                            @endif

                            <div class="flex flex-wrap items-center gap-2">
                                @if ($product['has_update'] && $product['matched_package'] && ! $product['matched_package']?->is_active)
                                    <form method="POST" action="{{ route('admin-marketplace.update', $product['matched_package']->id_secure) }}">
                                        @csrf
                                        <x-ui.button type="submit" variant="warning" size="sm">
                                            <i class="fa-light fa-arrows-rotate text-xs"></i>
                                            {{ __('Update') }}
                                        </x-ui.button>
                                    </form>
                                @endif

                                @if ($product['buy_url'] && ! $storefrontApiBase && ! $product['is_envato'])
                                    <x-ui.button :href="$product['buy_url']" size="sm" target="_blank">
                                        {{ __('Buy') }}
                                    </x-ui.button>
                                @endif
                            </div>
                        </div>
                        </div>
                    </div>
                </x-ui.card>
            @empty
                <div class="md:col-span-2 xl:col-span-3">
                    <x-ui.empty
                        icon="fa-light fa-store-slash"
                        :title="__('No marketplace products matched the current filters.')"
                        :description="__('Adjust the filters, try a broader search, or verify that the catalog source is connected and responding.')"
                    />
                </div>
            @endforelse
        </div>

        @if (method_exists($catalogProducts, 'hasPages') && $catalogProducts->hasPages())
            <div class="pt-3">
                <div class="flex flex-col items-center justify-between gap-4 rounded-[1.4rem] border px-4 py-4 md:flex-row" style="border-color: rgba(var(--theme-border-color-rgb), 0.65); background: color-mix(in srgb, var(--theme-surface-base) 96%, rgba(59,130,246,0.03));">
                    <div class="inline-flex items-center rounded-full px-3 py-2 text-sm" style="background: rgba(var(--theme-accent-rgb), 0.08); color: var(--theme-muted-text-color);">
                        {{ __('Showing :from to :to of :total results', [
                            'from' => number_format($catalogProducts->firstItem()),
                            'to' => number_format($catalogProducts->lastItem()),
                            'total' => number_format($catalogProducts->total()),
                        ]) }}
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            wire:click="previousPage"
                            @disabled(! $catalogProducts->onFirstPage())
                            class="inline-flex h-11 w-11 items-center justify-center rounded-[1rem] border transition"
                            style="border-color: rgba(var(--theme-border-color-rgb), 0.72); color: {{ $catalogProducts->onFirstPage() ? 'var(--theme-muted-text-color)' : 'var(--theme-header-text-color)' }}; background: {{ $catalogProducts->onFirstPage() ? 'rgba(148,163,184,0.08)' : 'var(--theme-card-background)' }};"
                        >
                            <i class="fa-light fa-chevron-left text-sm"></i>
                        </button>

                        <div class="flex items-center gap-2">
                            @foreach ($catalogProducts->getUrlRange(1, $catalogProducts->lastPage()) as $page => $url)
                                <button
                                    type="button"
                                    wire:click="gotoPage({{ $page }})"
                                    class="inline-flex h-11 min-w-11 items-center justify-center rounded-[1rem] border px-3 text-sm font-semibold transition"
                                    style="border-color: {{ $catalogProducts->currentPage() === $page ? 'transparent' : 'rgba(var(--theme-border-color-rgb), 0.72)' }}; background: {{ $catalogProducts->currentPage() === $page ? 'var(--theme-accent)' : 'var(--theme-card-background)' }}; color: {{ $catalogProducts->currentPage() === $page ? 'white' : 'var(--theme-header-text-color)' }};"
                                >
                                    {{ $page }}
                                </button>
                            @endforeach
                        </div>

                        <button
                            type="button"
                            wire:click="nextPage"
                            @disabled(! $catalogProducts->hasMorePages())
                            class="inline-flex h-11 w-11 items-center justify-center rounded-[1rem] border transition"
                            style="border-color: rgba(var(--theme-border-color-rgb), 0.72); color: {{ $catalogProducts->hasMorePages() ? 'var(--theme-header-text-color)' : 'var(--theme-muted-text-color)' }}; background: {{ $catalogProducts->hasMorePages() ? 'var(--theme-card-background)' : 'rgba(148,163,184,0.08)' }};"
                        >
                            <i class="fa-light fa-chevron-right text-sm"></i>
                        </button>
                    </div>
                </div>
            </div>
        @endif

    </div>

@if ($storefrontApiBase)
    <script>
            (() => {
                const apiBase = @json($storefrontApiBase);
                const tokenKey = 'admin_marketplace_shop_cart_token';
                const statusNode = document.getElementById('marketplace-cart-status');
                const itemsNode = document.getElementById('marketplace-cart-items');
                const countNode = document.getElementById('marketplace-cart-count');
                const quantityNode = document.getElementById('marketplace-cart-quantity');
                const subtotalNode = document.getElementById('marketplace-cart-subtotal');
                const discountNode = document.getElementById('marketplace-cart-discount');
                const totalNode = document.getElementById('marketplace-cart-total');
                const cartShell = document.getElementById('marketplace-cart-shell');
                const cartToggle = document.getElementById('marketplace-cart-toggle');
                const cartDropdown = document.getElementById('marketplace-cart-dropdown');

                const getToken = () => window.localStorage.getItem(tokenKey) || '';
                const setToken = (token) => { if (token) window.localStorage.setItem(tokenKey, token); };
                const money = (value) => `$${Number(value || 0).toFixed(2)}`;
                const setStatus = (message, isError = false) => {
                    if (!statusNode) return;
                    statusNode.textContent = message;
                    statusNode.style.color = isError ? 'rgb(225 29 72)' : 'var(--theme-muted-text-color)';
                };

                const renderCart = (payload) => {
                    const data = payload?.data || {};
                    const items = Array.isArray(data.items) ? data.items : [];
                    if (countNode) countNode.textContent = `${data.quantity || 0}`;
                    if (quantityNode) quantityNode.textContent = `${data.quantity || 0}`;
                    if (subtotalNode) subtotalNode.textContent = money(data.subtotal);
                    if (discountNode) discountNode.textContent = money(data.discount);
                    if (totalNode) totalNode.textContent = money(data.total);

                    if (!itemsNode) return;

                    if (!items.length) {
                        itemsNode.innerHTML = `<div class="rounded-2xl border px-4 py-5 text-sm" style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color);">{{ __('Cart is empty.') }}</div>`;
                        return;
                    }

                    itemsNode.innerHTML = items.map((item) => `
                        <div class="rounded-2xl border p-4" style="border-color: var(--theme-border-color); background: rgba(255,255,255,0.96);">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate font-medium" style="color: var(--theme-header-text-color);">${item.name}</p>
                                    <p class="text-xs" style="color: var(--theme-muted-text-color);">${item.type_label} · ${item.quantity} x ${money(item.price)}</p>
                                </div>
                                <button type="button" class="text-xs font-medium" style="color: rgb(225 29 72);" data-storefront-remove="${item.key}">{{ __('Remove') }}</button>
                            </div>
                        </div>
                    `).join('');
                };

                const request = async (path, options = {}) => {
                    const headers = Object.assign({
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    }, options.headers || {});
                    const token = getToken();
                    if (token) headers['X-Cart-Token'] = token;

                    const response = await fetch(`${apiBase}${path}`, {
                        method: options.method || 'GET',
                        headers,
                        body: options.body ? JSON.stringify(options.body) : undefined,
                    });

                    const json = await response.json().catch(() => ({}));
                    const headerToken = response.headers.get('X-Cart-Token');
                    setToken(json.cart_token || headerToken || token);

                    if (!response.ok || json.status === 0) {
                        throw new Error(json.message || 'Storefront request failed.');
                    }

                    return json;
                };

                const openShopCart = () => {
                    if (!@json($shopAppUrl)) return;

                    const token = getToken();
                    const cartUrl = `${@json($shopAppUrl)}/cart${token ? `?cart_token=${encodeURIComponent(token)}` : ''}`;
                    window.open(cartUrl, '_blank', 'noopener');
                };

                const setCartDropdown = (open) => {
                    if (!cartDropdown || !cartToggle) return;
                    cartDropdown.classList.toggle('hidden', !open);
                    cartToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                };

                const loadCart = async (message = '{{ __('Cart loaded successfully.') }}') => {
                    try {
                        setStatus('{{ __('Loading cart...') }}');
                        const json = await request('');
                        renderCart(json);
                        setStatus(message);
                    } catch (error) {
                        setStatus(error.message, true);
                    }
                };

                document.addEventListener('click', async (event) => {
                    if (cartToggle && event.target.closest('#marketplace-cart-toggle')) {
                        event.preventDefault();
                        setCartDropdown(cartDropdown?.classList.contains('hidden'));
                        return;
                    }

                    if (cartShell && !event.target.closest('#marketplace-cart-shell')) {
                        setCartDropdown(false);
                    }

                    const addButton = event.target.closest('[data-storefront-add]');
                    if (addButton) {
                        event.preventDefault();
                        const productId = Number(addButton.dataset.productId || 0);
                        const licenseType = String(addButton.dataset.licenseType || 'regular');
                        if (!productId) return;
                        const originalText = addButton.textContent;

                        try {
                            addButton.disabled = true;
                            addButton.textContent = '{{ __('Adding...') }}';
                            const json = await request('/items', {
                                method: 'POST',
                                body: { product_id: productId, license_type: licenseType, quantity: 1 },
                            });
                            renderCart(json);
                            setStatus(json.message || '{{ __('Product added to cart successfully.') }}');
                            addButton.textContent = '{{ __('Added') }}';
                            window.setTimeout(() => {
                                addButton.textContent = originalText;
                                addButton.disabled = false;
                            }, 1200);
                        } catch (error) {
                            setStatus(error.message, true);
                            addButton.textContent = originalText;
                            addButton.disabled = false;
                        }

                        return;
                    }

                    const removeButton = event.target.closest('[data-storefront-remove]');
                    if (removeButton) {
                        event.preventDefault();
                        try {
                            const itemKey = removeButton.dataset.storefrontRemove;
                            const json = await request(`/items/${encodeURIComponent(itemKey)}`, { method: 'DELETE' });
                            renderCart(json);
                            setStatus(json.message || '{{ __('Cart item removed successfully.') }}');
                        } catch (error) {
                            setStatus(error.message, true);
                        }

                        return;
                    }

                    if (event.target.closest('#marketplace-cart-refresh')) {
                        event.preventDefault();
                        loadCart('{{ __('Cart refreshed successfully.') }}');
                        return;
                    }

                    if (event.target.closest('#marketplace-cart-clear')) {
                        event.preventDefault();
                        try {
                            const json = await request('', { method: 'DELETE' });
                            renderCart(json);
                            setStatus(json.message || '{{ __('Cart cleared successfully.') }}');
                        } catch (error) {
                            setStatus(error.message, true);
                        }

                        return;
                    }

                    if (event.target.closest('[data-open-shop-cart]')) {
                        event.preventDefault();
                        openShopCart();
                    }
                });

                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') {
                        setCartDropdown(false);
                    }
                });

                loadCart();
            })();
    </script>
@endif
