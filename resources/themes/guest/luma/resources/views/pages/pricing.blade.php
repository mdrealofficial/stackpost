@php
    $pricing = \Pricing::plansWithFeatures();
    $planTypes = collect(\Plan::getTypes())->filter(fn ($label, $key) => ! empty($pricing[$key] ?? []));
    $defaultType = (int) ($planTypes->keys()->first() ?? 1);
    $signupEnabled = auth_signup_enabled();
@endphp

@component(theme_view('layouts.marketing', 'guest'), ['pageTitle' => $pageTitle])
    @push('styles')
        @include(theme_view('partials.stylesheet', 'guest'), ['stylesheet' => 'pricing'])
    @endpush

    <div class="lm-subpage lm-pricing-page" x-data="{ type: {{ $defaultType }} }">
        <section class="lm-page-hero lm-pricing-hero">
            <div class="lm-page-shell lm-pricing-hero-layout">
                <div class="lm-pricing-hero-copy lm-page-reveal lm-page-reveal-left">
                    <span class="lm-page-eyebrow"><i class="fa-light fa-credit-card"></i>{{ __('Pricing') }}</span>
                        <h1 class="lm-page-title">{{ __('Find the right plan for your team.') }}</h1>
                    <p class="lm-page-copy">{{ __('Compare the current limits for channels, posts, AI usage, team access, analytics, and workspace features before you subscribe.') }}</p>
                    @if($planTypes->isNotEmpty())
                        <div class="lm-pricing-toggle" role="group" aria-label="{{ __('Billing periods') }}">
                            @foreach($planTypes as $typeKey => $typeLabel)
                                <button type="button"  x-on:click="type={{ $typeKey }}" x-bind:class="type === {{ $typeKey }} ? 'is-active' : ''" x-bind:aria-pressed="type === {{ $typeKey }}">{{ $typeLabel }}</button>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="lm-pricing-visual lm-page-reveal lm-page-reveal-right" aria-hidden="true">
                    <div class="lm-pricing-square is-plan"><i class="fa-light fa-calendar-lines"></i><div><span>{{ __('Plan') }}</span><strong>{{ __('Stay organized') }}</strong></div></div>
                    <div class="lm-pricing-square is-ghost-a"></div>
                    <div class="lm-pricing-square is-publish"><i class="fa-light fa-paper-plane"></i><div><span>{{ __('Publish') }}</span><strong>{{ __('Connected channels') }}</strong></div></div>
                    <div class="lm-pricing-square is-ai"><i class="fa-light fa-sparkles"></i><div><span>{{ __('AI tools') }}</span><strong>{{ __('Create faster') }}</strong></div></div>
                    <div class="lm-pricing-square is-team"><i class="fa-light fa-users"></i><div><span>{{ __('Team') }}</span><strong>{{ __('Work together') }}</strong></div></div>
                    <div class="lm-pricing-core">
                        <span class="lm-pricing-core-icon"><i class="fa-light fa-layer-group"></i></span>
                        <div><small>{{ __('One workspace') }}</small><strong>{{ __('Everything connected.') }}</strong></div>
                        <span class="lm-pricing-core-status">{{ __('Ready for your workflow') }}</span>
                    </div>
                    <div class="lm-pricing-square is-automate"><i class="fa-light fa-arrows-rotate"></i><div><span>{{ __('Automate') }}</span><strong>{{ __('Keep moving') }}</strong></div></div>
                    <div class="lm-pricing-square is-ghost-b"></div>
                    <div class="lm-pricing-square is-analyze"><i class="fa-light fa-chart-line"></i><div><span>{{ __('Analyze') }}</span><strong>{{ __('See results') }}</strong></div></div>
                </div>
            </div>
        </section>

        <section class="lm-section lm-pricing-plans">
            <div class="lm-page-shell">
                @if($planTypes->isEmpty())
                    <div class="lm-empty lm-page-reveal">{{ __('No public plans are available yet.') }}</div>
                @else
                    <div class="lm-pricing-section-head lm-page-reveal">
                        <div><p class="lm-section-kicker">{{ __('Plans at a glance') }}</p><h2>{{ __('Pick the workspace that fits today.') }}</h2></div>
                        <p class="lm-pricing-section-note">{{ __('Compare included features and allowances to choose a plan that fits your team.') }}</p>
                    </div>
                    <div class="lm-pricing-grid">
                        @foreach($planTypes as $typeKey => $typeLabel)
                            @foreach(collect($pricing[$typeKey] ?? []) as $plan)
                                @php
                                    $isFreePlan = (bool) ($plan['free_plan'] ?? false);
                                    $isFeatured = (bool) ($plan['featured'] ?? false);
                                    $planTarget = $plan['model']->slug ?? $plan['id'];
                                    $featureItems = collect($plan['features'] ?? []);
                                    $planType = (int) ($plan['type'] ?? 1);
                                    $price = $isFreePlan ? '0' : rtrim(rtrim(number_format((float) ($plan['price'] ?? 0), 2), '0'), '.');
                                @endphp
                                <article x-cloak x-show="type === {{ $typeKey }}" x-transition.opacity.duration.220ms class="lm-card lm-card-hover lm-plan-card lm-page-reveal {{ $isFeatured ? 'is-featured' : '' }}" style="--lm-delay:{{ min($loop->index * 70,280) }}ms;">
                                    <div class="lm-plan-head">
                                        <div class="lm-plan-topline"><span class="lm-meta">{{ __('Plan') }}</span>@if($isFeatured)<span class="lm-plan-badge"><i class="fa-light fa-sparkles"></i>{{ __('Featured') }}</span>@endif</div>
                                        <div class="lm-plan-identity"><span class="lm-plan-number">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><h2>{{ $plan['name'] ?? __('Plan') }}</h2></div>
                                        <p class="lm-plan-description">{{ $plan['desc'] ?: __('A workspace plan with the limits and features listed below.') }}</p>
                                        <div class="lm-plan-price"><strong>{{ $plan['currency_symbol'] ?: '$' }}{{ $price }}</strong>@if($planType !== 3)<span>/{{ $planType === 2 ? __('year') : __('month') }}</span>@endif</div>
                                        <p class="lm-plan-billing">{{ match($planType) { 2 => __('Billed yearly'), 3 => __('One-time payment'), default => __('Billed monthly') } }}</p>
                                        <a href="{{ $isFreePlan ? (auth()->check() ? route('portal.dashboard') : ($signupEnabled ? route('register') : route('login'))) : route('payment.index', $planTarget) }}" class="{{ $isFeatured ? 'lm-button' : 'lm-button-secondary' }}">{{ $isFreePlan ? __('Start for free') : __('Choose plan') }}<i class="fa-light fa-arrow-right"></i></a>
                                    </div>
                                    <div class="lm-plan-body">
                                        @include(theme_view('partials.plan-allowances', 'guest'), ['plan' => $plan])
                                        <div class="lm-plan-divider"><strong>{{ __('Included in this plan') }}</strong><p>{{ __('Explore the features and allowances included with this plan.') }}</p></div>
                                        <div class="lm-plan-features">
                                            @foreach($featureItems as $feature)
                                                @php
                                                    $featureData = is_array($feature) ? $feature : ['label' => $feature];
                                                    $featureLabel = (string) ($featureData['label'] ?? '');
                                                @endphp
                                                @continue(stripos($featureLabel, 'approval') !== false)
                                                @continue(in_array($featureData['key'] ?? '', ['max_channels', 'max_posts_per_month', 'credits_usage_limit', 'max_team_members', 'max_storage_size_mb'], true))
                                                <div class="lm-plan-feature {{ ($featureData['check'] ?? true) ? '' : 'is-unavailable' }}">
                                                    <span class="lm-plan-check"><i class="fa-light {{ ($featureData['check'] ?? true) ? 'fa-check' : 'fa-minus' }}"></i></span>
                                                    <span class="lm-plan-feature-copy">{{ __($featureLabel) }}</span>
                                                    @if(($featureData['display'] ?? null) !== null && ($featureData['display'] ?? '') !== '')<span class="lm-plan-feature-value">{{ is_string($featureData['display']) ? __($featureData['display']) : $featureData['display'] }}</span>@endif

                                                </div>
                                                @if(!empty($featureData['subfeature']))
                                                    <details class="lm-plan-details">
                                                        <summary>{{ __('View :feature details', ['feature' => __($featureLabel)]) }}<i class="fa-light fa-chevron-down" aria-hidden="true"></i></summary>
                                                        <div class="lm-plan-details-content">
                                                            @foreach($featureData['subfeature'] as $tabGroup)
                                                                <section class="lm-plan-detail-group">
                                                                    <h3>{{ __($tabGroup['tab_name']) }}</h3>
                                                                    @foreach($tabGroup['items'] as $sub)
                                                                        @continue(stripos((string)($sub['label'] ?? ''), 'approval') !== false)
                                                                        <div class="lm-plan-detail-row"><i class="fa-light {{ ($sub['check'] ?? true) ? 'fa-check' : 'fa-minus' }}" aria-hidden="true"></i><span>{{ __($sub['label']) }}</span>@if(($sub['display'] ?? '') !== '')<strong>{{ __((string) $sub['display']) }}</strong>@endif</div>
                                                                    @endforeach
                                                                </section>
                                                            @endforeach
                                                        </div>
                                                    </details>
                                                @endif
                                            @endforeach
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        <section class="lm-section lm-section-soft">
            <div class="lm-page-shell lm-plan-controls">
                <div class="lm-page-reveal lm-page-reveal-left"><p class="lm-section-kicker">{{ __('Plan controls') }}</p><h2>{{ __('Scale usage without changing your workflow.') }}</h2><p>{{ __('Compare channel capacity, publishing allowances, and AI credits in the plan cards above to find the right fit for your team.') }}</p></div>
                <div class="lm-card lm-plan-control-list lm-page-reveal lm-page-reveal-right">
                    @foreach([
                        ['fa-share-nodes',__('Connected profiles'),__('Channel and workspace capacity')],
                        ['fa-calendar-lines',__('Publishing volume'),__('Posts, queues and schedules')],
                        ['fa-sparkles',__('AI usage'),__('Credits and available AI tools')],
                        ['fa-users',__('Team access'),__('Seats and workspace permissions')],
                        ['fa-chart-line',__('Analytics'),__('Available reporting coverage')],
                    ] as $control)
                        <div class="lm-plan-control-row"><span class="lm-icon-tile"><i class="fa-light {{ $control[0] }}"></i></span><span><strong>{{ $control[1] }}</strong><small>{{ $control[2] }}</small></span></div>
                    @endforeach
                </div>
            </div>
        </section>

        @if($featuredFaqs->isNotEmpty())
            <section class="lm-section lm-pricing-faq-section">
                <div class="lm-page-shell lm-plan-faq-layout">
                    <aside class="lm-plan-faq-intro lm-page-reveal lm-page-reveal-left">
                        <p class="lm-section-kicker">{{ __('Before you choose') }}</p>
                        <h2>{{ __('Common plan questions.') }}</h2>
                        <p>{{ __('Review the essentials, then compare the live limits above or open the complete help center for workspace-specific details.') }}</p>
                        <div class="lm-plan-faq-tiles" aria-hidden="true">
                            <span class="lm-plan-faq-tile"><i class="fa-light fa-credit-card"></i>{{ __('Plans') }}</span>
                            <span class="lm-plan-faq-tile"><i class="fa-light fa-users"></i>{{ __('Access') }}</span>
                            <span class="lm-plan-faq-tile"><i class="fa-light fa-circle-question"></i>{{ __('Answers') }}</span>
                        </div>
                        <a href="{{ route('guest.faqs') }}" class="lm-plan-faq-link">{{ __('Browse all FAQs') }}<i class="fa-light fa-arrow-right"></i></a>
                    </aside>
                    <div class="lm-plan-faqs" x-data="{open:0}">
                        @foreach($featuredFaqs as $faq)
                            <article class="lm-card lm-plan-faq lm-page-reveal" style="--lm-delay:{{ $loop->index * 55 }}ms;">
                                <button type="button" x-on:click="open=open==={{ $loop->index }}?-1:{{ $loop->index }}" x-bind:aria-expanded="open==={{ $loop->index }}" aria-controls="pricing-faq-answer-{{ $faq->getKey() }}">
                                    <span><span class="lm-plan-faq-number">{{ __('Question') }} {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><h3>{{ $faq->titleForLocale() }}</h3></span>
                                    <span class="lm-plan-faq-toggle" x-bind:class="open==={{ $loop->index }}?'is-open':''"><i class="fa-light fa-plus"></i></span>
                                </button>
                                <div id="pricing-faq-answer-{{ $faq->getKey() }}" x-bind:aria-hidden="open!=={{ $loop->index }}" x-cloak class="lm-plan-faq-answer-grid" x-bind:class="open==={{ $loop->index }}?'is-open':''"><div class="lm-plan-faq-answer-shell"><p class="lm-plan-faq-answer">{{ trim(strip_tags($faq->contentForLocale())) }}</p></div></div>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif
    </div>
@endcomponent
