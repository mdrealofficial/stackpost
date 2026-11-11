@extends(theme_view('layouts.marketing', 'guest'))

@php
    $signupEnabled = auth_signup_enabled();
    $primaryHref = auth()->check() ? route('portal.dashboard') : ($signupEnabled ? route('register') : route('login'));
    $primaryLabel = auth()->check() ? __('Open dashboard') : ($signupEnabled ? __('Get started') : __('Open workspace'));

    $channels = [
        ['fa-brands fa-facebook-f', 'Facebook', '#1877f2', 0],
        ['fa-brands fa-instagram', 'Instagram', '#e1306c', 45],
        ['fa-brands fa-tiktok', 'TikTok', '#111111', 90],
        ['fa-brands fa-youtube', 'YouTube', '#ff0033', 135],
        ['fa-brands fa-linkedin-in', 'LinkedIn', '#0a66c2', 180],
        ['fa-brands fa-pinterest-p', 'Pinterest', '#e60023', 225],
        ['fa-brands fa-x-twitter', 'X', '#111111', 270],
        ['fa-brands fa-threads', 'Threads', '#111111', 315],
    ];

    $features = [
        ['fa-calendar-days', __('Visual publishing calendar'), __('See drafts and scheduled posts in one clear calendar, organized around the way your team actually works.'), 'coral'],
        ['fa-wand-magic-sparkles', __('AI content tools'), __('Turn an idea into captions, hooks, hashtags, campaign drafts, images, and reusable content variations.'), 'violet'],
        ['fa-paper-plane-top', __('Multi-channel publishing'), __('Prepare channel-ready posts and publish through connected social profiles without rebuilding the same campaign.'), 'blue'],
        ['fa-chart-line-up', __('Social analytics'), __('Bring supported channel performance into one reporting surface for faster content decisions.'), 'green'],
        ['fa-users', __('Team workspaces'), __('Keep planning, review conversations, and publishing responsibilities connected to the right workspace.'), 'orange'],
        ['fa-photo-film-music', __('Media library'), __('Organize the images, videos, and creative assets your publishing workflow depends on.'), 'cyan'],
    ];

    $steps = [
        ['01', 'fa-link', __('Connect profiles'), __('Add the supported social profiles your workspace is configured to use.')],
        ['02', 'fa-lightbulb', __('Build the idea'), __('Draft the campaign yourself or use the available AI content tools for a faster start.')],
        ['03', 'fa-calendar-check', __('Review and schedule'), __('Preview each post, choose its destination, and place it on the publishing calendar.')],
        ['04', 'fa-chart-simple', __('Publish and learn'), __('Follow publishing status and use supported analytics to improve what comes next.')],
    ];

    $useCases = [
        ['fa-buildings', __('Agencies'), __('Separate client workspaces, content calendars, connected profiles, and team responsibilities.')],
        ['fa-bullhorn', __('Brands'), __('Keep campaigns, media, publishing, and performance reporting in one operating rhythm.')],
        ['fa-camera-retro', __('Creators'), __('Move from content idea to a consistent multi-channel schedule with fewer repetitive steps.')],
    ];
@endphp

@push('styles')
@include(theme_view('partials.stylesheet', 'guest'), ['stylesheet' => 'home'])
@endpush

