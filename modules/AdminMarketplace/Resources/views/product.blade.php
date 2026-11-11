@php
    $openUploadModal = request()->boolean('upload') || $errors->has('module_zip');
    $heroImage = $product['preview_images'][0] ?? $product['thumbnail_url'];
    $heroFallbackImage = $product['thumbnail_url'] && $product['thumbnail_url'] !== $heroImage ? $product['thumbnail_url'] : null;
    $galleryImages = collect($product['preview_images'])->skip($heroImage ? 1 : 0)->take(3)->values();
    $hasFaqs = !empty($product['faqs']);
    $hasSupport = !empty(data_get($product, 'support.info')) || !empty(data_get($product, 'support.link'));
    $hasChangelog = !empty($product['changelog']);
    $tabs = collect([
        ['key' => 'detail', 'label' => __('Detail'), 'icon' => 'fa-light fa-circle-info', 'visible' => true],
        ['key' => 'faqs', 'label' => __('FAQs'), 'icon' => 'fa-light fa-circle-question', 'visible' => $hasFaqs],
        ['key' => 'support', 'label' => __('Support'), 'icon' => 'fa-light fa-life-ring', 'visible' => $hasSupport],
        ['key' => 'changelog', 'label' => __('Changelog'), 'icon' => 'fa-light fa-clock-rotate-left', 'visible' => $hasChangelog],
    ])->where('visible')->values();
    $requestedTab = (string) request('tab', 'detail');
    $initialTab = $tabs->contains(fn ($tab) => $tab['key'] === $requestedTab) ? $requestedTab : 'detail';
@endphp

<style>
    .marketplace-product-content {
        color: var(--theme-header-text-color);
    }

    .marketplace-product-content img {
        display: block;
        width: 100%;
        max-width: 100%;
        height: auto;
        border-radius: 1.25rem;
        margin: 1.25rem 0;
        border: 1px solid rgba(var(--theme-border-color-rgb), 0.68);
        box-shadow: 0 18px 45px -34px rgba(15, 23, 42, 0.35);
    }

    .marketplace-product-content h2,
    .marketplace-product-content h3,
    .marketplace-product-content h4 {
        color: var(--theme-header-text-color);
        font-weight: 700;
        letter-spacing: -0.02em;
        margin-top: 1.75rem;
        margin-bottom: 0.85rem;
    }

    .marketplace-product-content p,
    .marketplace-product-content li,
    .marketplace-product-content blockquote {
        font-size: 0.98rem;
        line-height: 1.95;
        color: color-mix(in srgb, var(--theme-header-text-color) 82%, transparent);
    }

    .marketplace-product-content ul,
    .marketplace-product-content ol {
        margin: 0;
        padding-left: 1.2rem;
    }

    .marketplace-product-content a {
        color: var(--theme-accent);
        text-decoration: none;
        font-weight: 600;
    }

    .marketplace-product-content blockquote {
        margin: 1.25rem 0;
        padding: 1rem 1.25rem;
        border-left: 3px solid rgba(var(--theme-accent-rgb), 0.45);
        border-radius: 1rem;
        background: rgba(var(--theme-accent-rgb), 0.05);
    }

    .marketplace-product-content code {
        padding: 0.18rem 0.45rem;
        border-radius: 0.5rem;
        background: rgba(15, 23, 42, 0.06);
        font-size: 0.92em;
    }

    .marketplace-product-stage {
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(var(--theme-border-color-rgb), 0.68);
        border-radius: 1.8rem;
        background:
            radial-gradient(circle at top right, rgba(59,130,246,0.1), transparent 30%),
            radial-gradient(circle at bottom left, rgba(16,185,129,0.08), transparent 24%),
            color-mix(in srgb, var(--theme-surface-base) 99%, white);
    }

    .marketplace-product-stage::before {
        content: "";
        position: absolute;
        inset: 0;
        background:
            linear-gradient(135deg, rgba(255,255,255,0.74), rgba(255,255,255,0.18));
        pointer-events: none;
    }

    .marketplace-product-segment {
        backdrop-filter: blur(10px);
    }
