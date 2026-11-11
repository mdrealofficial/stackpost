@component(theme_view('layouts.marketing', 'guest'), ['pageTitle' => $pageTitle])
    <section class="guest-marketing-shell guest-section-space mx-auto px-6 pt-10 lg:px-12 lg:pt-14">
        <div class="grid gap-8 xl:grid-cols-[0.3fr_0.7fr] xl:items-start">
            <aside class="self-start">
                <div class="space-y-6 lg:sticky lg:top-[7.5rem] xl:top-[8.5rem]">
                    <a href="{{ route('guest.blogs') }}" class="inline-flex items-center rounded-full border border-white/10 px-4 py-2 text-sm font-semibold transition hover:bg-white/[0.04]" style="color: var(--theme-header-text-color);">
                        <i class="fa-light fa-arrow-left mr-2"></i>
                        {{ __('Back to blog') }}
                    </a>

                    <div class="nova-card-surface rounded-[2rem] border border-white/8 p-6">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">{{ __('Article details') }}</p>
                        <div class="mt-5 space-y-5 text-sm">
                            @if ($blog->category)
                                <div>
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Category') }}</p>
                                    <p class="mt-2 font-medium" style="color: var(--theme-header-text-color);">{{ $blog->category->nameForLocale() }}</p>
                                </div>
                            @endif
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Published') }}</p>
                                <p class="mt-2 font-medium" style="color: var(--theme-header-text-color);">{{ $blog->publishedAtFormatted('M d, Y') ?: $blog->createdAtFormatted('M d, Y') }}</p>
                            </div>
                            @if ($blog->tags->isNotEmpty())
                                <div>
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Tags') }}</p>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @foreach ($blog->tags as $tag)
                                            <span class="inline-flex items-center rounded-full border border-white/10 px-3 py-1.5 text-xs font-medium" style="color: var(--theme-header-text-color);">
                                                {{ $tag->nameForLocale() }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Read focus') }}</p>
                                <p class="mt-2 leading-7" style="color: var(--theme-muted-text-color);">{{ __('Operational notes, systems thinking, and clean explanations for teams evaluating the product.') }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="nova-soft-surface rounded-[1.75rem] border border-white/8 p-6">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">{{ __('Keep reading') }}</p>
                        <p class="mt-3 text-sm leading-7" style="color: var(--theme-muted-text-color);">{{ __('Use the blog as part of the buying flow: explain the system, then route readers toward pricing, FAQs, or direct contact.') }}</p>
                        <div class="mt-5 flex flex-wrap gap-3">
                            <a href="{{ route('guest.pricing') }}" class="inline-flex items-center rounded-full border border-white/10 px-4 py-2 text-sm font-semibold transition hover:bg-white/[0.04]" style="color: var(--theme-header-text-color);">{{ __('Pricing') }}</a>
                            <a href="{{ route('guest.contact') }}" class="inline-flex items-center rounded-full border border-white/10 px-4 py-2 text-sm font-semibold transition hover:bg-white/[0.04]" style="color: var(--theme-header-text-color);">{{ __('Contact') }}</a>
                        </div>
                    </div>
                </div>
            </aside>

            <article class="space-y-8">
                <div class="space-y-5">
                    <div class="flex flex-wrap items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">
                        <span>{{ __('Editorial note') }}</span>
                        @if ($blog->category)
                            <span class="inline-flex items-center rounded-full border border-white/10 px-2.5 py-1">{{ $blog->category->nameForLocale() }}</span>
                        @endif
                        <span>{{ $blog->publishedAtFormatted('M d, Y') ?: $blog->createdAtFormatted('M d, Y') }}</span>
                    </div>
                    @if ($blog->tags->isNotEmpty())
                        <div class="flex flex-wrap gap-2">
                            @foreach ($blog->tags as $tag)
                                <span class="inline-flex items-center rounded-full border border-white/10 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">
                                    {{ $tag->nameForLocale() }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                    <h1 class="max-w-4xl text-[1.85rem] font-semibold leading-[1.12] tracking-[-0.03em] sm:text-[2.2rem] xl:text-[2.5rem]" style="color: var(--theme-header-text-color);">{{ $blog->titleForLocale() }}</h1>
                    @if ($blog->excerptForLocale() !== '')
                        <p class="max-w-3xl text-base leading-8 md:text-lg" style="color: var(--theme-muted-text-color);">{{ $blog->excerptForLocale() }}</p>
                    @endif
                </div>

                @if ($blog->thumbnailUrl())
                    <div class="nova-card-surface overflow-hidden rounded-[2.2rem] border border-white/8 shadow-[0_24px_80px_-50px_rgba(0,0,0,0.34)]">
                        <img src="{{ $blog->thumbnailUrl() }}" alt="{{ $blog->titleForLocale() }}" class="h-[24rem] w-full object-cover md:h-[30rem]">
                    </div>
                @endif

                <div class="nova-card-surface rounded-[2.2rem] border border-white/8 px-7 py-8 shadow-[0_24px_80px_-52px_rgba(0,0,0,0.26)] md:px-10 md:py-10 xl:px-12 xl:py-12">
                    <div class="guest-blog-content">
                        {!! $blog->normalizedContentForLocale() !!}
                    </div>
                </div>
            </article>
        </div>
    </section>

    @if ($relatedBlogs->isNotEmpty())
        <section class="guest-marketing-shell guest-section-space mx-auto px-6 pt-0 lg:px-12">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">{{ __('More reading') }}</p>
                    <h2 class="mt-3 text-3xl font-semibold tracking-tight" style="color: var(--theme-header-text-color);">{{ __('Related articles') }}</h2>
                </div>
                <a href="{{ route('guest.blogs') }}" class="text-sm font-semibold transition hover:text-cyan-300" style="color: var(--theme-accent-color);">{{ __('View all posts') }}</a>
            </div>
            <div class="mt-8 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($relatedBlogs as $relatedBlog)
                    @include(theme_view('partials.blog-card', 'guest'), ['blog' => $relatedBlog, 'compact' => true])
                @endforeach
            </div>
        </section>
    @endif

    <style>
        .guest-blog-content {
            color: var(--theme-muted-text-color);
            font-size: 1.03rem;
            line-height: 1.95;
        }
        .guest-blog-content > * + * {
            margin-top: 1.05rem;
        }
        .guest-blog-content h1,
        .guest-blog-content h2,
        .guest-blog-content h3,
        .guest-blog-content h4,
        .guest-blog-content h5,
        .guest-blog-content h6 {
            margin-top: 1.8rem;
            margin-bottom: 0.9rem;
            font-weight: 700;
            line-height: 1.2;
            color: var(--theme-header-text-color);
        }
        .guest-blog-content p,
        .guest-blog-content li {
            color: var(--theme-muted-text-color);
        }
        .guest-blog-content ul { list-style: disc; padding-left: 1.4rem; }
        .guest-blog-content ol { list-style: decimal; padding-left: 1.4rem; }
        .guest-blog-content blockquote {
            margin: 1.5rem 0;
            padding: 1.1rem 1.25rem;
            border-left: 4px solid rgba(var(--theme-accent-rgb), 0.45);
            background: color-mix(in srgb, var(--theme-card-color) 92%, white 8%);
            border-radius: 0 1rem 1rem 0;
            color: var(--theme-muted-text-color);
        }
        .guest-blog-content pre {
            overflow-x: auto;
            border-radius: 1rem;
            padding: 1rem 1.25rem;
            background: var(--theme-inverse-surface-color, #090b16);
            color: #e2e8f0;
        }
        .guest-blog-content figure {
            overflow: hidden;
            margin: 1.5rem auto;
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 1.25rem;
            background: color-mix(in srgb, var(--theme-card-color) 95%, white 5%);
        }
        .guest-blog-content figure img,
        .guest-blog-content figure video,
        .guest-blog-content figure iframe {
            display: block;
            width: 100%;
        }
        .guest-blog-content figure figcaption {
            padding: 0.85rem 1rem 1rem;
            color: var(--theme-muted-text-color);
            font-size: 0.95rem;
            line-height: 1.7;
        }
        .guest-blog-content a {
            color: var(--theme-accent-color);
            text-decoration: underline;
            text-underline-offset: 0.18em;
        }
    </style>
@endcomponent