@section('content')
<div class="lm-home">
    <section class="lm-home-hero">
        <div class="lm-home-shell">
            <div class="lm-hero-intro">
                <span class="lm-home-eyebrow"><i class="fa-light fa-sparkles"></i>{{ __('Social publishing, without the busywork') }}</span>
                <h1 class="lm-home-title">{{ __('The All-in-One Social Media Workspace') }}</h1>
                <p class="lm-hero-kicker">
                    <span class="lm-accent-underline">{{ __('Plan') }}</span> ·
                    <span class="lm-accent-underline">{{ __('Create') }}</span> ·
                    <span class="lm-accent-underline">{{ __('Publish') }}</span> ·
                    <span class="lm-accent-underline">{{ __('Analyze') }}</span>
                    — {{ __('across every connected channel') }}
                </p>
                <div class="lm-hero-actions">
                    <a href="{{ $primaryHref }}" class="lm-home-button"><i class="fa-light fa-arrow-up-right"></i>{{ $primaryLabel }}</a>
                    <a href="#features" class="lm-home-button is-secondary">{{ __('Explore features') }}<i class="fa-light fa-arrow-down"></i></a>
                </div>
                <div class="lm-hero-note">
                    <span><i class="fa-light fa-circle-check"></i>{{ __('One connected publishing workflow') }}</span>
                    <span><i class="fa-light fa-circle-check"></i>{{ __('Plan details define available tools') }}</span>
                </div>
            </div>
        </div>

        <div class="lm-home-square-scene" aria-hidden="true">
            <span class="lm-home-float-square is-plan"><i class="fa-light fa-calendar-lines"></i><span>{{ __('Plan') }}</span></span>
            <span class="lm-home-float-square is-ai"><i class="fa-light fa-wand-magic-sparkles"></i><span>{{ __('AI content') }}</span></span>
            <span class="lm-home-float-square is-channels"><i class="fa-light fa-share-nodes"></i><span>{{ __('Channels') }}</span></span>
            <span class="lm-home-float-square is-publish"><i class="fa-light fa-paper-plane"></i><span>{{ __('Publish') }}</span></span>
            <span class="lm-home-float-square is-media"><i class="fa-light fa-photo-film"></i><span>{{ __('Media') }}</span></span>
            <span class="lm-home-float-square is-analyze"><i class="fa-light fa-chart-line"></i><span>{{ __('Analyze') }}</span></span>
        </div>

        <div class="lm-product-window" x-data="{ screen: 'calendar' }">
            <div class="lm-product-topbar">
                <div class="lm-window-meta">
                    <span class="lm-window-dots"><span class="lm-window-dot"></span><span class="lm-window-dot"></span><span class="lm-window-dot"></span></span>
                    <span class="lm-window-label">{{ __('Interactive demo · sample content') }}</span>
                </div>
                <div class="lm-preview-tabs" role="group" aria-label="{{ __('Product preview') }}">
                    <button type="button" class="lm-preview-tab" x-bind:class="screen === 'calendar' ? 'is-active' : ''" x-on:click="screen = 'calendar'" aria-label="{{ __('Calendar') }}" x-bind:aria-pressed="screen === 'calendar'"><i class="fa-light fa-calendar-days"></i><span>{{ __('Calendar') }}</span></button>
                    <button type="button" class="lm-preview-tab" x-bind:class="screen === 'composer' ? 'is-active' : ''" x-on:click="screen = 'composer'" aria-label="{{ __('Composer') }}" x-bind:aria-pressed="screen === 'composer'"><i class="fa-light fa-pen-line"></i><span>{{ __('Composer') }}</span></button>
                    <button type="button" class="lm-preview-tab" x-bind:class="screen === 'analytics' ? 'is-active' : ''" x-on:click="screen = 'analytics'" aria-label="{{ __('Analytics') }}" x-bind:aria-pressed="screen === 'analytics'"><i class="fa-light fa-chart-line"></i><span>{{ __('Analytics') }}</span></button>
                </div>
                <div class="lm-preview-actions"><span class="lm-preview-action"><i class="fa-light fa-bell"></i></span><span class="lm-preview-action"><i class="fa-light fa-gear"></i></span></div>
            </div>

            <div class="lm-product-body">
                <aside class="lm-product-sidebar" aria-label="{{ __('Preview navigation') }}">
                    <div class="lm-product-workspace"><span class="lm-product-workspace-icon"><i class="fa-light fa-layer-group"></i></span>{{ __('Social workspace') }}</div>
                    <div class="lm-product-nav">
                        <span class="lm-product-nav-item"><i class="fa-light fa-grid-2"></i>{{ __('Overview') }}</span>
                        <span class="lm-product-nav-item is-active"><i class="fa-light fa-calendar-days"></i>{{ __('Publishing') }}</span>
                        <span class="lm-product-nav-item"><i class="fa-light fa-wand-magic-sparkles"></i>{{ __('AI content') }}</span>
                        <span class="lm-product-nav-item"><i class="fa-light fa-photo-film"></i>{{ __('Media') }}</span>
                        <span class="lm-product-nav-item"><i class="fa-light fa-link"></i>{{ __('Channels') }}</span>
                        <span class="lm-product-nav-item"><i class="fa-light fa-chart-simple"></i>{{ __('Analytics') }}</span>
                        <span class="lm-product-nav-item"><i class="fa-light fa-users"></i>{{ __('Team') }}</span>
                    </div>
                </aside>

                <div class="lm-product-main">
                    <div x-show="screen === 'calendar'" class="lm-preview-panel">
                        <div class="lm-preview-header">
                            <div><p class="lm-preview-overline">{{ __('Publishing calendar') }}</p><h2 class="lm-preview-title">{{ __('Campaign week') }}</h2></div>
                            <span class="lm-preview-button"><i class="fa-light fa-plus"></i><span>{{ __('Create post') }}</span></span>
                        </div>
                        <div class="lm-calendar">
                            <div class="lm-calendar-head">
                                @foreach ([__('Monday'), __('Tuesday'), __('Wednesday'), __('Thursday'), __('Friday')] as $day)
                                    <div class="lm-calendar-day">{{ $day }}</div>
                                @endforeach
                            </div>
                            <div class="lm-calendar-grid">
                                <div class="lm-calendar-cell"><span class="lm-calendar-date">01</span><div class="lm-post-card is-accent"><div class="lm-post-status"><span>{{ __('Scheduled') }}</span><i class="fa-brands fa-instagram"></i></div><p class="lm-post-title">{{ __('Launch teaser') }}</p><p class="lm-post-time">{{ __('Morning slot') }}</p></div></div>
                                <div class="lm-calendar-cell"><span class="lm-calendar-date">02</span><div class="lm-post-card"><div class="lm-post-status"><span>{{ __('Draft') }}</span><i class="fa-brands fa-linkedin-in"></i></div><p class="lm-post-title">{{ __('Founder insight') }}</p><p class="lm-post-time">{{ __('Needs review') }}</p></div></div>
                                <div class="lm-calendar-cell"><span class="lm-calendar-date">03</span><div class="lm-post-card is-blue"><div class="lm-post-status"><span>{{ __('Ready') }}</span><i class="fa-brands fa-facebook-f"></i></div><p class="lm-post-title">{{ __('Product story') }}</p><p class="lm-post-time">{{ __('Afternoon slot') }}</p></div></div>
                                <div class="lm-calendar-cell"><span class="lm-calendar-date">04</span><div class="lm-post-card"><div class="lm-post-status"><span>{{ __('Draft') }}</span><i class="fa-brands fa-tiktok"></i></div><p class="lm-post-title">{{ __('Behind the scenes') }}</p><p class="lm-post-time">{{ __('Video post') }}</p></div></div>
                                <div class="lm-calendar-cell"><span class="lm-calendar-date">05</span><div class="lm-post-card is-accent"><div class="lm-post-status"><span>{{ __('Scheduled') }}</span><i class="fa-brands fa-x-twitter"></i></div><p class="lm-post-title">{{ __('Community recap') }}</p><p class="lm-post-time">{{ __('Evening slot') }}</p></div></div>
                            </div>
                        </div>
                    </div>

                    <div x-cloak x-show="screen === 'composer'" class="lm-preview-panel">
                        <div class="lm-preview-header">
                            <div><p class="lm-preview-overline">{{ __('Post composer') }}</p><h2 class="lm-preview-title">{{ __('Create once, adapt by channel') }}</h2></div>
                            <span class="lm-preview-button"><i class="fa-light fa-calendar-check"></i><span>{{ __('Schedule') }}</span></span>
                        </div>
                        <div class="lm-composer-grid">
                            <div class="lm-ui-card">
                                <div class="lm-ui-card-head"><span>{{ __('Content') }}</span><span class="lm-home-eyebrow"><i class="fa-light fa-sparkles"></i>{{ __('AI assist') }}</span></div>
                                <div class="lm-ui-card-body">
                                    <div class="lm-compose-field">{{ __('Write your campaign update here, then tailor the final version for each connected social profile.') }}</div>
                                    <div class="lm-compose-tools"><span class="lm-compose-tool"><i class="fa-light fa-image"></i>{{ __('Media') }}</span><span class="lm-compose-tool"><i class="fa-light fa-hashtag"></i>{{ __('Hashtags') }}</span><span class="lm-compose-tool"><i class="fa-light fa-wand-magic-sparkles"></i>{{ __('Rewrite') }}</span><span class="lm-compose-tool"><i class="fa-light fa-language"></i>{{ __('Translate') }}</span></div>
                                </div>
                            </div>
                            <div class="lm-ui-card is-secondary-preview">
                                <div class="lm-ui-card-head"><span>{{ __('Channel preview') }}</span><i class="fa-brands fa-instagram"></i></div>
                                <div class="lm-ui-card-body"><div class="lm-preview-post"><div class="lm-preview-post-head"><span class="lm-avatar"><i class="fa-light fa-building"></i></span><span>{{ __('Your brand') }}</span></div><div class="lm-preview-media"><i class="fa-light fa-photo-film"></i></div><p class="lm-feature-copy">{{ __('Preview the post before it enters the schedule.') }}</p></div></div>
                            </div>
                        </div>
                    </div>

                    <div x-cloak x-show="screen === 'analytics'" class="lm-preview-panel">
                        <div class="lm-preview-header">
                            <div><p class="lm-preview-overline">{{ __('Analytics') }}</p><h2 class="lm-preview-title">{{ __('Content performance overview') }}</h2></div>
                            <span class="lm-preview-button"><i class="fa-light fa-file-export"></i><span>{{ __('Export') }}</span></span>
                        </div>
                        <div class="lm-analytics-grid">
                            <div class="lm-ui-card"><div class="lm-ui-card-head"><span>{{ __('Publishing trend') }}</span><span class="lm-module-note" style="margin:0">{{ __('Preview') }}</span></div><div class="lm-chart">@foreach ([38,58,46,72,64,86,70,92] as $bar)<span class="lm-chart-bar" style="height: {{ $bar }}%"></span>@endforeach</div></div>
                            <div class="lm-ui-card"><div class="lm-ui-card-head"><span>{{ __('Connected data') }}</span></div><div class="lm-ui-card-body lm-signal-list"><div class="lm-signal"><span class="lm-signal-name"><i class="fa-brands fa-facebook-f"></i>Facebook</span><span class="lm-signal-status">{{ __('Synced') }}</span></div><div class="lm-signal"><span class="lm-signal-name"><i class="fa-brands fa-instagram"></i>Instagram</span><span class="lm-signal-status">{{ __('Synced') }}</span></div><div class="lm-signal"><span class="lm-signal-name"><i class="fa-brands fa-linkedin-in"></i>LinkedIn</span><span class="lm-signal-status">{{ __('Synced') }}</span></div><div class="lm-signal"><span class="lm-signal-name"><i class="fa-brands fa-youtube"></i>YouTube</span><span class="lm-signal-status">{{ __('Synced') }}</span></div></div></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="lm-channel-strip">
        <div class="lm-home-shell lm-channel-strip-inner">
            <span class="lm-channel-strip-label">{{ __('Built around the channels you use') }}</span>
            @foreach ($channels as $channel)
                <span class="lm-channel-chip" style="--channel-color: {{ $channel[2] }}"><i class="{{ $channel[0] }}"></i>{{ $channel[1] }}</span>
            @endforeach
        </div>
    </div>

    <section id="features" class="lm-home-section">
        <div class="lm-home-shell">
            <div class="lm-section-intro">
                <span class="lm-home-eyebrow"><i class="fa-light fa-grid-2"></i>{{ __('Core workspace') }}</span>
                <h2 class="lm-home-heading">{{ __('Everything your team needs to move content forward') }}</h2>
                <p class="lm-home-copy">{{ __('Bring planning, creative work, publishing, supported analytics, and team coordination into a calmer operating system for social media.') }}</p>
            </div>
            <div class="lm-feature-grid">
                @foreach ($features as $feature)
                    <article class="lm-feature-card" data-tone="{{ $feature[3] }}">
                        <span class="lm-feature-icon"><i class="fa-light {{ $feature[0] }}"></i></span>
                        <h3 class="lm-feature-title">{{ $feature[1] }}</h3>
                        <p class="lm-feature-copy">{{ $feature[2] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="lm-home-section lm-hub-section">
        <div class="lm-home-shell lm-hub-grid">
            <div class="lm-orbit" aria-label="{{ __('Supported social channel families') }}">
                <div class="lm-orbit-ring"></div>
                <div class="lm-orbit-center"><div><strong>{{ __('One hub') }}</strong><span>{{ __('Connected social workflow') }}</span></div></div>
                @foreach ($channels as $channel)
                    <span class="lm-orbit-item" style="--angle: {{ $channel[3] }}deg; --channel-color: {{ $channel[2] }}" title="{{ $channel[1] }}"><i class="{{ $channel[0] }}"></i></span>
                @endforeach
            </div>

            <div class="lm-hub-copy">
                <span class="lm-home-eyebrow"><i class="fa-light fa-share-nodes"></i>{{ __('Connected channels') }}</span>
                <h2 class="lm-home-heading">{{ __('One workflow across every social channel') }}</h2>
                <p class="lm-home-copy">{{ __('Plan the campaign once, prepare the right version for each destination, and keep publishing work visible in the same workspace.') }}</p>
                <div class="lm-hub-points">
                    <div class="lm-hub-point"><span class="lm-hub-point-icon"><i class="fa-light fa-layer-group"></i></span><div><strong>{{ __('Channel-aware publishing') }}</strong><span>{{ __('Prepare content around the formats and requirements supported by each connected provider.') }}</span></div></div>
                    <div class="lm-hub-point"><span class="lm-hub-point-icon"><i class="fa-light fa-calendar-days"></i></span><div><strong>{{ __('One publishing calendar') }}</strong><span>{{ __('Keep drafts and scheduled content together instead of rebuilding the plan in separate tools.') }}</span></div></div>
                    <div class="lm-hub-point"><span class="lm-hub-point-icon"><i class="fa-light fa-chart-line"></i></span><div><strong>{{ __('Supported analytics in context') }}</strong><span>{{ __('Review available channel performance without losing the campaign and publishing context around it.') }}</span></div></div>
                </div>
                <span class="lm-module-note">{{ __('Channel availability, analytics coverage, and publishing capabilities can vary by plan and provider configuration.') }}</span>
            </div>
        </div>
    </section>

    <section id="workflow" class="lm-home-section">
        <div class="lm-home-shell">
            <div class="lm-section-intro">
                <span class="lm-home-eyebrow"><i class="fa-light fa-route"></i>{{ __('How it works') }}</span>
                <h2 class="lm-home-heading">{{ __('From content idea to published post in one clear flow') }}</h2>
                <p class="lm-home-copy">{{ __('A focused workflow keeps the team moving without hiding the channel-specific decisions that still matter.') }}</p>
            </div>
            <div class="lm-workflow-grid">
                @foreach ($steps as $step)
                    <article class="lm-step"><span class="lm-step-number">{{ $step[0] }}</span><span class="lm-step-icon"><i class="fa-light {{ $step[1] }}"></i></span><h3>{{ $step[2] }}</h3><p>{{ $step[3] }}</p></article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="lm-home-section is-compact">
        <div class="lm-home-shell">
            <div class="lm-section-intro">
                <span class="lm-home-eyebrow"><i class="fa-light fa-briefcase"></i>{{ __('Built for real social teams') }}</span>
                <h2 class="lm-home-heading">{{ __('A flexible workspace for different operating models') }}</h2>
            </div>
            <div class="lm-use-cases">
                @foreach ($useCases as $useCase)
                    <article class="lm-use-card"><div class="lm-use-card-head"><span class="lm-use-icon"><i class="fa-light {{ $useCase[0] }}"></i></span><h3>{{ $useCase[1] }}</h3></div><p>{{ $useCase[2] }}</p></article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="lm-home-band">
        <div class="lm-home-shell lm-home-band-card">
            <div><h2 class="lm-home-subheading">{{ __('Choose a plan around your team, channels, and publishing volume.') }}</h2><p class="lm-home-copy">{{ __('Compare the current plan limits and included features before you choose a workspace.') }}</p></div>
            <a href="{{ route('guest.pricing') }}" class="lm-home-button">{{ __('View pricing') }}<i class="fa-light fa-arrow-right"></i></a>
        </div>
    </section>

    @if (($latestBlogs ?? collect())->isNotEmpty())
        <section class="lm-home-section">
            <div class="lm-home-shell">
                <div class="lm-blog-head">
                    <div><span class="lm-home-eyebrow"><i class="fa-light fa-newspaper"></i>{{ __('From the blog') }}</span><h2 class="lm-home-heading">{{ __('Ideas for a sharper publishing workflow') }}</h2></div>
                    <a href="{{ route('guest.blogs') }}" class="lm-text-link">{{ __('View all articles') }}<i class="fa-light fa-arrow-right"></i></a>
                </div>
                <div class="lm-blog-grid">
                    @foreach ($latestBlogs as $blog)
                        <article class="lm-blog-card">
                            <a href="{{ route('guest.blog-show', $blog->slug) }}" class="lm-blog-media">
                                @if ($blog->thumbnailUrl())
                                    <img src="{{ $blog->thumbnailUrl() }}" alt="{{ $blog->titleForLocale() }}" onerror="this.style.display='none'">
                                @endif
                                <i class="fa-light fa-newspaper" aria-hidden="true"></i>
                            </a>
                            <div class="lm-blog-body">
                                <span class="lm-blog-meta">{{ $blog->category?->nameForLocale() ?? __('Product guide') }}</span>
                                <h3 class="lm-blog-title"><a href="{{ route('guest.blog-show', $blog->slug) }}">{{ $blog->titleForLocale() }}</a></h3>
                                <p class="lm-blog-excerpt">{{ $blog->contentPreview(126) }}</p>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if (($featuredFaqs ?? collect())->isNotEmpty())
        <section class="lm-home-section lm-hub-section">
            <div class="lm-home-shell lm-faq-grid" x-data="{ openFaq: {{ (int) (($featuredFaqs->first()->id ?? 0)) }} }">
                <div class="lm-faq-copy"><span class="lm-home-eyebrow"><i class="fa-light fa-circle-question"></i>{{ __('Frequently asked questions') }}</span><h2 class="lm-home-heading">{{ __('Clear answers before you start') }}</h2><p class="lm-home-copy">{{ __('Review the essentials, then compare plan details or contact the team for a workspace-specific question.') }}</p></div>
                <div class="lm-faq-list">
                    @foreach ($featuredFaqs as $faq)
                        <article class="lm-faq-item">
                            <button type="button" class="lm-faq-button" x-on:click="openFaq = openFaq === {{ (int) $faq->id }} ? 0 : {{ (int) $faq->id }}" x-bind:aria-expanded="openFaq === {{ (int) $faq->id }}" aria-controls="home-faq-answer-{{ (int) $faq->id }}"><span>{{ $faq->titleForLocale() }}</span><i class="fa-light fa-plus lm-faq-icon" x-bind:class="openFaq === {{ (int) $faq->id }} ? 'is-open' : ''"></i></button>
                            <div id="home-faq-answer-{{ (int) $faq->id }}" x-bind:aria-hidden="openFaq !== {{ (int) $faq->id }}" x-cloak class="lm-faq-answer-grid" x-bind:class="openFaq === {{ (int) $faq->id }} ? 'is-open' : ''"><div class="lm-faq-answer-shell"><div class="lm-faq-answer">{{ trim(strip_tags($faq->contentForLocale())) }}</div></div></div>
                        </article>
                    @endforeach
                    <a href="{{ route('guest.faqs') }}" class="lm-text-link" style="margin-top:12px">{{ __('Browse all questions') }}<i class="fa-light fa-arrow-right"></i></a>
                </div>
            </div>
        </section>
    @endif

    <section class="lm-home-section lm-final-cta">
        <div class="lm-home-shell lm-final-cta-inner">
            <span class="lm-home-eyebrow"><i class="fa-light fa-sparkles"></i>{{ __('Your next publishing workflow') }}</span>
            <h2 class="lm-home-heading">{{ __('Bring every social campaign into one calmer workspace.') }}</h2>
            <p class="lm-home-copy">{{ __('Plan the content, prepare channel-ready posts, publish through connected profiles, and learn from the analytics available to your workspace.') }}</p>
            <div class="lm-final-actions"><a href="{{ $primaryHref }}" class="lm-home-button">{{ $primaryLabel }}<i class="fa-light fa-arrow-up-right"></i></a><a href="{{ route('guest.contact') }}" class="lm-home-button is-secondary">{{ __('Contact us') }}</a></div>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
    (() => {
        const startLumaMotion = () => {
            const home = document.querySelector('.lm-home');
            if (! home || home.dataset.motionReady === '1') return;

            home.dataset.motionReady = '1';
            document.documentElement.classList.add('lm-motion-ready');

            const revealTargets = home.querySelectorAll([
                '.lm-section-intro',
                '.lm-feature-card',
                '.lm-hub-grid > *',
                '.lm-step',
                '.lm-use-card',
                '.lm-home-band-card > *',
                '.lm-blog-head > *',
                '.lm-blog-card',
                '.lm-faq-grid > *',
                '.lm-final-cta-inner'
            ].join(','));

            revealTargets.forEach((element, index) => {
                element.classList.add('lm-reveal');
                element.style.setProperty('--lm-reveal-delay', `${(index % 4) * 70}ms`);
            });

            home.querySelector('.lm-hub-grid > :first-child')?.classList.add('lm-reveal-from-left');
            home.querySelector('.lm-hub-grid > :last-child')?.classList.add('lm-reveal-from-right');

            if (! ('IntersectionObserver' in window)) {
                revealTargets.forEach(element => element.classList.add('is-visible'));
                return;
            }

            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (! entry.isIntersecting) return;
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                });
            }, { threshold: 0.12, rootMargin: '0px 0px -7% 0px' });

            revealTargets.forEach(element => observer.observe(element));
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', startLumaMotion, { once: true });
        } else {
            startLumaMotion();
        }

        document.addEventListener('livewire:navigated', startLumaMotion);
    })();
</script>
@endpush