</style>

<div class="space-y-6">
    @if (session('status'))
        <x-ui.alert variant="success" :title="__('Updated')" :description="session('status')" dismissible />
    @endif

    @if ($errors->has('marketplace'))
        <x-ui.alert :title="__('Marketplace error')" :description="$errors->first('marketplace')" variant="danger" dismissible />
    @endif

    <x-ui.page-hero
        :eyebrow="__('Marketplace')"
        :title="$product['name']"
        :description="$product['description'] ?: __('Marketplace product detail loaded from Stackposts API.')"
        icon="fa-light fa-store"
    >
        <x-slot:actions>
            <x-ui.button :href="route('admin-marketplace.index')" variant="outline" wire:navigate>{{ __('Back') }}</x-ui.button>

            @if ($storefrontApiBase)
                <button
                    type="button"
                    id="marketplace-product-cart-toggle"
                    class="relative inline-flex h-11 w-11 items-center justify-center rounded-full border shadow-sm"
                    style="border-color: var(--theme-border-color); background: var(--theme-card-background); color: var(--theme-header-text-color);"
                    aria-label="{{ __('Open cart') }}"
                >
                    <i class="fa-light fa-cart-shopping text-base"></i>
                    <span
                        id="marketplace-product-cart-count"
                        class="absolute -right-1 -top-1 inline-flex min-w-[1.4rem] items-center justify-center rounded-full px-1.5 py-0.5 text-[11px] font-semibold"
                        style="background: var(--theme-accent); color: white;"
                    >
                        0
                    </span>
                </button>
            @endif

            @if ($product['is_envato'] && $product['buy_url'])
                <x-ui.button :href="$product['buy_url']" target="_blank">{{ __('Buy on Envato') }}</x-ui.button>
            @elseif ($product['matched_package']?->is_active)
            @endif

            @if ($product['matched_package'])
                <x-ui.modal
                    width="lg"
                    :title="__('Upload new version')"
                    :description="__('Upload a ZIP package to replace the installed module with a newer build.')"
                    :initially-open="$openUploadModal"
                >
                    <x-slot:trigger>
                        <x-ui.button type="button" variant="outline">{{ __('Upload new version') }}</x-ui.button>
                    </x-slot:trigger>

                    <div x-data="{ submitting: false }">
                        <form method="POST" action="{{ route('admin-marketplace.install') }}" enctype="multipart/form-data" class="space-y-5" x-on:submit="submitting = true">
                            @csrf
                            <input type="hidden" name="install_method" value="zip">

                            <div class="rounded-2xl border px-4 py-3 text-sm" style="border-color: var(--theme-border-color); background: rgba(59,130,246,0.04); color: var(--theme-muted-text-color);">
                                {{ __('The uploaded ZIP should contain the same module and a newer version than the package already installed.') }}
                            </div>

                            <div class="space-y-2">
                                <label for="product-module-zip" class="text-sm font-medium" style="color: var(--theme-header-text-color);">{{ __('Module ZIP') }}</label>
                                <input
                                    id="product-module-zip"
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

                            <div class="flex justify-end gap-3 border-t pt-5" style="border-color: var(--theme-border-color);">
                                <x-ui.button type="button" variant="outline" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                                <x-ui.button type="submit" x-bind:disabled="submitting">
                                    <span x-show="!submitting">{{ __('Upload new version') }}</span>
                                    <span x-show="submitting" x-cloak>{{ __('Uploading...') }}</span>
                                </x-ui.button>
                            </div>
                        </form>
                    </div>
                </x-ui.modal>
            @endif

            @if ($product['has_update'] && $product['matched_package'])
                <form method="POST" action="{{ route('admin-marketplace.update', $product['matched_package']->id_secure) }}">
                    @csrf
                    <x-ui.button type="submit" variant="warning">{{ __('Update to :version', ['version' => $product['latest_version']]) }}</x-ui.button>
                </form>
            @endif
        </x-slot:actions>
    </x-ui.page-hero>

    <div class="grid gap-3 md:grid-cols-3 xl:grid-cols-4">
        @if ($product['author'])
            <div class="rounded-[1.4rem] border px-5 py-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.66); background: color-mix(in srgb, var(--theme-surface-base) 96%, rgba(59,130,246,0.05));">
                <p class="text-[11px] uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Author') }}</p>
                <p class="mt-2 text-base font-semibold" style="color: var(--theme-header-text-color);">{{ $product['author'] }}</p>
            </div>
        @endif

        <div class="rounded-[1.4rem] border px-5 py-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.66); background: color-mix(in srgb, var(--theme-surface-base) 96%, rgba(16,185,129,0.05));">
            <p class="text-[11px] uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Version') }}</p>
            <p class="mt-2 text-base font-semibold" style="color: var(--theme-header-text-color);">{{ $product['version'] ?: __('Unknown') }}</p>
        </div>

        <div class="rounded-[1.4rem] border px-5 py-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.66); background: color-mix(in srgb, var(--theme-surface-base) 96%, rgba(245,158,11,0.05));">
            <p class="text-[11px] uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Sales') }}</p>
            <p class="mt-2 text-base font-semibold" style="color: var(--theme-header-text-color);">{{ number_format((int) $product['sales']) }}</p>
        </div>

        <div class="rounded-[1.4rem] border px-5 py-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.66); background: color-mix(in srgb, var(--theme-surface-base) 96%, rgba(99,102,241,0.05));">
            <p class="text-[11px] uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Status') }}</p>
            <p class="mt-2 text-base font-semibold" style="color: var(--theme-header-text-color);">
                @if ($product['matched_package'])
                    {{ __('Installed') }}
                @elseif ((int) $product['status'] === 1)
                    {{ __('Available') }}
                @else
                    {{ __('Unavailable') }}
                @endif
            </p>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px] xl:items-start">
        <div x-data="{ tab: @js($initialTab) }" class="space-y-6">
            <x-ui.card padding="none" class="overflow-hidden">
                <div class="px-4 py-4" style="background: linear-gradient(180deg, rgba(59,130,246,0.035), rgba(16,185,129,0.025));">
                    <div class="inline-flex flex-wrap gap-2 rounded-full border p-2 marketplace-product-segment" style="border-color: rgba(var(--theme-border-color-rgb), 0.66); background: rgba(255,255,255,0.66);">
                        @foreach ($tabs as $tab)
                            <button
                                type="button"
                                x-on:click="tab = @js($tab['key'])"
                                class="inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-medium transition"
                                :style="tab === @js($tab['key'])
                                    ? 'background: var(--theme-accent); color: white; border-color: transparent; box-shadow: 0 14px 34px -24px rgba(var(--theme-accent-rgb), 0.65);'
                                    : 'background: transparent; color: var(--theme-header-text-color); border-color: transparent;'"
                            >
                                <i class="{{ $tab['icon'] }}"></i>
                                <span>{{ $tab['label'] }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </x-ui.card>

            <div x-show="tab === 'detail'" x-cloak class="space-y-6">
                <x-ui.card padding="none" class="overflow-hidden">
                    <div class="p-6 md:p-8">
                        <div class="space-y-5">
                            <div class="flex flex-wrap gap-2">
                                @if ($product['is_featured'])
                                    <span class="inline-flex items-center rounded-full px-4 py-2 text-sm font-semibold" style="background: rgba(245,158,11,0.16); color: rgb(146, 64, 14);">
                                        {{ __('Featured Product') }}
                                    </span>
                                @endif
                                <span class="inline-flex items-center rounded-full px-4 py-2 text-sm font-semibold" style="background: rgba(15,23,42,0.06); color: var(--theme-header-text-color);">
                                    {{ $product['category_name'] ?: __('Product') }}
                                </span>
                            </div>

                            <div class="space-y-3">
                                <h2 class="text-[2.3rem] font-semibold leading-tight tracking-[-0.045em]" style="color: var(--theme-header-text-color);">
                                    {{ $product['name'] }}
                                </h2>
                                <p class="max-w-4xl text-[1.03rem] leading-8" style="color: color-mix(in srgb, var(--theme-header-text-color) 82%, transparent);">
                                    {{ $product['description'] ?: __('This product does not include a short description.') }}
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-3">
                                <span class="inline-flex items-center rounded-full px-4 py-2 text-sm font-semibold" style="background: rgba(16,185,129,0.12); color: rgb(6, 95, 70);">
                                    {{ __('Well Documented') }}
                                </span>
                                @if ($product['has_update'])
                                    <span class="inline-flex items-center rounded-full px-4 py-2 text-sm font-semibold" style="background: rgba(59,130,246,0.12); color: var(--theme-accent);">
                                        {{ __('New update available') }}
                                    </span>
                                @endif
                            </div>

                            <div class="grid gap-4 border-t pt-5 sm:grid-cols-2 lg:grid-cols-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.62);">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full text-sm font-semibold" style="background: rgba(59,130,246,0.1); color: var(--theme-accent);">
                                        {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($product['author'] ?: 'A', 0, 1)) }}
                                    </div>
                                    <div>
                                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">{{ __('Created by') }}</p>
                                        <p class="mt-1 text-base font-semibold" style="color: var(--theme-header-text-color);">{{ $product['author'] ?: __('Admin') }}</p>
                                    </div>
                                </div>
                                <div>
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">{{ __('Sales') }}</p>
                                    <p class="mt-1 text-base font-semibold" style="color: var(--theme-header-text-color);">{{ number_format((int) $product['sales']) }}</p>
                                </div>
                                <div>
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">{{ __('Version') }}</p>
                                    <p class="mt-1 text-base font-semibold" style="color: var(--theme-header-text-color);">{{ $product['version'] ?: __('Unknown') }}</p>
                                </div>
                                <div>
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">{{ __('Status') }}</p>
                                    <p class="mt-1 text-base font-semibold" style="color: var(--theme-header-text-color);">
                                        @if ($product['matched_package'])
                                            {{ __('Installed') }}
                                        @elseif ((int) $product['status'] === 1)
                                            {{ __('Available') }}
                                        @else
                                            {{ __('Unavailable') }}
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </x-ui.card>

                <x-ui.card padding="none" class="overflow-hidden">
                    <div class="p-4 md:p-5">
                        <div class="marketplace-product-stage p-5 md:p-6 lg:p-7">
                            <div class="space-y-4">
                                <div class="overflow-hidden rounded-[1.6rem] border bg-white/70" style="border-color: rgba(var(--theme-border-color-rgb), 0.7);">
                                    <div class="relative aspect-[16/9] w-full">
                                        <div class="flex h-full w-full items-center justify-center text-5xl" style="color: var(--theme-muted-text-color); {{ $heroImage ? 'display:none;' : '' }}" data-product-thumbnail-fallback>
                                            <i class="fa-light fa-image"></i>
                                        </div>
                                        @if ($heroImage)
                                            <img
                                                src="{{ $heroImage }}"
                                                alt="{{ $product['name'] }}"
                                                class="absolute inset-0 h-full w-full object-contain"
                                                loading="lazy"
                                                decoding="async"
                                                onerror="if (this.dataset.fallback && this.src !== this.dataset.fallback) { this.src = this.dataset.fallback; return; } this.style.display='none'; const fallback = this.parentElement.querySelector('[data-product-thumbnail-fallback]'); if (fallback) fallback.style.display='flex';"
                                                @if ($heroFallbackImage) data-fallback="{{ $heroFallbackImage }}" @endif
                                            >
                                        @endif
                                    </div>
                                </div>

                                @if ($galleryImages->isNotEmpty())
                                    <div class="grid grid-cols-3 gap-3">
                                        @foreach ($galleryImages as $previewImage)
                                            <div class="overflow-hidden rounded-[1rem] border bg-white/70" style="border-color: rgba(var(--theme-border-color-rgb), 0.7);">
                                                <div class="relative aspect-[4/3] w-full">
                                                    <img src="{{ $previewImage }}" alt="" class="absolute inset-0 h-full w-full object-cover" loading="lazy" decoding="async">
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </x-ui.card>

                <x-ui.card padding="none" class="overflow-hidden">
                    <div class="p-6 md:p-8">
                        <div class="mb-6 border-b pb-5" style="border-color: rgba(var(--theme-border-color-rgb), 0.62);">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">{{ __('Inside the product') }}</p>
                            <h2 class="mt-2 text-2xl font-semibold tracking-[-0.02em]" style="color: var(--theme-header-text-color);">{{ __('Overview & features') }}</h2>
                        </div>

                        @if ($product['content'])
                            <div class="marketplace-product-content max-w-none">
                                {!! $product['content'] !!}
                            </div>
                        @else
                            <p class="text-base leading-8" style="color: var(--theme-muted-text-color);">{{ $product['description'] }}</p>
                        @endif
                    </div>
                </x-ui.card>
            </div>

            @if ($hasFaqs)
                <x-ui.card padding="none" x-show="tab === 'faqs'" x-cloak class="overflow-hidden">
                    <div class="space-y-4 p-4 md:p-5">
                        @foreach ($product['faqs'] as $faq)
                            <div x-data="{ open: false }" class="overflow-hidden rounded-[1.4rem] border" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background: var(--theme-card-background);">
                                <button type="button" x-on:click="open = !open" class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left">
                                    <span class="font-semibold" style="color: var(--theme-header-text-color);">{{ $faq['name'] ?? __('FAQ') }}</span>
                                    <i class="fa-light fa-plus text-sm transition" :class="{ 'rotate-45': open }" style="color: var(--theme-muted-text-color);"></i>
                                </button>
                                <div x-show="open" x-collapse class="border-t px-5 py-4 text-sm leading-7" style="border-color: rgba(var(--theme-border-color-rgb), 0.62); color: var(--theme-muted-text-color);">
                                    {!! $faq['content'] ?? '' !!}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-ui.card>
            @endif

            @if ($hasSupport)
                <x-ui.card padding="none" x-show="tab === 'support'" x-cloak class="overflow-hidden">
                    <div class="rounded-[1.8rem] border p-6 md:p-8" style="border-color: rgba(var(--theme-border-color-rgb), 0.66); background: color-mix(in srgb, var(--theme-surface-base) 99%, white);">
                            <div class="mb-6 border-b pb-5" style="border-color: rgba(var(--theme-border-color-rgb), 0.62);">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">{{ __('Support') }}</p>
                                <h2 class="mt-2 text-2xl font-semibold tracking-[-0.02em]" style="color: var(--theme-header-text-color);">{{ __('Support information') }}</h2>
                            </div>

                            <div class="marketplace-product-content max-w-none">
                                {!! data_get($product, 'support.info') !!}
                            </div>

                            @if (data_get($product, 'support.link'))
                                <div class="mt-6">
                                    <x-ui.button :href="data_get($product, 'support.link')" class="w-full justify-center rounded-[1rem] py-3" target="_blank">
                                        <i class="fa-light fa-headset"></i>
                                        <span>{{ __('Contact support') }}</span>
                                    </x-ui.button>
                                </div>
                            @endif
                    </div>
                </x-ui.card>
            @endif

            @if ($hasChangelog)
                <x-ui.card padding="none" x-show="tab === 'changelog'" x-cloak class="overflow-hidden">
                    <div class="space-y-4 p-4 md:p-5">
                        @foreach ($product['changelog'] as $log)
                            <div class="rounded-[1.4rem] border p-5" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background: var(--theme-card-background);">
                                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                    <div class="space-y-2">
                                        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold" style="background: rgba(var(--theme-accent-rgb), 0.1); color: var(--theme-accent);">
                                            {{ __('Version :version', ['version' => $log['version'] ?? '?']) }}
                                        </span>
                                        <h3 class="text-lg font-semibold" style="color: var(--theme-header-text-color);">{{ $log['title'] ?? __('Update') }}</h3>
                                        <p class="text-sm leading-7" style="color: var(--theme-muted-text-color);">{!! nl2br(e($log['content'] ?? '')) !!}</p>
                                    </div>
                                    <div class="shrink-0 text-sm" style="color: var(--theme-muted-text-color);">
                                        {{ !empty($log['published_at']) ? \Illuminate\Support\Carbon::parse($log['published_at'])->format('M d, Y') : __('Unknown date') }}
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-ui.card>
            @endif
        </div>

        <div class="space-y-6 xl:sticky xl:top-[calc(var(--app-shell-header-height,79px)+1rem)] xl:self-start">
            <x-ui.card>
                <div class="space-y-5">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="rounded-2xl px-4 py-4" style="background: rgba(59,130,246,0.08);">
                            <p class="text-xs uppercase tracking-[0.16em]" style="color: var(--theme-muted-text-color);">{{ __('Regular') }}</p>
                            <p class="mt-2 text-2xl font-semibold" style="color: var(--theme-header-text-color);">${{ number_format($product['price_regular_license'], 2) }}</p>
                        </div>
                        <div class="rounded-2xl px-4 py-4" style="background: rgba(16,185,129,0.08);">
                            <p class="text-xs uppercase tracking-[0.16em]" style="color: var(--theme-muted-text-color);">{{ __('Extended') }}</p>
                            <p class="mt-2 text-2xl font-semibold" style="color: var(--theme-header-text-color);">${{ number_format($product['price_extended_license'], 2) }}</p>
                        </div>
                    </div>

                    @if ($product['price_renew_support'] > 0)
                        <div class="rounded-2xl px-4 py-4" style="background: rgba(245,158,11,0.08);">
                            <p class="text-xs uppercase tracking-[0.16em]" style="color: var(--theme-muted-text-color);">{{ __('Renew support') }}</p>
                            <p class="mt-2 text-xl font-semibold" style="color: var(--theme-header-text-color);">${{ number_format($product['price_renew_support'], 2) }}</p>
                        </div>
                    @endif

                    <div class="space-y-3">
                        @if ($product['matched_package']?->is_active)
                            @if ($product['has_update'] && $product['matched_package'])
                                <form method="POST" action="{{ route('admin-marketplace.update', $product['matched_package']->id_secure) }}">
                                    @csrf
                                    <x-ui.button type="submit" variant="warning" class="w-full justify-center rounded-[1rem] py-3">
                                        <i class="fa-light fa-arrows-rotate text-sm"></i>
                                        {{ __('Update') }}
                                    </x-ui.button>
                                </form>
                            @else
                                <span class="inline-flex w-full items-center justify-center rounded-[1rem] border px-4 py-3 text-sm font-medium uppercase tracking-[0.16em]" style="border-color: rgba(16,185,129,0.28); color: rgb(6, 95, 70); background: rgba(16,185,129,0.08);">
                                    {{ __('INSTALLED') }}
                                </span>
                            @endif
                        @elseif ($product['is_envato'] && $product['buy_url'] && (int) $product['status'] === 1)
                            <x-ui.button :href="$product['buy_url']" class="w-full justify-center rounded-[1rem] py-3" target="_blank">
                                <i class="fa-brands fa-envira text-sm"></i>
                                {{ __('Buy on Envato') }}
                            </x-ui.button>
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
                        @elseif ($product['buy_url'] && (int) $product['status'] === 1)
                            <x-ui.button :href="$product['buy_url']" class="w-full justify-center rounded-[1rem] py-3" target="_blank">
                                {{ __('Buy') }}
                            </x-ui.button>
                        @else
                            <span class="inline-flex w-full items-center justify-center rounded-[1rem] border px-4 py-3 text-sm font-medium" style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color);">
                                {{ __('Not available for purchase') }}
                            </span>
                        @endif

                        @if ($product['demo_url'])
                            <x-ui.button :href="$product['demo_url']" variant="outline" class="w-full justify-center rounded-[1rem] py-3" target="_blank">
                                <i class="fa-light fa-arrow-up-right-from-square"></i>
                                <span>{{ __('Open demo') }}</span>
                            </x-ui.button>
                        @endif
                    </div>
                </div>
            </x-ui.card>

            <div class="space-y-3">
                <div class="rounded-[1.4rem] border px-5 py-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.66); background: color-mix(in srgb, var(--theme-surface-base) 98%, rgba(16,185,129,0.03));">
                    <div class="flex items-start gap-3">
                        <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full" style="background: rgba(16,185,129,0.1); color: rgb(22 163 74);">
                            <i class="fa-light fa-badge-check"></i>
                        </span>
                        <p class="text-base leading-7" style="color: var(--theme-header-text-color);">
                            {{ __('Quality checked and ready for production use') }}
                        </p>
                    </div>
                </div>

                <div class="rounded-[1.4rem] border px-5 py-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.66); background: color-mix(in srgb, var(--theme-surface-base) 98%, rgba(16,185,129,0.03));">
                    <div class="flex items-start gap-3">
                        <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full" style="background: rgba(16,185,129,0.1); color: rgb(22 163 74);">
                            <i class="fa-light fa-arrows-rotate"></i>
                        </span>
                        <p class="text-base leading-7" style="color: var(--theme-header-text-color);">
                            {{ __('Future updates included with your purchase') }}
                        </p>
                    </div>
                </div>

                <div class="rounded-[1.4rem] border px-5 py-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.66); background: color-mix(in srgb, var(--theme-surface-base) 98%, rgba(16,185,129,0.03));">
                    <div class="flex items-start gap-3">
                        <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full" style="background: rgba(16,185,129,0.1); color: rgb(22 163 74);">
                            <i class="fa-light fa-life-ring"></i>
                        </span>
                        <p class="text-base leading-7" style="color: var(--theme-header-text-color);">
                            {{ __('Support handled through your account workspace') }}
                        </p>
                    </div>
                </div>
            </div>

            <x-ui.card>
                <div class="space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <span style="color: var(--theme-muted-text-color);">{{ __('Product name') }}</span>
                        <span class="text-right" style="color: var(--theme-header-text-color);">{{ $product['name'] ?: __('Unknown') }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span style="color: var(--theme-muted-text-color);">{{ __('Module name') }}</span>
                        <span class="font-mono text-right" style="color: var(--theme-header-text-color);">{{ $product['module_name'] ?: __('Not mapped') }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span style="color: var(--theme-muted-text-color);">{{ __('Version') }}</span>
                        <span style="color: var(--theme-header-text-color);">{{ $product['version'] ?: __('Unknown') }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span style="color: var(--theme-muted-text-color);">{{ __('Install folder') }}</span>
                        <span class="font-mono text-right" style="color: var(--theme-header-text-color);">{{ $product['folder_install'] ?: __('Unknown') }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span style="color: var(--theme-muted-text-color);">{{ __('Created') }}</span>
                        <span style="color: var(--theme-header-text-color);">{{ $product['created_at'] ?: __('Unknown') }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span style="color: var(--theme-muted-text-color);">{{ __('Changed') }}</span>
                        <span style="color: var(--theme-header-text-color);">{{ $product['changed_at'] ?: __('Unknown') }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span style="color: var(--theme-muted-text-color);">{{ __('Matched local package') }}</span>
                        <span style="color: var(--theme-header-text-color);">{{ $product['matched_package']?->title ?: __('Not installed') }}</span>
                    </div>
                    @if ($product['matched_package'])
                        <div class="flex items-center justify-between gap-3">
                            <span style="color: var(--theme-muted-text-color);">{{ __('Installed version') }}</span>
                            <span style="color: var(--theme-header-text-color);">{{ $product['matched_package']->version ?: __('Unknown') }}</span>
                        </div>
                    @endif
                </div>
            </x-ui.card>

            @if ($product['product_url'] || data_get($product, 'support.link'))
                <x-ui.card>
                    <div class="space-y-3">
                        @if ($product['product_url'])
                            <x-ui.button :href="$product['product_url']" variant="outline" class="w-full justify-center rounded-[1rem] py-3" target="_blank">
                                <i class="fa-light fa-globe"></i>
                                <span>{{ __('View public page') }}</span>
                            </x-ui.button>
                        @endif

                        @if (data_get($product, 'support.link'))
                            <x-ui.button :href="data_get($product, 'support.link')" variant="outline" class="w-full justify-center rounded-[1rem] py-3" target="_blank">
                                <i class="fa-light fa-life-ring"></i>
                                <span>{{ __('Open support') }}</span>
                            </x-ui.button>
                        @endif
                    </div>
                </x-ui.card>
            @endif
        </div>
    </div>
</div>

@if ($storefrontApiBase)
    <script>
        (() => {
            const apiBase = @json($storefrontApiBase);
            const shopAppUrl = @json($shopAppUrl);
            const tokenKey = 'admin_marketplace_shop_cart_token';
            const cartCountNode = document.getElementById('marketplace-product-cart-count');
            const cartToggle = document.getElementById('marketplace-product-cart-toggle');

            const getToken = () => window.localStorage.getItem(tokenKey) || '';
            const setToken = (token) => { if (token) window.localStorage.setItem(tokenKey, token); };
            const setCartCount = (count) => {
                if (!cartCountNode) return;
                cartCountNode.textContent = `${Number(count || 0)}`;
            };
            const toast = (type, message) => {
                window.dispatchEvent(new CustomEvent('app-toast', {
                    detail: {
                        type,
                        title: @json(__('Marketplace')),
                        message,
                    },
                }));
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
                if (!shopAppUrl) return;

                const token = getToken();
                const cartUrl = `${shopAppUrl}/cart${token ? `?cart_token=${encodeURIComponent(token)}` : ''}`;
                window.open(cartUrl, '_blank', 'noopener');
            };

            const loadCartCount = async () => {
                try {
                    const json = await request('');
                    setCartCount(json?.data?.quantity || 0);
                } catch (_) {
                }
            };

            document.addEventListener('click', async (event) => {
                if (cartToggle && event.target.closest('#marketplace-product-cart-toggle')) {
                    event.preventDefault();
                    openShopCart();
                    return;
                }

                const addButton = event.target.closest('[data-storefront-add]');
                if (!addButton) return;

                event.preventDefault();
                const productId = Number(addButton.dataset.productId || 0);
                const licenseType = String(addButton.dataset.licenseType || 'regular');
                if (!productId) return;

                const originalText = addButton.textContent;

                try {
                    addButton.disabled = true;
                    addButton.textContent = @json(__('Adding...'));
                    const json = await request('/items', {
                        method: 'POST',
                        body: { product_id: productId, license_type: licenseType, quantity: 1 },
                    });
                    setCartCount(json?.data?.quantity || 0);
                    toast('success', json.message || @json(__('Product added to cart successfully.')));
                    addButton.textContent = @json(__('Added'));
                    window.setTimeout(() => {
                        addButton.textContent = originalText;
                        addButton.disabled = false;
                    }, 1200);
                } catch (error) {
                    toast('error', error.message || @json(__('Storefront request failed.')));
                    addButton.textContent = originalText;
                    addButton.disabled = false;
                }
            });

            loadCartCount();
        })();
    </script>
@endif
