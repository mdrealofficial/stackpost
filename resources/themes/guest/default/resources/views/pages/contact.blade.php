@php
    $options = app(\Modules\AdminSettings\Support\OptionStore::class);
    $companyName = trim((string) $options->get('contact_company_name', config('app.name', 'Stackposts')));
    $companyWebsite = trim((string) $options->get('contact_company_website', 'https://yourcompany.com'));
    $contactEmail = trim((string) $options->get('contact_email', 'support@yourcompany.com'));
    $contactPhone = trim((string) $options->get('contact_phone_number', '+1 234 567 890'));
    $workingHours = trim((string) $options->get('contact_working_hours', 'Mon - Fri: 09:00 AM - 06:00 PM'));
    $contactLocation = trim((string) $options->get('contact_location', '123 Main Street, City, Country'));
    $contactCards = [
        [
            'label' => __('Website'),
            'value' => $companyWebsite !== '' ? $companyWebsite : __('Not configured yet'),
            'href' => $companyWebsite !== '' ? $companyWebsite : '#',
            'tag' => 'a',
            'valueClass' => 'break-all text-[1.02rem] font-medium leading-7 text-white/88',
        ],
        [
            'label' => __('Email'),
            'value' => $contactEmail,
            'href' => 'mailto:' . $contactEmail,
            'tag' => 'a',
            'valueClass' => 'break-all text-[1.02rem] font-medium leading-7 text-white/88',
        ],
        [
            'label' => __('Phone'),
            'value' => $contactPhone,
            'href' => 'tel:' . preg_replace('/\s+/', '', $contactPhone),
            'tag' => 'a',
            'valueClass' => 'text-[1.42rem] font-semibold tracking-[-0.02em] text-white',
        ],
        [
            'label' => __('Working hours'),
            'value' => $workingHours,
            'tag' => 'div',
            'valueClass' => 'text-[1.05rem] font-semibold leading-8 tracking-[-0.02em] text-white',
        ],
        [
            'label' => __('Address'),
            'value' => $contactLocation,
            'tag' => 'div',
            'span' => 'md:col-span-2',
            'valueClass' => 'text-[1.06rem] font-medium leading-8 text-white/90',
        ],
    ];

@endphp

@component(theme_view('layouts.marketing', 'guest'), ['pageTitle' => $pageTitle])
    <section class="guest-marketing-shell guest-section-space mx-auto px-6 pt-2 lg:px-12 lg:pt-4">
        <div class="mx-auto max-w-4xl text-center">
            <span class="inline-flex items-center rounded-full border border-emerald-400/14 bg-emerald-400/10 px-4 py-2 text-xs font-semibold uppercase tracking-[0.22em] text-emerald-100/90">
                {{ __('Contact us') }}
            </span>
            <h1 class="mx-auto mt-6 max-w-4xl text-4xl font-semibold tracking-[-0.06em] text-white md:text-6xl">
                {{ __('Talk to the team behind your publishing workflow') }}
            </h1>
            <p class="mx-auto mt-5 max-w-3xl text-base leading-8 text-white/58 md:text-lg">
                {{ __('Reach out for onboarding help, pricing questions, infrastructure guidance, or support with a production-ready setup.') }}
            </p>
        </div>

        <div class="mx-auto mt-14 grid max-w-6xl gap-4 lg:grid-cols-3">
            @foreach ($contactCards as $item)
                @if (($item['tag'] ?? 'div') === 'a')
                    <a
                        href="{{ $item['href'] }}"
                        @if (($item['label'] ?? '') === __('Website') && $companyWebsite !== '')
                            target="_blank" rel="noopener noreferrer"
                        @endif
                        class="nova-soft-surface rounded-[1.5rem] border border-white/8 px-5 py-5 transition hover:-translate-y-0.5 {{ $item['span'] ?? '' }}"
                    >
                        <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-emerald-100/70">{{ $item['label'] }}</p>
                        <p class="mt-4 {{ $item['valueClass'] }}">{{ $item['value'] }}</p>
                    </a>
                @else
                    <div class="nova-soft-surface rounded-[1.5rem] border border-white/8 px-5 py-5 {{ $item['span'] ?? '' }}">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-emerald-100/70">{{ $item['label'] }}</p>
                        <p class="mt-4 {{ $item['valueClass'] }}">{{ $item['value'] }}</p>
                    </div>
                @endif
            @endforeach

            <div class="rounded-[1.9rem] border border-white/8 px-6 py-6 lg:row-span-2" style="background: linear-gradient(180deg, rgba(255,255,255,0.06) 0%, rgba(255,255,255,0.02) 100%);">
                <span class="inline-flex items-center rounded-full border border-white/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] text-white/70">{{ __('Why contact us') }}</span>
                <h2 class="mt-4 text-[2rem] font-semibold leading-tight tracking-[-0.04em] text-white">{{ __('Get practical guidance, not generic support replies.') }}</h2>
                <p class="mt-3 text-sm leading-7 text-white/56">{{ __('We help teams deploy publishing operations with the right structure from day one.') }}</p>
            </div>

            <div class="nova-soft-surface rounded-[1.5rem] border border-white/8 px-5 py-5">
                <p class="text-lg font-semibold text-white">{{ __('Onboarding and setup') }}</p>
                <p class="mt-2 text-sm leading-7 text-white/56">{{ __('Need help configuring workspaces, queues, automations, or connected accounts?') }}</p>
            </div>

            <div class="nova-soft-surface rounded-[1.5rem] border border-white/8 px-5 py-5">
                <p class="text-lg font-semibold text-white">{{ __('Pricing and scale') }}</p>
                <p class="mt-2 text-sm leading-7 text-white/56">{{ __('We can help map the right plan to your publishing volume and team structure.') }}</p>
            </div>

            <div class="nova-soft-surface rounded-[1.6rem] border border-white/8 px-5 py-5 lg:col-span-2">
                <p class="text-lg font-semibold text-white">{{ __('Operational workflows') }}</p>
                <p class="mt-2 text-sm leading-7 text-white/56">{{ __('Talk through approval flows, support handoff, campaign management, and media operations.') }}</p>

                <div class="mt-5 rounded-[1.2rem] border border-white/8 bg-white/[0.03] px-4 py-4">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-emerald-100/70">{{ __('Preferred channel') }}</p>
                    <p class="mt-3 text-lg font-semibold text-white">{{ __('Email first, phone when urgent.') }}</p>
                    <p class="mt-2 text-sm leading-7 text-white/56">{{ __('That keeps requests structured, searchable, and easier for the team to route accurately.') }}</p>
                </div>
            </div>
        </div>
    </section>
@endcomponent
