@component(theme_view('layouts.marketing', 'guest'), ['pageTitle' => $pageTitle])
    @push('styles')
        @include(theme_view('partials.stylesheet', 'guest'), ['stylesheet' => 'blogs'])
    @endpush

    <div class="lm-subpage">
        <section class="lm-page-hero lm-square-hero">
            <div class="lm-page-shell lm-square-hero-layout">
                <div class="lm-square-hero-copy lm-page-reveal lm-page-reveal-left">
                    <span class="lm-page-eyebrow"><i class="fa-light fa-newspaper"></i>{{ __('Publishing library') }}</span>
                    <h1 class="lm-page-title">{{ __('Ideas for a sharper social workflow.') }}</h1>
                    <p class="lm-page-copy">{{ __('Explore practical notes about planning, creating, publishing, automating, and measuring social content from one workspace.') }}</p>
                    <form method="GET" action="{{ route('guest.blogs') }}" class="lm-search" role="search">
                        <i class="fa-light fa-magnifying-glass"></i>
                        <input name="q" value="{{ $filters['q'] }}" placeholder="{{ __('Search the publishing library...') }}" aria-label="{{ __('Search blog posts') }}">
                        <button type="submit" class="lm-button" aria-label="{{ __('Search') }}"><i class="fa-light fa-arrow-right"></i></button>
                    </form>
                    <div class="lm-blog-signal"><span><i class="fa-light fa-calendar-lines"></i>{{ __('Planning') }}</span><span><i class="fa-light fa-wand-magic-sparkles"></i>{{ __('Creation') }}</span><span><i class="fa-light fa-chart-line"></i>{{ __('Analytics') }}</span></div>
                </div>

                @include(theme_view('partials.hero-squares', 'guest'), [
                    'heroCoreIcon' => 'fa-newspaper',
                    'heroCoreLabel' => __('Publishing library'),
                    'heroCoreTitle' => __('Ideas in context.'),
                    'heroCoreStatus' => __('Fresh workflow notes'),
                    'heroTiles' => [
                        ['icon' => 'fa-calendar-lines', 'label' => __('Plan'), 'title' => __('Build a cadence')],
                        ['icon' => 'fa-wand-magic-sparkles', 'label' => __('Create'), 'title' => __('Shape content')],
                        ['icon' => 'fa-paper-plane', 'label' => __('Publish'), 'title' => __('Reach channels')],
                        ['icon' => 'fa-arrows-rotate', 'label' => __('Automate'), 'title' => __('Reduce repeats')],
                        ['icon' => 'fa-chart-line', 'label' => __('Analyze'), 'title' => __('Review signals')],
                        ['icon' => 'fa-lightbulb', 'label' => __('Learn'), 'title' => __('Improve the flow')],
                    ],
                ])
            </div>
        </section>

        @if ($featuredPost)
            <section class="lm-section">
                <div class="lm-page-shell">
                    <div class="lm-section-heading lm-page-reveal"><div><p class="lm-section-kicker">{{ __('Featured article') }}</p><h2 class="lm-section-title">{{ __('Start with the latest operating note.') }}</h2></div></div>
                    <article class="lm-card lm-blog-featured lm-page-reveal">
                        <a href="{{ route('guest.blog-show', $featuredPost->slug) }}" class="lm-blog-featured-media">
                            <div class="lm-blog-media-fallback"><div class="lm-blog-media-window"><span class="lm-icon-tile"><i class="fa-light fa-newspaper"></i></span><div class="lm-blog-media-line" style="width:78%;margin-top:22px;"></div><div class="lm-blog-media-line" style="width:100%;"></div><div class="lm-blog-media-line" style="width:58%;"></div></div></div>
                            @if ($featuredPost->thumbnailUrl())<img src="{{ $featuredPost->thumbnailUrl() }}" alt="{{ $featuredPost->titleForLocale() }}" onerror="this.style.display='none'">@endif
                        </a>
                        <div class="lm-blog-featured-body">
                            <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;"><span class="lm-chip">{{ $featuredPost->category?->nameForLocale() ?: __('Article') }}</span><span class="lm-meta">{{ $featuredPost->publishedAtFormatted('M d, Y') ?: $featuredPost->createdAtFormatted('M d, Y') }}</span></div>
                            <h2><a href="{{ route('guest.blog-show', $featuredPost->slug) }}">{{ $featuredPost->titleForLocale() }}</a></h2>
                            <p>{{ $featuredPost->contentPreview(220) }}</p>
                            <a href="{{ route('guest.blog-show', $featuredPost->slug) }}" class="lm-blog-featured-link">{{ __('Read article') }}<i class="fa-light fa-arrow-right"></i></a>
                        </div>
                    </article>
                </div>
            </section>
        @endif

        <section class="lm-section {{ $featuredPost ? 'lm-section-soft' : '' }}">
            <div class="lm-page-shell">
                <div class="lm-section-heading lm-page-reveal">
                    <div><p class="lm-section-kicker">{{ __('All articles') }}</p><h2 class="lm-section-title">{{ $filters['q'] !== '' ? __('Search results') : __('More ways to improve the workflow.') }}</h2></div>
                    @if($filters['q'] !== '')<a href="{{ route('guest.blogs') }}" class="lm-button-secondary">{{ __('Clear search') }}<i class="fa-light fa-xmark"></i></a>@endif
                </div>

                <div class="lm-blog-grid">
                    @forelse ($blogs as $blog)
                        @continue($featuredPost && $filters['q'] === '' && $blog->getKey() === $featuredPost->getKey())
                        <article class="lm-card lm-card-hover lm-blog-card lm-page-reveal" style="--lm-delay:{{ min($loop->index * 55,330) }}ms;">
                            <a href="{{ route('guest.blog-show', $blog->slug) }}" class="lm-blog-card-media">
                                <div class="lm-blog-media-fallback"><span class="lm-icon-tile"><i class="fa-light fa-newspaper"></i></span></div>
                                @if ($blog->thumbnailUrl())<img src="{{ $blog->thumbnailUrl() }}" alt="{{ $blog->titleForLocale() }}" loading="lazy" onerror="this.style.display='none'">@endif
                            </a>
                            <div class="lm-blog-card-body">
                                <span class="lm-meta">{{ $blog->category?->nameForLocale() ?: __('Article') }}</span>
                                <h3><a href="{{ route('guest.blog-show', $blog->slug) }}">{{ $blog->titleForLocale() }}</a></h3>
                                <p>{{ $blog->contentPreview(145) }}</p>
                                <div class="lm-blog-card-footer"><span>{{ $blog->publishedAtFormatted('M d, Y') ?: $blog->createdAtFormatted('M d, Y') }}</span><i class="fa-light fa-arrow-up-right"></i></div>
                            </div>
                        </article>
                    @empty
                        <div class="lm-empty lm-page-reveal" style="grid-column:1/-1;"><i class="fa-light fa-newspaper" style="font-size:24px;"></i><p style="margin:14px 0 0;">{{ __('No blog posts found.') }}</p></div>
                    @endforelse
                </div>

                @if ($blogs->hasPages())
                    <nav class="lm-pagination" aria-label="Blog pagination">
                        @if ($blogs->onFirstPage())<span class="lm-pagination-item is-disabled"><i class="fa-light fa-chevron-left"></i></span>@else<a href="{{ $blogs->previousPageUrl() }}" class="lm-pagination-item"><i class="fa-light fa-chevron-left" aria-hidden="true"></i><span class="sr-only">{{ __('Previous page') }}</span></a>@endif
                        @foreach ($blogs->getUrlRange(1, $blogs->lastPage()) as $page => $url)
                            @if ($page === $blogs->currentPage())<span class="lm-pagination-item is-active" aria-current="page">{{ $page }}</span>@else<a href="{{ $url }}" class="lm-pagination-item">{{ $page }}</a>@endif
                        @endforeach
                        @if ($blogs->hasMorePages())<a href="{{ $blogs->nextPageUrl() }}" class="lm-pagination-item"><i class="fa-light fa-chevron-right" aria-hidden="true"></i><span class="sr-only">{{ __('Next page') }}</span></a>@else<span class="lm-pagination-item is-disabled"><i class="fa-light fa-chevron-right"></i></span>@endif
                    </nav>
                @endif
            </div>
        </section>
    </div>
@endcomponent
