@component(theme_view('layouts.marketing', 'guest'), ['pageTitle' => $pageTitle, 'pageDescription' => $pageDescription, 'pageImage' => $pageImage])
    @push('styles')
        @include(theme_view('partials.stylesheet', 'guest'), ['stylesheet' => 'article'])
    @endpush

    <div class="lm-subpage">
        <section class="lm-page-hero lm-article-hero">
            <div class="lm-page-shell">
                <a href="{{ route('guest.blogs') }}" class="lm-article-back lm-page-reveal"><i class="fa-light fa-arrow-left"></i>{{ __('Back to blog') }}</a>
                <header class="lm-article-head lm-page-reveal">
                    <div class="lm-article-meta">
                        @if($blog->category)<span class="lm-chip">{{ $blog->category->nameForLocale() }}</span>@endif
                        <span class="lm-chip"><i class="fa-light fa-calendar"></i>{{ $blog->publishedAtFormatted('M d, Y') ?: $blog->createdAtFormatted('M d, Y') }}</span>
                    </div>
                    <h1>{{ $blog->titleForLocale() }}</h1>
                    <p>{{ $blog->contentPreview(240) }}</p>
                    @if(str_starts_with($blog->slug, 'demo-preview-'))
                        <aside class="lm-demo-note"><strong>{{ __('Demo walkthrough') }}</strong> {{ __('This article uses sample content and demo accounts to explain the workflow. It does not show a live customer workspace.') }}</aside>
                    @endif
                </header>
                <div class="lm-card lm-article-cover lm-page-reveal">
                    <div class="lm-article-cover-fallback"><div class="lm-article-cover-window"><span class="lm-icon-tile"><i class="fa-light fa-newspaper"></i></span><h2>{{ $blog->category?->nameForLocale() ?: __('Publishing insight') }}</h2><p class="lm-section-copy">{{ __('A practical note from the social publishing library.') }}</p></div></div>
                    @if($blog->thumbnailUrl())<img src="{{ $blog->thumbnailUrl() }}" alt="{{ $blog->titleForLocale() }}" onerror="this.style.display='none'">@endif
                </div>
            </div>
        </section>

        <section class="lm-section">
            <div class="lm-page-shell lm-article-layout">
                <article class="lm-card lm-article-content lm-page-reveal lm-page-reveal-left">
                    <div class="lm-prose prose max-w-none">{!! $blog->normalizedContentForLocale() !!}</div>
                </article>
                <aside class="lm-article-aside lm-page-reveal lm-page-reveal-right">
                    <div class="lm-card lm-article-aside-card">
                        <span class="lm-meta">{{ __('Next step') }}</span>
                        <h2>{{ __('Build your publishing workspace.') }}</h2>
                        <p>{{ __('Compare the current plans and included limits before choosing a workspace.') }}</p>
                        <a href="{{ route('guest.pricing') }}" class="lm-button" style="width:100%;margin-top:18px;">{{ __('View pricing') }}<i class="fa-light fa-arrow-right"></i></a>
                    </div>
                    <div class="lm-card lm-article-aside-card">
                        <span class="lm-meta">{{ __('Explore') }}</span>
                        <div class="lm-article-links"><a href="{{ route('guest.faqs') }}" class="lm-article-link">{{ __('FAQs') }}<i class="fa-light fa-arrow-right"></i></a><a href="{{ route('guest.contact') }}" class="lm-article-link">{{ __('Contact') }}<i class="fa-light fa-arrow-right"></i></a><a href="{{ route('guest.blogs') }}" class="lm-article-link">{{ __('All articles') }}<i class="fa-light fa-arrow-right"></i></a></div>
                    </div>
                </aside>
            </div>
        </section>

        @if($relatedBlogs->isNotEmpty())
            <section class="lm-section lm-section-soft">
                <div class="lm-page-shell">
                    <div class="lm-section-heading lm-page-reveal"><div><p class="lm-section-kicker">{{ __('Continue reading') }}</p><h2 class="lm-section-title">{{ __('More from the publishing library.') }}</h2></div><a href="{{ route('guest.blogs') }}" class="lm-button-secondary">{{ __('All articles') }}<i class="fa-light fa-arrow-right"></i></a></div>
                    <div class="lm-related-grid">
                        @foreach($relatedBlogs as $related)
                            <article class="lm-card lm-card-hover lm-related-card lm-page-reveal" style="--lm-delay:{{ $loop->index * 70 }}ms;"><span class="lm-meta">{{ $related->category?->nameForLocale() ?: __('Article') }}</span><h3><a href="{{ route('guest.blog-show',$related->slug) }}">{{ $related->titleForLocale() }}</a></h3><p>{{ $related->contentPreview(130) }}</p></article>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif
    </div>
@endcomponent
