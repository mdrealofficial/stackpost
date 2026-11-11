@php
    $currentTheme = in_array($currentTheme ?? 'default', ['default', 'luma'], true) ? $currentTheme : 'default';
    $isGuestTheme = (bool) ($isGuestTheme ?? false);
@endphp

<div data-demo-theme-switcher-panel>
    <x-ui.modal
        title="{{ __('Choose a frontend theme') }}"
        description="{{ __('Preview available guest themes before switching the demo.') }}"
        width="xl"
        :dismissible="true"
        :close-on-backdrop="true"
        data-demo-theme-switcher-modal
    >
        <x-slot:trigger>
            <button
                type="button"
                aria-label="{{ __('Choose demo theme') }}"
                title="{{ __('Choose demo theme') }}"
                class="demo-theme-switcher-tab"
            >
                <span class="demo-theme-switcher-tab__icon" aria-hidden="true">
                    <i class="fa-light fa-palette"></i>
                </span>
                <span class="demo-theme-switcher-tab__label">{{ __('Themes') }}</span>
            </button>
        </x-slot:trigger>

        <div class="-mt-2 mb-5 flex flex-wrap items-center gap-2">
            <span class="demo-theme-switcher-chip"><i class="fa-light fa-palette"></i>{{ __('Theme styles') }}</span>
            <span class="demo-theme-switcher-chip"><i class="fa-light fa-eye"></i>{{ __('Live preview') }}</span>
            <span class="demo-theme-switcher-chip"><i class="fa-light fa-bolt"></i>{{ __('Quick switch') }}</span>
        </div>

        <div class="demo-theme-switcher-list">
            <div class="grid gap-4 md:grid-cols-2">
                @foreach ($themes as $theme)
                    @php($active = $theme['key'] === $currentTheme)

                    <article
                        class="overflow-hidden rounded-2xl border bg-white shadow-sm"
                        @style([
                            'border-color: rgba(var(--theme-accent-rgb),0.42); box-shadow: 0 0 0 3px rgba(var(--theme-accent-rgb),0.10);' => $active,
                        ])
                    >
                        <img
                            src="{{ $theme['preview'] }}"
                            alt="{{ $theme['label'] }} {{ __('theme preview') }}"
                            loading="lazy"
                            class="block aspect-[16/10] w-full border-b border-slate-200 bg-slate-50 object-cover"
                        >

                        <div class="space-y-4 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="flex items-center gap-2 text-lg font-black tracking-[-0.02em] text-slate-950">
                                    <i class="fa-light {{ $theme['key'] === 'luma' ? 'fa-sun-bright' : 'fa-layer-group' }} text-[var(--theme-accent)]"></i>
                                    <span>{{ $theme['label'] }}</span>
                                </h3>

                                @if ($active)
                                    <x-ui.badge variant="primary"><i class="fa-light fa-check me-1"></i>{{ __('Active') }}</x-ui.badge>
                                @endif
                            </div>

                            <p class="min-h-[3rem] text-sm leading-6 text-slate-500">{{ $theme['description'] }}</p>

                            <x-ui.button
                                type="button"
                                :variant="$active ? 'primary' : 'outline'"
                                block
                                class="demo-theme-switcher-action"
                                :data-active="$active ? 'true' : 'false'"
                                :disabled="$active"
                                onclick="window.demoThemeSwitcherChoose('{{ $theme['key'] }}')"
                            >
                                <i class="fa-light {{ $active ? 'fa-check-circle' : 'fa-eye' }}"></i>
                                {{ $active ? __('Current theme') : __('Preview this theme') }}
                            </x-ui.button>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </x-ui.modal>
</div>

