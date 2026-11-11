@php
    $options = app(\Modules\AdminSettings\Support\OptionStore::class);
    $companyWebsite = trim((string) $options->get('contact_company_website', ''));
    $contactEmail = trim((string) $options->get('contact_email', ''));
    $contactPhone = trim((string) $options->get('contact_phone_number', ''));
    $workingHours = trim((string) $options->get('contact_working_hours', ''));
    $contactLocation = trim((string) $options->get('contact_location', ''));
    $companyWebsite = in_array(strtolower($companyWebsite), ['https://yourcompany.com', 'http://yourcompany.com'], true) ? '' : $companyWebsite;
    $contactEmail = str_ends_with(strtolower($contactEmail), '@yourcompany.com') ? '' : $contactEmail;
    $contactPhone = $contactPhone === '+1 234 567 890' ? '' : $contactPhone;
    $contactLocation = $contactLocation === '123 Main Street, City, Country' ? '' : $contactLocation;
    $hasContactChannel = $contactEmail !== '' || $contactPhone !== '' || $companyWebsite !== '';
@endphp

@component(theme_view('layouts.marketing', 'guest'), ['pageTitle' => $pageTitle])
    @push('styles')
        @include(theme_view('partials.stylesheet', 'guest'), ['stylesheet' => 'contact'])
    @endpush

    <div class="lm-subpage lm-contact-page">
        <section class="lm-page-hero lm-square-hero">
            <div class="lm-page-shell lm-square-hero-layout">
                <div class="lm-square-hero-copy lm-contact-hero-copy lm-page-reveal lm-page-reveal-left">
                    <span class="lm-page-eyebrow"><i class="fa-light fa-message-lines"></i>{{ __('Contact') }}</span>
                    <h1 class="lm-page-title">{{ __('Let’s find the right next step.') }}</h1>
                    <p class="lm-page-copy">{{ $hasContactChannel ? __('Questions about your account, publishing or plans? Choose a contact method below.') : __('Find answers about your account, publishing and plans in our help center.') }}</p>
                    <div class="lm-page-actions">
                        @if($contactEmail !== '')
                        <a href="mailto:{{ $contactEmail }}" class="lm-button"><i class="fa-light fa-envelope"></i>{{ __('Email support') }}</a>
                        @elseif($contactPhone !== '')
                        <a href="tel:{{ preg_replace('/\s+/', '', $contactPhone) }}" class="lm-button"><i class="fa-light fa-phone"></i>{{ __('Call support') }}</a>
                        @elseif($companyWebsite !== '')
                        <a href="{{ $companyWebsite }}" class="lm-button">{{ __('Visit company website') }}</a>
                        @else
                        <a href="{{ route('guest.faqs') }}" class="lm-button">{{ __('Browse FAQs') }}</a>
                        @endif
                        @if($hasContactChannel)<a href="{{ route('guest.faqs') }}" class="lm-button-secondary">{{ __('Browse FAQs') }}<i class="fa-light fa-arrow-right"></i></a>@endif
                    </div>
                </div>

                @include(theme_view('partials.hero-squares', 'guest'), [
                    'heroCoreIcon' => 'fa-headset',
                    'heroCoreLabel' => __('Contact us'),
                    'heroCoreTitle' => __('Find helpful answers.'),
                    'heroCoreStatus' => __('Explore the help center'),
                    'heroTiles' => [
                        ['icon' => 'fa-calendar-days', 'label' => __('Publishing'), 'title' => __('Plan your content')],
                        ['icon' => 'fa-sparkles', 'label' => __('AI tools'), 'title' => __('Explore usage')],
                        ['icon' => 'fa-credit-card', 'label' => __('Plans'), 'title' => __('Review allowances')],
                        ['icon' => 'fa-users', 'label' => __('Workspace'), 'title' => __('Account guidance')],
                        ['icon' => 'fa-circle-question', 'label' => __('FAQs'), 'title' => __('Review answers')],
                        ['icon' => 'fa-chart-line', 'label' => __('Analytics'), 'title' => __('Understand reports')],
                    ],
                ])
            </div>
        </section>

        <section class="lm-section lm-section-soft">
            <div class="lm-page-shell lm-contact-details">
                <div class="lm-contact-details-intro lm-page-reveal lm-page-reveal-left">
                    <p class="lm-section-kicker">{{ __('Contact details') }}</p>
                    <h2>{{ $hasContactChannel ? __('Get in touch.') : __('Available information.') }}</h2>
                    <p>{{ $hasContactChannel ? __('Use the contact details below for questions about your account, plans, or getting started.') : __('Direct contact details have not been published. You can find existing guidance in our FAQs.') }}</p>
                </div>
                <div class="lm-card lm-contact-list lm-page-reveal lm-page-reveal-right">
                    @foreach ([
                        ['fa-envelope', __('Email'), $contactEmail, 'mailto:'.$contactEmail],
                        ['fa-phone', __('Phone'), $contactPhone, 'tel:'.preg_replace('/\s+/', '', $contactPhone)],
                        ['fa-globe', __('Website'), $companyWebsite, $companyWebsite],
                        ['fa-clock', __('Working hours'), $workingHours, null],
                        ['fa-location-dot', __('Address'), $contactLocation, null],
                    ] as $item)
                        @continue($item[2] === '')
                        @if ($item[3])<a href="{{ $item[3] }}" class="lm-contact-row">@else<div class="lm-contact-row">@endif
                            <span class="lm-icon-tile"><i class="fa-light {{ $item[0] }}"></i></span>
                            <span><span class="lm-contact-row-label">{{ $item[1] }}</span><span class="lm-contact-row-value">{{ $item[2] }}</span></span>
                            @if ($item[3])<i class="fa-light fa-arrow-up-right" style="margin-left:auto;color:var(--lm-muted);"></i>@endif
                        @if ($item[3])</a>@else</div>@endif
                    @endforeach

                </div>
            </div>
        </section>

        <section class="lm-section">
            <div class="lm-page-shell">
                <div class="lm-section-heading lm-page-reveal">
                    <div><p class="lm-section-kicker">{{ __('How we can help') }}</p><h2 class="lm-section-title">{{ __('Choose the conversation that matches your goal.') }}</h2></div>
                    <p class="lm-section-copy">{{ __('Find help with publishing, AI tools, billing, or getting your team started.') }}</p>
                </div>
                <div class="lm-contact-topics">
                    @foreach ([
                        ['fa-calendar-lines', __('Publishing workflow'), __('Connected profiles, calendars, queues, post formats and publishing status.')],
                        ['fa-sparkles', __('AI and analytics'), __('Available AI tools, credits, reports and provider-dependent coverage.')],
                        ['fa-credit-card', __('Plans and workspace'), __('Plan limits, billing questions, team access and account setup.')],
                    ] as $item)
                        @continue($item[2] === '')
                        <article class="lm-card lm-card-hover lm-contact-topic lm-page-reveal" style="--lm-delay:{{ $loop->index * 70 }}ms;">
                            <span class="lm-icon-tile"><i class="fa-light {{ $item[0] }}"></i></span><h3>{{ $item[1] }}</h3><p>{{ $item[2] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    </div>
@endcomponent
