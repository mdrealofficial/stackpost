@component(theme_view('layouts.marketing', 'guest'), ['pageTitle' => $pageTitle])
    @php
        $hasHtml = str_contains($pageContent, '<') && str_contains($pageContent, '>');
        $pageType = $pageType ?? 'html';
        $socialLinks = $socialLinks ?? [];
        $socialStyles = [
            'Facebook' => ['rgb' => '24,119,242', 'text' => '#8ec5ff'],
            'Instagram' => ['rgb' => '225,48,108', 'text' => '#ff9ac2'],
            'LinkedIn' => ['rgb' => '10,102,194', 'text' => '#8fc4ff'],
            'X' => ['rgb' => '148,163,184', 'text' => '#d7deea'],
            'YouTube' => ['rgb' => '255,0,0', 'text' => '#ff9a9a'],
            'TikTok' => ['rgb' => '37,244,238', 'text' => '#8ff7f2'],
            'Telegram' => ['rgb' => '38,165,228', 'text' => '#91dbff'],
            'WhatsApp' => ['rgb' => '37,211,102', 'text' => '#9ff0bd'],
        ];
        $eyebrow = $pageType === 'social'
            ? __('Social directory')
            : (str_contains(strtolower($pageTitle), 'privacy')
                ? __('Privacy')
                : (str_contains(strtolower($pageTitle), 'term')
                    ? __('Legal')
                    : __('Information')));
        $pageTabs = [
            ['label' => __('Privacy Policy'), 'route' => 'guest.privacy-policy'],
            ['label' => __('Terms of Use'), 'route' => 'guest.terms-of-use'],
            ['label' => __('Social Pages'), 'route' => 'guest.social-pages'],
        ];
    @endphp

    <section class="guest-marketing-shell guest-section-space mx-auto px-6 pt-10 lg:px-12 lg:pt-14">
        <div class="mx-auto max-w-5xl">
            <div class="space-y-5">
                <span class="inline-flex items-center rounded-full border border-violet-400/14 bg-violet-400/10 px-4 py-2 text-[11px] font-semibold uppercase tracking-[0.22em] text-violet-200 shadow-[0_0_24px_rgba(139,92,246,0.10)]">
                    {{ $eyebrow }}
                </span>

                <div class="space-y-4">
                    <h1 class="max-w-3xl text-[2rem] font-semibold leading-[1.06] tracking-[-0.04em] sm:text-[2.35rem] lg:text-[2.75rem]" style="color: var(--theme-header-text-color);">
                        {{ $pageTitle }}
                    </h1>
                    <p class="max-w-3xl text-base leading-8" style="color: var(--theme-muted-text-color);">
                        {{ $pageType === 'social'
                            ? __('Manage public-facing social destinations from admin and keep them aligned with the rest of the marketing frontend.')
                            : __('Public legal and informational pages should feel clear and lightweight, not like another landing-page hero.') }}
                    </p>
                </div>
            </div>

            <div class="mt-8 flex flex-wrap gap-2 rounded-[1.5rem] border border-white/8 p-3 shadow-[0_18px_48px_-38px_rgba(0,0,0,0.34)] nova-card-surface">
                @foreach ($pageTabs as $tab)
                    <a
                        href="{{ route($tab['route']) }}"
                        class="{{ $routeName === $tab['route'] ? 'bg-white text-zinc-950' : 'border border-white/10 bg-white/[0.03] hover:bg-white/8' }} rounded-full px-4 py-2 text-sm font-semibold transition"
                        style="{{ $routeName === $tab['route'] ? '' : 'color: var(--theme-muted-text-color);' }}"
                    >
                        {{ $tab['label'] }}
                    </a>
                @endforeach
            </div>

            <div class="mt-6 overflow-hidden rounded-[2rem] border border-white/8 shadow-[0_24px_70px_-46px_rgba(0,0,0,0.32)] nova-card-surface">
                <div class="border-b border-white/8 px-7 py-6 md:px-9">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">{{ __('Page content') }}</p>
                </div>

                <div class="px-7 py-7 md:px-9 md:py-8">
                @if ($pageType === 'social')
                    @if (trim($pageContent) !== '')
                        <div class="mb-8 max-w-3xl text-base leading-8 whitespace-pre-line" style="color: var(--theme-muted-text-color);">{{ $pageContent }}</div>
                    @endif

                    @if ($socialLinks !== [])
                        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-[repeat(auto-fit,minmax(18rem,1fr))]">
                            @foreach ($socialLinks as $link)
                                @php
                                    $style = $socialStyles[$link['label']] ?? ['rgb' => '139,92,246', 'text' => '#c4b5fd'];
                                @endphp
                                <a
                                    href="{{ $link['url'] }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="group rounded-[1.4rem] border p-5 transition hover:-translate-y-0.5 nova-soft-surface"
                                    style="border-color: rgba({{ $style['rgb'] }}, 0.14); background: linear-gradient(180deg, color-mix(in srgb, rgba({{ $style['rgb'] }}, 0.08) 22%, var(--nova-soft-bg) 78%) 0%, var(--nova-soft-bg) 100%); box-shadow: inset 0 1px 0 rgba(255,255,255,0.02);"
                                >
                                    <div class="flex items-center gap-4">
                                        <span
                                            class="guest-social-link-icon flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl"
                                            style="background: linear-gradient(180deg, rgba({{ $style['rgb'] }}, 0.20) 0%, rgba({{ $style['rgb'] }}, 0.09) 100%); color: {{ $style['text'] }}; box-shadow: inset 0 1px 0 rgba(255,255,255,0.05);"
                                        >
                                            <i class="{{ $link['icon'] }} block text-[1.25rem] leading-none"></i>
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <p class="font-semibold" style="color: var(--theme-header-text-color);">{{ $link['label'] }}</p>
                                            <p class="mt-1 truncate text-sm transition" style="color: var(--theme-muted-text-color);">{{ $link['url'] }}</p>
                                        </div>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <div class="rounded-[1.6rem] border border-dashed border-white/12 px-6 py-12 text-center text-sm" style="color: var(--theme-muted-text-color);">
                            {{ __('No social links have been configured yet.') }}
                        </div>
                    @endif
                @elseif (trim($pageContent) === '')
                    <div class="rounded-[1.6rem] border border-dashed border-white/12 px-6 py-12 text-center text-sm" style="color: var(--theme-muted-text-color);">
                        {{ __('This page has not been configured yet.') }}
                    </div>
                @elseif ($hasHtml)
                    <div class="guest-static-content">
                        {!! $pageContent !!}
                    </div>
                @else
                    <div class="guest-static-content whitespace-pre-line">
                        {{ $pageContent }}
                    </div>
                @endif
            </div>
        </div>
    </section>

    <style>
        html[data-theme-resolved='light'] .guest-social-link-icon {
            filter: saturate(1.3) contrast(1.18);
        }
        html[data-theme-resolved='light'] .guest-social-link-icon i {
            opacity: 1;
            filter: saturate(1.25) contrast(1.2);
        }

        .guest-static-content {
            color: var(--theme-muted-text-color);
            font-size: 1rem;
            line-height: 1.9;
        }
        .guest-static-content > * + * {
            margin-top: 1rem;
        }
        .guest-static-content h1,
        .guest-static-content h2,
        .guest-static-content h3,
        .guest-static-content h4,
        .guest-static-content h5,
        .guest-static-content h6 {
            margin-top: 1.8rem;
            margin-bottom: 0.9rem;
            font-weight: 700;
            line-height: 1.2;
            color: var(--theme-header-text-color);
        }
        .guest-static-content ul { list-style: disc; padding-left: 1.4rem; }
        .guest-static-content ol { list-style: decimal; padding-left: 1.4rem; }
        .guest-static-content blockquote {
            margin: 1.5rem 0;
            padding: 1rem 1.25rem;
            border-left: 4px solid rgba(var(--theme-accent-rgb), 0.45);
            background: color-mix(in srgb, var(--theme-card-color) 94%, white 6%);
            border-radius: 0 1rem 1rem 0;
            color: var(--theme-muted-text-color);
        }
        .guest-static-content pre {
            overflow-x: auto;
            border-radius: 1rem;
            padding: 1rem 1.25rem;
            background: var(--theme-inverse-surface-color, #090b16);
            color: #e2e8f0;
        }
        .guest-static-content figure {
            overflow: hidden;
            margin: 1.5rem auto;
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 1.25rem;
            background: color-mix(in srgb, var(--theme-card-color) 95%, white 5%);
        }
        .guest-static-content figure img,
        .guest-static-content figure video,
        .guest-static-content figure iframe {
            display: block;
            width: 100%;
        }
        .guest-static-content figure figcaption {
            padding: 0.85rem 1rem 1rem;
            color: var(--theme-muted-text-color);
            font-size: 0.95rem;
            line-height: 1.7;
        }
    </style>
@endcomponent
