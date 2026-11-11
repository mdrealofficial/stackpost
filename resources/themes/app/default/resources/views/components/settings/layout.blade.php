@props([
    'contentWidthClass' => 'max-w-4xl',
])

@php
    $settingsSections = settings_navigation_sections();
    $settingsItemCount = collect($settingsSections)->sum(fn ($section) => count($section['items'] ?? []));
@endphp

<div class="mx-auto max-w-[88rem] space-y-6">
    <section class="overflow-hidden rounded-[1.5rem] border" style="border-color: rgba(var(--theme-border-color-rgb), 0.6); background:
        radial-gradient(circle at top left, rgba(var(--theme-accent-rgb), 0.16), transparent 30%),
        radial-gradient(circle at 80% 20%, rgba(14, 165, 233, 0.1), transparent 22%),
        linear-gradient(135deg, rgba(var(--theme-surface-base-rgb,255,255,255),0.985), rgba(var(--theme-surface-soft-rgb,248,250,252),0.92));
        box-shadow: 0 16px 42px rgba(15, 23, 42, 0.05);
    ">
        <div class="grid gap-5 px-5 py-5 sm:px-6 xl:grid-cols-[minmax(0,1fr)_18rem] xl:items-center">
            <div class="flex items-start gap-3">
                <span class="inline-flex h-14 w-14 flex-none items-center justify-center rounded-[1.05rem] border" style="border-color: rgba(var(--theme-border-color-rgb), 0.45); background:
                    linear-gradient(145deg, rgba(var(--theme-accent-rgb), 0.18), rgba(var(--theme-accent-rgb), 0.06));
                    color: var(--theme-accent);
                ">
                    <i class="fa-light fa-sliders text-lg"></i>
                </span>

                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.28em]" style="color: var(--theme-muted-text-color);">{{ __('Admin settings') }}</p>
                    <h1 class="mt-1.5 text-[1.9rem] font-semibold tracking-[-0.055em] leading-tight" style="color: var(--theme-header-text-color);">{{ $heading ?? __('Settings') }}</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ $subheading ?? __('Configure shared admin modules, platform defaults, and account controls from one workspace.') }}</p>
                </div>
            </div>

            <div class="grid gap-2.5 sm:grid-cols-2 xl:grid-cols-1">
                <div class="rounded-[1.05rem] border px-4 py-3.5" style="border-color: rgba(var(--theme-border-color-rgb), 0.42); background:
                    linear-gradient(160deg, rgba(var(--theme-surface-base-rgb,255,255,255),0.92), rgba(var(--theme-surface-soft-rgb,248,250,252),0.72));
                    backdrop-filter: blur(6px);
                ">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em]" style="color: var(--theme-muted-text-color);">{{ __('Settings pages') }}</p>
                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-full" style="background-color: rgba(var(--theme-accent-rgb), 0.12); color: var(--theme-accent);">
                            <i class="fa-light fa-file-lines text-[12px]"></i>
                        </span>
                    </div>
                    <p class="mt-2 text-[1.65rem] font-semibold tracking-[-0.05em]" style="color: var(--theme-header-text-color);">{{ number_format($settingsItemCount) }}</p>
                </div>
                <div class="rounded-[1.05rem] border px-4 py-3.5" style="border-color: rgba(var(--theme-border-color-rgb), 0.42); background:
                    linear-gradient(160deg, rgba(var(--theme-surface-base-rgb,255,255,255),0.92), rgba(var(--theme-surface-soft-rgb,248,250,252),0.72));
                    backdrop-filter: blur(6px);
                ">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em]" style="color: var(--theme-muted-text-color);">{{ __('Workspace') }}</p>
                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-full" style="background-color: rgba(var(--theme-accent-rgb), 0.12); color: var(--theme-accent);">
                            <i class="fa-light fa-grid-2 text-[12px]"></i>
                        </span>
                    </div>
                    <p class="mt-2 text-[15px] font-semibold leading-6" style="color: var(--theme-header-text-color);">{{ __('Shared admin control surface') }}</p>
                </div>
            </div>
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-[18rem_minmax(0,1fr)]">
        <aside
            class="relative z-20 space-y-4 xl:sticky xl:top-24 xl:self-start"
            x-data="{
                currentPath: '',
                isNavigating: false,
                navigatingTo: '',
                normalize(url) {
                    if (!url) return '/';
                    try {
                        const parsed = new URL(url, window.location.origin);
                        const path = (parsed.pathname || '/').replace(/\/+$/, '');
                        return path === '' ? '/' : path;
                    } catch (error) {
                        return '/';
                    }
                },
                sync() {
                    const path = (window.location.pathname || '/').replace(/\/+$/, '');
                    this.currentPath = path === '' ? '/' : path;
                },
                startNavigate(route) {
                    if (!route) return;
                    this.navigatingTo = this.normalize(route);
                    this.isNavigating = true;
                },
                isPending(route) {
                    if (!route) return false;
                    return this.isNavigating && this.navigatingTo === this.normalize(route);
                },
                isActive(itemRoute, fallback = false) {
                    const routePath = this.normalize(itemRoute);

                    if (routePath !== '/' && this.currentPath.startsWith(routePath)) {
                        return true;
                    }

                    return routePath === this.currentPath || fallback;
                }
            }"
            x-on:livewire:navigate.window="isNavigating = true"
            x-on:livewire:navigated.window="isNavigating = false; navigatingTo = ''; sync()"
            x-init="
                sync();
                window.addEventListener('popstate', () => sync());
            "
        >
            <div class="overflow-hidden rounded-[1.4rem] border" style="border-color: rgba(var(--theme-border-color-rgb), 0.52); background:
                linear-gradient(180deg, rgba(var(--theme-surface-base-rgb,255,255,255),0.985), rgba(var(--theme-surface-soft-rgb,248,250,252),0.94));
                box-shadow: 0 12px 30px rgba(15, 23, 42, 0.035);
            ">
                <div class="space-y-4 p-3.5">
                @foreach ($settingsSections as $section)
                    <div class="space-y-2.5">
                        @if (! empty($section['label']))
                            <p class="px-2 text-[11px] font-semibold uppercase tracking-[0.2em]" style="color: var(--theme-muted-text-color);">{{ __($section['label']) }}</p>
                        @endif

                        @if ($section['key'] === 'account')
                            <div class="space-y-1.5 px-1.5">
                                @foreach ($section['items'] as $item)
                                    <a
                                        href="{{ $item['route'] ?? '#' }}"
                                        x-on:click="startNavigate(@js($item['route'] ?? null))"
                                        class="relative z-10 block rounded-[0.85rem] px-3 py-2.5 text-sm font-semibold transition"
                                        x-bind:style="isActive(@js($item['route'] ?? null), @js((bool) ($item['active'] ?? false)))
                                            ? 'background-color: rgba(var(--theme-accent-rgb), 0.10); color: var(--theme-header-text-color); box-shadow: inset 0 0 0 1px rgba(var(--theme-accent-rgb), 0.20);'
                                            : 'color: var(--theme-muted-text-color);'"
                                    >
                                        <span
                                            class="transition"
                                            x-bind:class="isActive(@js($item['route'] ?? null), @js((bool) ($item['active'] ?? false))) ? '' : 'hover:text-[var(--theme-header-text-color)]'"
                                        >{{ __($item['label']) }}</span>
                                    </a>
                                @endforeach
                            </div>
                        @else
                            <div class="space-y-1">
                                @foreach ($section['items'] as $item)
                                    <a
                                        href="{{ $item['route'] ?? '#' }}"
                                        x-on:click="startNavigate(@js($item['route'] ?? null))"
                                        class="group relative z-10 flex w-full items-center justify-between gap-3 rounded-[0.9rem] px-3.5 py-2.5 text-left text-sm font-semibold transition"
                                        x-bind:class="isActive(@js($item['route'] ?? null), @js((bool) ($item['active'] ?? false))) ? '' : 'hover:text-[var(--theme-header-text-color)]'"
                                        x-bind:style="isActive(@js($item['route'] ?? null), @js((bool) ($item['active'] ?? false)))
                                            ? 'background-color: rgba(var(--theme-accent-rgb), 0.11); color: var(--theme-header-text-color); box-shadow: inset 0 0 0 1px rgba(var(--theme-accent-rgb), 0.24);'
                                            : 'color: var(--theme-muted-text-color); background-color: transparent;'"
                                    >
                                        <span class="block">{{ __($item['label']) }}</span>
                                        <span
                                            class="inline-flex h-6 w-6 items-center justify-center rounded-full text-[10px] transition"
                                            x-bind:style="isActive(@js($item['route'] ?? null), @js((bool) ($item['active'] ?? false)))
                                                ? 'background-color: rgba(var(--theme-accent-rgb), 0.18); color: var(--theme-accent);'
                                                : 'background-color: rgba(var(--theme-border-color-rgb), 0.14); color: var(--theme-muted-text-color);'"
                                        >
                                            <i class="fa-light fa-spinner-third animate-spin" x-show="isPending(@js($item['route'] ?? null))"></i>
                                            <i class="fa-light fa-angle-right" x-show="!isPending(@js($item['route'] ?? null))"></i>
                                        </span>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
                </div>
            </div>
        </aside>

        <div class="relative z-0 min-w-0 {{ $contentWidthClass }}">
            <div class="[&>*:first-child]:!mt-0">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>
