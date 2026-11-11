@component(theme_view('layouts.marketing', 'guest'), ['pageTitle' => $pageTitle])
    @php
        $hasHtml = str_contains($pageContent, '<') && str_contains($pageContent, '>');
        $pageType = $pageType ?? 'html';
        $socialLinks = $socialLinks ?? [];
        $isPrivacy = $routeName === 'guest.privacy-policy';
        $isTerms = $routeName === 'guest.terms-of-use';
        $eyebrow = $pageType === 'social' ? __('Social directory') : ($isPrivacy ? __('Privacy') : ($isTerms ? __('Legal') : __('Information')));
        $intro = $pageType === 'social'
            ? __('Official public channels configured for this website.')
            : __('Public information maintained by the website operator.');
        $pageTabs = [
            ['label' => __('Privacy Policy'), 'route' => 'guest.privacy-policy', 'icon' => 'fa-shield-check'],
            ['label' => __('Terms of Use'), 'route' => 'guest.terms-of-use', 'icon' => 'fa-file-contract'],
            ['label' => __('Social Pages'), 'route' => 'guest.social-pages', 'icon' => 'fa-share-nodes'],
        ];
    @endphp

    @push('styles')
        @include(theme_view('partials.stylesheet', 'guest'), ['stylesheet' => 'document'])
    @endpush

    <div class="lm-subpage lm-document-page">
        <section class="lm-page-hero lm-square-hero">
            <div class="lm-page-shell lm-square-hero-layout">
                <div class="lm-square-hero-copy lm-page-reveal lm-page-reveal-left">
                    <span class="lm-page-eyebrow"><i class="fa-light {{ $pageType === 'social' ? 'fa-share-nodes' : 'fa-file-lines' }}"></i>{{ $eyebrow }}</span>
                    <h1 class="lm-page-title">{{ $pageTitle }}</h1>
                    <p class="lm-page-copy">{{ $intro }}</p>
                </div>

                @include(theme_view('partials.hero-squares', 'guest'), [
                    'heroCoreIcon' => $pageType === 'social' ? 'fa-share-nodes' : ($isPrivacy ? 'fa-shield-check' : ($isTerms ? 'fa-file-contract' : 'fa-file-lines')),
                    'heroCoreLabel' => $eyebrow,
                    'heroCoreTitle' => $pageType === 'social' ? __('Official channels.') : __('Clear public information.'),
                    'heroCoreStatus' => __('Maintained by the website'),
                    'heroTiles' => [
                        ['icon' => 'fa-file-lines', 'label' => __('Information'), 'title' => __('Easy to review')],
                        ['icon' => 'fa-shield-check', 'label' => __('Privacy'), 'title' => __('Data practices')],
                        ['icon' => 'fa-file-contract', 'label' => __('Terms'), 'title' => __('Service rules')],
                        ['icon' => 'fa-share-nodes', 'label' => __('Social'), 'title' => __('Public channels')],
                        ['icon' => 'fa-globe', 'label' => __('Website'), 'title' => __('Official source')],
                        ['icon' => 'fa-circle-check', 'label' => __('Status'), 'title' => __('Published details')],
                    ],
                ])
            </div>
        </section>

        <section class="lm-section">
            <div class="lm-page-shell lm-document-layout">
                <nav class="lm-card lm-document-nav lm-page-reveal lm-page-reveal-left" aria-label="{{ __('Public information pages') }}">
                    <p class="lm-document-nav-label">{{ __('Browse pages') }}</p>
                    @foreach($pageTabs as $tab)
                        <a href="{{ route($tab['route']) }}" class="lm-document-tab {{ $routeName === $tab['route'] ? 'is-active' : '' }}"><i class="fa-light {{ $tab['icon'] }}"></i>{{ $tab['label'] }}</a>
                    @endforeach
                </nav>

                <article class="lm-card lm-document-card lm-page-reveal lm-page-reveal-right">
                    <div class="lm-document-toolbar">
                        <div class="lm-document-toolbar-left"><div class="lm-document-dots"><span></span><span></span><span></span></div><span class="lm-meta">{{ __('Public page') }}</span></div>
                        <span class="lm-chip"><i class="fa-light fa-check-circle"></i>{{ __('Website information') }}</span>
                    </div>
                    <div class="lm-document-body">
                        @if ($pageType === 'social')
                            @if (trim($pageContent) !== '')
                                <div class="lm-prose" style="max-width:760px;white-space:pre-line;">{{ $pageContent }}</div>
                            @endif
                            @if ($socialLinks !== [])
                                <div class="lm-social-grid">
                                    @foreach ($socialLinks as $link)
                                        <a href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer" class="lm-card lm-card-hover lm-social-card">
                                            <span class="lm-icon-tile"><i class="{{ $link['icon'] }}"></i></span>
                                            <span style="min-width:0;flex:1;"><strong>{{ $link['label'] }}</strong><small>{{ $link['url'] }}</small></span>
                                            <i class="fa-light fa-arrow-up-right" style="color:var(--lm-muted);"></i>
                                        </a>
                                    @endforeach
                                </div>
                            @else
                                <div class="lm-empty">{{ __('No social links have been configured yet.') }}</div>
                            @endif
                        @elseif (trim($pageContent) === '')
                            <div class="lm-empty"><i class="fa-light fa-file-circle-info" style="font-size:24px;"></i><p style="margin:14px 0 0;">{{ __('This page has not been configured yet.') }}</p></div>
                        @elseif ($hasHtml)
                            <div class="lm-prose guest-static-content prose max-w-none">{!! $pageContent !!}</div>
                        @else
                            <div class="lm-prose guest-static-content" style="white-space:pre-line;">{{ $pageContent }}</div>
                        @endif
                    </div>
                </article>
            </div>
        </section>
    </div>
@endcomponent