<style data-demo-theme-switcher-style>
    .demo-theme-switcher-tab {
        position: fixed;
        right: 0;
        top: 50vh;
        z-index: 2147483000;
        width: 44px;
        min-height: 106px;
        transform: translateY(-50%);
        border: 1px solid rgba(226, 232, 240, .9);
        border-right: 0;
        border-radius: 14px 0 0 14px;
        background: rgba(255, 255, 255, .94);
        color: #0f172a;
        box-shadow: 0 18px 48px -24px rgba(15, 23, 42, .45);
        backdrop-filter: blur(16px);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        cursor: pointer;
        font-size: 12px;
        font-weight: 900;
        letter-spacing: .02em;
        animation: demo-theme-switcher-color-pulse 2.8s ease-in-out infinite;
        transition: transform .16s ease, background .16s ease, color .16s ease, box-shadow .16s ease;
    }

    .demo-theme-switcher-tab::before {
        content: "";
        position: absolute;
        inset: 8px;
        border-radius: 11px;
        background: rgba(var(--theme-accent-rgb, 15, 118, 110), .10);
        opacity: 0;
        transform: scale(.82);
        animation: demo-theme-switcher-attention 2.4s ease-in-out infinite;
        pointer-events: none;
    }

    .demo-theme-switcher-tab:hover {
        transform: translateY(-50%);
        animation-play-state: paused;
        border-color: rgba(var(--theme-accent-rgb, 15, 118, 110), .45);
        background: rgba(var(--theme-accent-rgb, 15, 118, 110), .10);
        color: rgb(var(--theme-accent-rgb, 15, 118, 110));
        box-shadow: 0 22px 56px -24px rgba(15, 23, 42, .55);
    }

    .demo-theme-switcher-tab:hover::before {
        animation-play-state: paused;
        opacity: .18;
        transform: scale(1);
    }

    .demo-theme-switcher-tab__icon {
        display: inline-flex;
        width: 18px;
        height: 18px;
        align-items: center;
        justify-content: center;
        margin-bottom: 8px;
        flex: 0 0 auto;
        font-size: 17px;
        line-height: 1;
        position: relative;
        z-index: 1;
        animation: demo-theme-switcher-icon-pulse 2.4s ease-in-out infinite;
    }

    .demo-theme-switcher-tab__label {
        writing-mode: vertical-rl;
        text-orientation: mixed;
        line-height: 1;
        position: relative;
        z-index: 1;
    }

    .demo-theme-switcher-chip {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        border: 1px solid rgba(203, 213, 225, .92);
        border-radius: 999px;
        background: #f8fafc;
        color: #475569;
        padding: 7px 10px;
        font-size: 12px;
        font-weight: 800;
        line-height: 1;
    }

    .demo-theme-switcher-chip i {
        color: rgb(var(--theme-accent-rgb, 15, 118, 110));
        font-size: 13px;
    }

    .demo-theme-switcher-list {
        max-height: min(58vh, 560px);
        overflow-y: auto;
        overscroll-behavior: contain;
        padding: 3px;
        scrollbar-width: thin;
        scrollbar-color: rgba(100, 116, 139, .45) transparent;
    }

    .demo-theme-switcher-list::-webkit-scrollbar {
        width: 8px;
    }

    .demo-theme-switcher-list::-webkit-scrollbar-track {
        background: transparent;
    }

    .demo-theme-switcher-list::-webkit-scrollbar-thumb {
        border-radius: 999px;
        background: rgba(100, 116, 139, .42);
    }

    @keyframes demo-theme-switcher-attention {
        0%,
        100% {
            opacity: 0;
            transform: scale(.82);
        }

        50% {
            opacity: .22;
            transform: scale(1);
        }
    }

    @keyframes demo-theme-switcher-icon-pulse {
        0%,
        100% {
            transform: scale(1);
        }

        50% {
        transform: scale(1.12);
        }
    }

    @keyframes demo-theme-switcher-color-pulse {
        0%,
        100% {
            border-color: rgba(226, 232, 240, .9);
            background: rgba(255, 255, 255, .94);
            color: #0f172a;
        }

        50% {
            border-color: rgba(var(--theme-accent-rgb, 15, 118, 110), .40);
            background: rgba(var(--theme-accent-rgb, 15, 118, 110), .10);
            color: rgb(var(--theme-accent-rgb, 15, 118, 110));
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .demo-theme-switcher-tab,
        .demo-theme-switcher-tab::before,
        .demo-theme-switcher-tab__icon {
            animation: none;
        }
    }

    body:has([data-demo-theme-switcher-panel]) > .fixed.inset-0.z-\[120\] > .absolute.inset-0.bg-white\/55 {
        background-color: rgba(15, 23, 42, .56) !important;
        backdrop-filter: blur(12px) saturate(.85) !important;
    }

    body:has([data-demo-theme-switcher-panel]) > .fixed.inset-0.z-\[120\] [style*="--theme-surface-overlay"] {
        background-color: #ffffff !important;
        border-color: rgba(203, 213, 225, .95) !important;
        color: #0f172a !important;
    }

    body:has([data-demo-theme-switcher-panel]) > .fixed.inset-0.z-\[120\] h1,
    body:has([data-demo-theme-switcher-panel]) > .fixed.inset-0.z-\[120\] h2,
    body:has([data-demo-theme-switcher-panel]) > .fixed.inset-0.z-\[120\] h3,
    body:has([data-demo-theme-switcher-panel]) > .fixed.inset-0.z-\[120\] h4 {
        color: #0f172a !important;
    }

    body:has([data-demo-theme-switcher-panel]) > .fixed.inset-0.z-\[120\] p {
        color: #64748b !important;
    }

    body:has([data-demo-theme-switcher-panel]) > .fixed.inset-0.z-\[120\] article {
        background-color: #ffffff !important;
        border-color: rgba(203, 213, 225, .92) !important;
    }

    body:has([data-demo-theme-switcher-panel]) > .fixed.inset-0.z-\[120\] .border-t {
        border-top-color: #e2e8f0 !important;
    }

    body:has([data-demo-theme-switcher-panel]) > .fixed.inset-0.z-\[120\] .border-b {
        border-bottom-color: #e2e8f0 !important;
    }

    body:has([data-demo-theme-switcher-panel]) > .fixed.inset-0.z-\[120\] button:not(.demo-theme-switcher-tab),
    body:has([data-demo-theme-switcher-panel]) > .fixed.inset-0.z-\[120\] a {
        color-scheme: light;
    }

    body:has([data-demo-theme-switcher-panel]) > .fixed.inset-0.z-\[120\] .demo-theme-switcher-action {
        border-color: #cbd5e1 !important;
        background-color: #ffffff !important;
        color: #0f172a !important;
        opacity: 1 !important;
    }

    body:has([data-demo-theme-switcher-panel]) > .fixed.inset-0.z-\[120\] .demo-theme-switcher-action:hover {
        border-color: rgba(var(--theme-accent-rgb, 15, 118, 110), .45) !important;
        background-color: rgba(var(--theme-accent-rgb, 15, 118, 110), .08) !important;
        color: rgb(var(--theme-accent-rgb, 15, 118, 110)) !important;
    }

    body:has([data-demo-theme-switcher-panel]) > .fixed.inset-0.z-\[120\] .demo-theme-switcher-action[data-active="true"] {
        border-color: rgb(var(--theme-accent-rgb, 15, 118, 110)) !important;
        background-color: rgb(var(--theme-accent-rgb, 15, 118, 110)) !important;
        color: #ffffff !important;
    }

    body:has([data-demo-theme-switcher-panel]) > .fixed.inset-0.z-\[120\] {
        color-scheme: light;
    }
</style>

<script data-demo-theme-switcher-script>
    window.demoThemeSwitcherChoose = (theme) => {
        const expires = new Date(Date.now() + 86400 * 30 * 1000).toUTCString();
        document.cookie = `demo_frontend_theme=${theme}; path=/; expires=${expires}; SameSite=Lax`;

        if (@js($isGuestTheme)) {
            const url = new URL(window.location.href);
            url.searchParams.set('demo_theme', theme);
            window.location.assign(url.toString());
            return;
        }

        window.location.assign(new URL('/?demo_theme=' + encodeURIComponent(theme), window.location.origin).toString());
    };
</script>
