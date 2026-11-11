@component(theme_view('layouts.marketing', 'guest'), ['pageTitle' => $pageTitle])
    @push('styles')
        @include(theme_view('partials.stylesheet', 'guest'), ['stylesheet' => 'faqs'])
    @endpush

    <div class="lm-subpage lm-faq-page">
        <section class="lm-page-hero lm-square-hero">
            <div class="lm-page-shell">
                <div class="lm-square-hero-layout">
                    <div class="lm-square-hero-copy lm-page-reveal lm-page-reveal-left">
                        <span class="lm-page-eyebrow"><i class="fa-light fa-circle-question"></i>{{ __('Frequently asked questions') }}</span>
                        <h1 class="lm-page-title">{{ __('Clear answers before you start.') }}</h1>
                        <p class="lm-page-copy">{{ __('Find practical details about plans, connected social profiles, publishing, AI tools, analytics, billing, and team workspaces.') }}</p>

                        <form method="GET" action="{{ route('guest.faqs') }}" class="lm-search" role="search">
                            <i class="fa-light fa-magnifying-glass"></i>
                            <input id="faq-search" name="q" value="{{ $filters['q'] }}" placeholder="{{ __('Search plans, profiles, publishing...') }}" aria-label="{{ __('Search answers') }}">
                            <button type="submit" class="lm-button" aria-label="{{ __('Search') }}"><i class="fa-light fa-arrow-right"></i></button>
                        </form>
                    </div>

                    @include(theme_view('partials.hero-squares', 'guest'), [
                        'heroCoreIcon' => 'fa-comments-question-check',
                        'heroCoreLabel' => __('Help center'),
                        'heroCoreTitle' => __('Answers in context.'),
                        'heroCoreStatus' => __('Search and explore'),
                        'heroTiles' => [
                            ['icon' => 'fa-credit-card', 'label' => __('Plans'), 'title' => __('Compare access')],
                            ['icon' => 'fa-calendar-check', 'label' => __('Publishing'), 'title' => __('Plan and queue')],
                            ['icon' => 'fa-sparkles', 'label' => __('AI tools'), 'title' => __('Usage details')],
                            ['icon' => 'fa-users', 'label' => __('Workspace'), 'title' => __('Teams and access')],
                            ['icon' => 'fa-share-nodes', 'label' => __('Channels'), 'title' => __('Connected profiles')],
                            ['icon' => 'fa-chart-line', 'label' => __('Analytics'), 'title' => __('Available reports')],
                        ],
                    ])
                </div>

                <div class="lm-faq-summary lm-page-reveal">
                    @foreach ([
                        ['fa-calendar-check', __('Publishing'), __('Planning, queues and supported channels')],
                        ['fa-sparkles', __('AI tools'), __('Usage and plan availability')],
                        ['fa-users', __('Workspace'), __('Teams, access and billing')],
                    ] as $item)
                        <article class="lm-card">
                            <span class="lm-icon-tile"><i class="fa-light {{ $item[0] }}"></i></span>
                            <div><strong>{{ $item[1] }}</strong><span>{{ $item[2] }}</span></div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="lm-section">
            <div class="lm-page-shell lm-faq-layout">
                <aside class="lm-card lm-faq-aside lm-page-reveal lm-page-reveal-left">
                    <span class="lm-icon-tile"><i class="fa-light fa-comments-question-check"></i></span>
                    <h2>{{ __('Everything essential, in one place.') }}</h2>
                    <p>{{ __('Open a question to review its answer. Plan details and provider requirements can vary, so compare the current pricing page when access limits matter.') }}</p>
                    <a href="{{ route('guest.pricing') }}" class="lm-button-secondary" style="margin-top:22px;width:100%;">{{ __('Compare plans') }}<i class="fa-light fa-arrow-right"></i></a>
                    <a href="{{ route('guest.contact') }}" class="lm-button-secondary" style="margin-top:9px;width:100%;">{{ __('Contact us') }}</a>
                </aside>

                <div x-data="{ openFaq: {{ $faqs->isNotEmpty() ? 0 : -1 }} }" class="lm-faq-list">
                    @forelse ($faqs as $faq)
                        <article class="lm-card lm-faq-item lm-page-reveal" style="--lm-delay: {{ min($loop->index * 55, 330) }}ms;">
                            <button type="button" class="lm-faq-question" x-on:click="openFaq = openFaq === {{ $loop->index }} ? -1 : {{ $loop->index }}" x-bind:aria-expanded="openFaq === {{ $loop->index }}" aria-controls="faq-answer-{{ $faq->id }}">
                                <div>
                                    <span class="lm-faq-number">{{ str_starts_with($faq->slug, 'demo-preview-') ? __('DEMO WALKTHROUGH') : __('QUESTION') }} {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                    <h2>{{ $faq->titleForLocale() }}</h2>
                                </div>
                                <span class="lm-faq-toggle" x-bind:class="openFaq === {{ $loop->index }} ? 'is-open' : ''"><i class="fa-light fa-plus"></i></span>
                            </button>
                            <div id="faq-answer-{{ $faq->id }}" class="lm-faq-answer-grid" x-bind:aria-hidden="openFaq !== {{ $loop->index }}" x-bind:style="openFaq === {{ $loop->index }} ? 'grid-template-rows:1fr;opacity:1;' : 'grid-template-rows:0fr;opacity:0;'">
                                <div class="lm-faq-answer"><div class="lm-faq-answer-inner">{{ trim(strip_tags($faq->contentForLocale())) }}</div></div>
                            </div>
                        </article>
                    @empty
                        <div class="lm-empty lm-page-reveal"><i class="fa-light fa-circle-question" style="font-size:24px;"></i><p style="margin:14px 0 0;">{{ __('No FAQs found for this search.') }}</p></div>
                    @endforelse

                    @if ($faqs->hasPages())
                        <div class="lm-pagination">{{ $faqs->links() }}</div>
                    @endif
                </div>
            </div>
        </section>
    </div>
@endcomponent
