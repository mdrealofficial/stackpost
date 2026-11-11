@php
    $categoryIcons = [
        'All' => 'fa-light fa-grid-2',
        'Creator' => 'fa-light fa-user-pen',
        'Business' => 'fa-light fa-briefcase',
        'Portfolio' => 'fa-light fa-images',
        'Product' => 'fa-light fa-bag-shopping',
        'Music' => 'fa-light fa-music',
        'Minimal' => 'fa-light fa-circle-half-stroke',
        'Bold' => 'fa-light fa-bolt',
    ];

    $backUrl = $pageId ? route('portal.link-bio.edit', ['page' => $pageId]) : route('portal.link-bio.create');
@endphp

<div class="space-y-6 px-4 pb-8 pt-4 sm:px-5 xl:px-6">
    <section class="relative overflow-hidden rounded-[1.35rem] border px-5 py-8 sm:px-8 sm:py-10" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background:
        radial-gradient(circle at 28% 0%, rgba(34,211,238,0.22), transparent 28%),
        radial-gradient(circle at 72% 0%, rgba(168,85,247,0.20), transparent 30%),
        linear-gradient(180deg, rgba(var(--theme-surface-base-rgb,255,255,255),1) 0%, rgba(var(--theme-surface-base-rgb,255,255,255),0.92) 100%);">
        <div class="relative mx-auto max-w-5xl text-center">
            <div class="mb-5 flex flex-wrap items-center justify-center gap-2">
                <a href="{{ route('portal.link-bio.index') }}" class="inline-flex h-8 items-center gap-2 rounded-full border px-3 text-xs font-medium transition" style="border-color: rgba(var(--theme-border-color-rgb),0.68); color: var(--theme-header-text-color); background-color: rgba(var(--theme-surface-base-rgb,255,255,255),0.72);">
                    <i class="fa-light fa-link-horizontal"></i>
                    {{ __('Your pages') }}
                </a>
                <span class="inline-flex h-8 items-center gap-2 rounded-full border px-3 text-xs font-semibold" style="border-color: rgba(var(--theme-accent-rgb),0.22); color: var(--theme-accent); background-color: rgba(var(--theme-accent-rgb),0.10);">
                    <i class="fa-light fa-swatchbook"></i>
                    {{ __('Templates') }}
                </span>
                <a href="{{ $backUrl }}" class="inline-flex h-8 items-center gap-2 rounded-full border px-3 text-xs font-medium transition" style="border-color: rgba(var(--theme-border-color-rgb),0.68); color: var(--theme-header-text-color); background-color: rgba(var(--theme-surface-base-rgb,255,255,255),0.72);">
                    <i class="fa-light fa-arrow-left"></i>
                    {{ __('Back to editor') }}
                </a>
            </div>

            <h1 class="mx-auto max-w-3xl text-3xl font-semibold tracking-[-0.045em] sm:text-5xl" style="color: var(--theme-header-text-color);">
                {{ __('What bio page will you design today?') }}
            </h1>
            <p class="mx-auto mt-3 max-w-2xl text-sm leading-6" style="color: var(--theme-muted-text-color);">
                {{ __('Search templates, pick a style, then continue editing with your content and blocks.') }}
            </p>

            <div class="mx-auto mt-7 max-w-2xl">
                <div class="relative">
                    <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2" style="color: var(--theme-accent);">
                        <i class="fa-light fa-magnifying-glass"></i>
                    </span>
                    <input
                        wire:model.live.debounce.300ms="search"
                        type="search"
                        placeholder="{{ __('Search and discover templates...') }}"
                        class="h-14 w-full rounded-[1rem] border bg-white/95 pl-11 pr-14 text-sm font-medium outline-none transition focus:ring-4"
                        style="border-color: rgba(var(--theme-accent-rgb),0.24); color: var(--theme-header-text-color); box-shadow: 0 18px 46px -28px rgba(var(--theme-accent-rgb),0.58); --tw-ring-color: rgba(var(--theme-accent-rgb),0.16);"
                    >
                    <button type="button" wire:click="$refresh" class="absolute right-2 top-1/2 inline-flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-[0.85rem] transition" style="background-color: rgba(var(--theme-accent-rgb),0.10); color: var(--theme-accent);">
                        <i class="fa-light fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <div class="mt-6 overflow-x-auto pb-2">
                <div class="inline-flex min-w-max items-start justify-center gap-5">
                    @foreach ($categories as $option)
                        @php
                            $active = $category === $option;
                            $icon = $categoryIcons[$option] ?? 'fa-light fa-shapes';
                        @endphp
                        <button
                            type="button"
                            wire:click="$set('category', @js($option))"
                            class="group relative flex w-[76px] flex-col items-center gap-2 rounded-[1rem] px-1.5 py-1.5 text-center transition hover:-translate-y-0.5"
                            style="background-color: transparent;"
                        >
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-full text-sm transition" style="{{ $active ? 'background-color: var(--theme-accent); color: #fff; box-shadow: 0 14px 30px -18px rgba(var(--theme-accent-rgb),0.58);' : 'background-color: color-mix(in srgb, var(--theme-surface-base) 94%, transparent); color: var(--theme-header-text-color); border: 1px solid color-mix(in srgb, var(--theme-border-color) 78%, transparent); box-shadow: 0 10px 24px -22px rgba(15,23,42,0.32);' }}">
                                <i class="{{ $icon }}"></i>
                            </span>
                            <span class="max-w-full truncate text-[11px] font-semibold" style="color: {{ $active ? 'var(--theme-accent)' : 'var(--theme-muted-text-color)' }};">{{ $option }}</span>
                            <span class="absolute bottom-0 h-0.5 w-5 rounded-full transition" style="background-color: {{ $active ? 'var(--theme-accent)' : 'transparent' }};"></span>
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="sr-only">
                <x-ui.select wire:model.live="category" :label="__('Category')">
                    @foreach ($categories as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </x-ui.select>
            </div>
        </div>
    </section>

    <x-ui.section-card
        :eyebrow="__('Template library')"
        :title="$category === 'All' ? __('Explore templates') : $category"
        :description="__('Choose a visual direction for this Link Bio page.')"
        body-class="p-5"
        header-class="py-4"
        title-class="text-[1.25rem]"
    >
        @if ($templates === [])
            <x-ui.empty icon="fa-light fa-swatchbook" :title="__('No templates found')" :description="__('Adjust your search or category to browse more templates.')" />
        @else
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                @foreach ($templates as $template)
                    <x-ui.card padding="none" class="group overflow-hidden transition hover:-translate-y-1 hover:shadow-[0_26px_60px_-40px_rgba(15,23,42,0.42)]">
                        <a href="{{ $pageId ? route('portal.link-bio.edit', ['page' => $pageId, 'template' => $template['key']]) : route('portal.link-bio.create', ['template' => $template['key']]) }}" class="block">
                            <div class="relative aspect-[4/3] overflow-hidden p-5" style="background: {{ data_get($template, 'theme.page_bg', '#f8fafc') }};">
                                <div class="mx-auto h-full max-w-[150px] overflow-hidden rounded-[1.4rem] border shadow-2xl" style="border-color: {{ data_get($template, 'theme.surface_border', 'rgba(15,23,42,0.14)') }}; background: {{ data_get($template, 'theme.shell_bg', '#ffffff') }};">
                                    <div class="h-16" style="background: {{ $template['preview'] }};"></div>
                                    <div class="px-4 py-4">
                                        <div class="h-8 w-8 rounded-full border-2 border-white" style="margin-top:-30px; background: {{ data_get($template, 'theme.accent', '#2563eb') }};"></div>
                                        <div class="mt-3 h-2.5 w-20 rounded-full" style="background: {{ data_get($template, 'theme.text', '#0f172a') }};"></div>
                                        <div class="mt-2 h-2 w-24 rounded-full" style="background: {{ data_get($template, 'theme.muted', '#64748b') }}; opacity:.7;"></div>
                                        <div class="mt-4 space-y-2">
                                            <div class="h-7 rounded-full" style="background: {{ data_get($template, 'theme.button_bg', data_get($template, 'theme.accent', '#2563eb')) }};"></div>
                                            <div class="h-7 rounded-full" style="background: {{ data_get($template, 'theme.surface_bg', 'rgba(255,255,255,0.18)') }}; border:1px solid {{ data_get($template, 'theme.surface_border', 'rgba(15,23,42,0.14)') }};"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="absolute bottom-4 left-4 right-4">
                                    <div class="rounded-[0.9rem] bg-white/90 p-3 shadow-lg backdrop-blur">
                                        <p class="truncate text-sm font-semibold text-slate-950">{{ $template['label'] }}</p>
                                        <p class="mt-1 truncate text-xs text-slate-500">{{ $template['category'] }}</p>
                                    </div>
                                </div>
                                <span class="absolute right-4 top-4 inline-flex h-9 w-9 items-center justify-center rounded-full bg-white/92 text-slate-950 opacity-0 shadow-lg transition group-hover:opacity-100">
                                    <i class="fa-light fa-arrow-up-right"></i>
                                </span>
                            </div>

                            <div class="space-y-3 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <h3 class="truncate text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $template['label'] }}</h3>
                                        <p class="mt-1 text-[11px] font-semibold uppercase tracking-[0.16em]" style="color: var(--theme-muted-text-color);">{{ $template['category'] }}</p>
                                    </div>
                                    <x-ui.badge variant="primary">{{ __('Use') }}</x-ui.badge>
                                </div>
                                <p class="line-clamp-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ $template['description'] }}</p>
                            </div>
                        </a>
                    </x-ui.card>
                @endforeach
            </div>
        @endif
    </x-ui.section-card>
</div>
