@component(theme_view('layouts.marketing', 'guest'), ['pageTitle' => $pageTitle])
    <section class="guest-marketing-shell guest-section-space mx-auto px-6 pt-10 lg:px-12 lg:pt-14">
        <div class="grid gap-8 xl:grid-cols-[0.72fr_1.28fr] xl:items-start">
            <div class="space-y-6 xl:sticky xl:top-32">
                <div class="space-y-5">
                    <span class="inline-flex items-center rounded-full border border-emerald-400/20 bg-emerald-400/8 px-4 py-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-emerald-300">
                        {{ __('Automation journal') }}
                    </span>
                    <div class="space-y-4">
                        <h1 class="max-w-xl text-[2.15rem] font-semibold leading-[1.08] tracking-[-0.04em] sm:text-[2.55rem] xl:text-[3rem]" style="color: var(--theme-header-text-color);">
                            {{ __('Stories, systems, and practical notes from the publishing layer.') }}
                        </h1>
                        <p class="max-w-lg text-base leading-8 md:text-lg" style="color: var(--theme-muted-text-color);">
                            {{ __('Use the blog to explain how your workflow actually runs: decisions, automations, operations, and the small systems that keep content moving.') }}
                        </p>
                    </div>
                </div>

                <div class="nova-card-surface rounded-[2rem] border border-white/8 p-6 shadow-[0_20px_60px_-40px_rgba(0,0,0,0.28)]">
                    <form method="GET" action="{{ route('guest.blogs') }}" class="grid gap-4">
                        <label for="blog-search" class="text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">
                            {{ __('Find a post') }}
                        </label>
                        <input id="blog-search" name="q" value="{{ $filters['q'] }}" placeholder="{{ __('Search blog posts...') }}" class="nova-soft-surface w-full rounded-2xl border border-white/10 px-5 py-4 text-sm outline-none transition focus:border-violet-400/40" style="color: var(--theme-header-text-color);">
                        <button type="submit" class="inline-flex items-center justify-center rounded-full px-6 py-4 text-sm font-semibold text-white transition hover:scale-[1.01]" style="background: linear-gradient(135deg, #8b5cf6 0%, #4f46e5 48%, #22d3ee 100%);">
                            {{ __('Search articles') }}
                        </button>
                    </form>
                </div>
            </div>

            <div>
                @if ($featuredPost)
                    <article class="nova-card-surface group overflow-hidden rounded-[2.3rem] border border-white/8 shadow-[0_28px_90px_-52px_rgba(0,0,0,0.34)]">
                        <div class="grid gap-0 lg:grid-cols-[1.1fr_0.9fr]">
                            <a href="{{ route('guest.blog-show', $featuredPost->slug) }}" class="relative block min-h-[22rem] overflow-hidden">
                                @if ($featuredPost->thumbnailUrl())
                                    <img src="{{ $featuredPost->thumbnailUrl() }}" alt="{{ $featuredPost->titleForLocale() }}" class="h-full w-full object-cover transition duration-700 group-hover:scale-[1.03]">
                                @else
                                    <div class="h-full w-full bg-[linear-gradient(135deg,rgba(34,211,238,0.10)_0%,rgba(139,92,246,0.16)_44%,rgba(17,24,39,0.92)_100%)]"></div>
                                @endif
                                <div class="pointer-events-none absolute inset-0 bg-[linear-gradient(180deg,rgba(10,14,28,0.06)_0%,rgba(10,14,28,0.18)_48%,rgba(10,14,28,0.72)_100%)]"></div>
                                <div class="absolute inset-x-0 bottom-0 flex items-end justify-between px-6 py-6">
                                    <div class="space-y-2">
                                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-white/46">{{ __('Featured article') }}</p>
                                        <p class="max-w-sm text-xl font-semibold leading-7 text-white">{{ $featuredPost->titleForLocale() }}</p>
                                    </div>
                                    <span class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-white/[0.08] text-cyan-100 backdrop-blur-sm">
                                        <i class="fa-light fa-arrow-up-right text-lg"></i>
                                    </span>
                                </div>
                            </a>

                            <div class="flex flex-col justify-between p-7 md:p-8">
                                <div class="space-y-5">
                                    <div class="flex flex-wrap items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.2em]" style="color: var(--theme-muted-text-color);">
                                        @if ($featuredPost->category)
                                            <span class="inline-flex items-center rounded-full border border-white/10 px-2.5 py-1">{{ $featuredPost->category->nameForLocale() }}</span>
                                        @endif
                                        <span>{{ $featuredPost->publishedAtFormatted('M d, Y') ?: $featuredPost->createdAtFormatted('M d, Y') }}</span>
                                    </div>
                                    <div class="space-y-4">
                                        <h2 class="text-3xl font-semibold leading-[1.08] tracking-[-0.04em]" style="color: var(--theme-header-text-color);">
                                            <a href="{{ route('guest.blog-show', $featuredPost->slug) }}">{{ $featuredPost->titleForLocale() }}</a>
                                        </h2>
                                        <p class="text-sm leading-8 md:text-[0.96rem]" style="color: var(--theme-muted-text-color);">
                                            {{ $featuredPost->contentPreview(220) }}
                                        </p>
                                    </div>
                                </div>

                                <div class="mt-8 flex items-center justify-between border-t border-white/8 pt-5">
                                    <span class="text-sm" style="color: var(--theme-muted-text-color);">{{ __('Built for operators, not generic readers.') }}</span>
                                    <a href="{{ route('guest.blog-show', $featuredPost->slug) }}" class="inline-flex items-center rounded-full border border-white/10 px-4 py-2 text-sm font-semibold transition hover:bg-white/[0.05]" style="color: var(--theme-header-text-color);">
                                        {{ __('Read article') }}
                                        <i class="fa-light fa-arrow-right ml-2"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </article>
                @endif
            </div>
        </div>

        <div class="mt-10 flex items-end justify-between gap-4">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">{{ __('Latest notes') }}</p>
                <h2 class="mt-3 text-2xl font-semibold tracking-[-0.04em] md:text-[2rem]" style="color: var(--theme-header-text-color);">{{ __('Recent articles and operating playbooks') }}</h2>
            </div>
            <div class="hidden rounded-full border border-white/10 px-4 py-2 text-sm md:inline-flex" style="color: var(--theme-muted-text-color);">
                {{ $blogs->total() }} {{ __('posts') }}
            </div>
        </div>

        <div class="mt-10 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($blogs as $blog)
                @include(theme_view('partials.blog-card', 'guest'), ['blog' => $blog, 'compact' => true])
            @empty
                <div class="nova-card-surface rounded-[2rem] border border-dashed border-white/12 px-6 py-12 text-center text-sm xl:col-span-3" style="color: var(--theme-muted-text-color);">{{ __('No blog posts found.') }}</div>
            @endforelse
        </div>

        @if ($blogs->hasPages())
            <div class="mt-8">{{ $blogs->links() }}</div>
        @endif
    </section>
@endcomponent
