@php
    $pricing = \Pricing::plansWithFeatures();
    $planTypes = collect(\Plan::getTypes())->filter(fn ($label, $key) => ! empty($pricing[$key] ?? []));
    $defaultType = (int) ($planTypes->keys()->first() ?? 1);
    $signupEnabled = auth_signup_enabled();
@endphp

@component(theme_view('layouts.marketing', 'guest'), ['pageTitle' => $pageTitle])
    <section x-data="{ type: {{ $defaultType }} }" class="guest-marketing-shell guest-section-space mx-auto px-6 pb-[28rem] pt-6 lg:px-12 lg:pb-[22rem]">
        <div class="mx-auto max-w-4xl text-center">
            <span data-reveal class="inline-flex items-center rounded-full border border-emerald-400/14 bg-emerald-400/10 px-4 py-2 text-xs font-semibold uppercase tracking-[0.22em] text-emerald-100/90">
                <i class="fa-light fa-circle-check mr-2"></i>
                {{ __('Pricing built for StackPosts growth') }}
            </span>
            <h1 data-reveal class="nova-stagger-1 mx-auto mt-6 max-w-4xl text-4xl font-semibold tracking-[-0.06em] text-white md:text-6xl">
                {{ __('Pick the plan that matches your publishing volume and team size.') }}
            </h1>
            <p data-reveal class="nova-stagger-2 mx-auto mt-6 max-w-3xl text-base leading-8 text-white/56 md:text-lg">
                {{ __('Clean pricing, scalable automation, and enough workspace depth to run scheduling, content workflows, media operations, and reporting from one place.') }}
            </p>

            @if ($planTypes->isNotEmpty())
                <div data-reveal class="nova-stagger-3 mt-10 flex justify-center">
                    <div class="nova-soft-surface inline-flex items-center gap-1 rounded-full border border-white/10 bg-white/[0.03] p-1.5 shadow-[inset_0_1px_0_rgba(255,255,255,0.05)]">
                        @foreach ($planTypes as $typeKey => $typeLabel)
                            <button
                                type="button"
                                x-on:click="type = {{ $typeKey }}"
                                :class="type === {{ $typeKey }} ? 'bg-emerald-500 text-white shadow-[0_12px_24px_-18px_rgba(16,185,129,0.6)]' : 'text-white/58 hover:bg-white/[0.05] hover:text-white'"
                                class="rounded-full px-5 py-2.5 text-sm font-semibold transition"
                            >
                                {{ $typeLabel }}
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="mt-14 xl:relative xl:min-h-[84rem] 2xl:min-h-[74rem]">
            @foreach ($planTypes as $typeKey => $typeLabel)
                @php
                    $groupPlans = collect($pricing[$typeKey] ?? []);
                @endphp
                <div x-cloak x-show="type === {{ $typeKey }}" x-transition.opacity.duration.200ms class="xl:absolute xl:inset-0" style="display: none;">
                    <div class="grid gap-6 xl:grid-cols-3">
                        @foreach ($groupPlans as $plan)
                            <article data-reveal class="nova-tilt-hover flex h-full flex-col rounded-[2.3rem] border {{ $plan['featured'] ? 'nova-accent-surface--emerald border-emerald-400/30 bg-[linear-gradient(180deg,rgba(18,38,34,0.96)_0%,rgba(10,13,24,0.94)_100%)] shadow-[0_0_0_1px_rgba(16,185,129,0.08),0_40px_90px_-52px_rgba(16,185,129,0.45)]' : 'nova-card-surface border-white/8 bg-[linear-gradient(180deg,rgba(14,17,31,0.94)_0%,rgba(10,13,25,0.88)_100%)] shadow-[0_24px_60px_-42px_rgba(0,0,0,0.7)]' }} p-7 lg:p-8">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="inline-flex items-center rounded-full {{ $plan['featured'] ? 'bg-emerald-400/12 text-emerald-100' : 'bg-white/[0.05] text-white/72' }} px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em]">
                                                {{ $plan['name'] }}
                                            </span>
                                            @if (!($plan['free_plan'] ?? false) && (int) ($plan['trial_day'] ?? 0) > 0)
                                                <x-ui.badge variant="warning">{{ __(':days-day trial', ['days' => (int) $plan['trial_day']]) }}</x-ui.badge>
                                            @endif
                                        </div>
                                        <p class="mt-6 text-lg font-medium text-white/66">{{ $plan['desc'] ?: __('A practical StackPosts plan for scheduling, automation, workspace control, and content operations.') }}</p>
                                    </div>
                                    @if ($plan['featured'])
                                        <span class="inline-flex items-center rounded-full border border-emerald-300/14 bg-emerald-400/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-emerald-100">
                                            {{ __('Most popular') }}
                                        </span>
                                    @endif
                                </div>

                                <div class="mt-10">
                                    <p class="text-6xl font-semibold tracking-[-0.08em] text-white">
                                        {{ $plan['free_plan'] ? ($plan['currency_symbol'] ?: '$').'0' : ($plan['currency_symbol'] ?: '$').rtrim(rtrim(number_format((float) $plan['price'], 2), '0'), '.') }}
                                        <span class="text-xl font-semibold tracking-normal text-white/42">/{{ strtolower($typeLabel) }}</span>
                                    </p>
                                    <p class="mt-2 text-xs font-semibold uppercase tracking-[0.18em] text-white/38">{{ $plan['currency'] }} · {{ $plan['currency_name'] }}</p>
                                    <p class="mt-3 text-sm font-medium text-white/46">
                                        {{ match((int) $plan['type']){2 => __('Billed yearly'),3 => __('Pay once, use forever'),default => __('Billed monthly')} }}
                                    </p>
                                </div>

                                <div class="mt-8">
                                    @php
                                        $planTarget = $plan['model']->slug ?? $plan['id'];
                                    @endphp
                                    <a href="{{ $plan['free_plan'] ? (auth()->check() ? route('portal.dashboard') : ($signupEnabled ? route('register') : route('login'))) : route('payment.index', $planTarget) }}" class="inline-flex w-full items-center justify-center rounded-[1rem] {{ $plan['free_plan'] ? 'border border-emerald-400/24 bg-transparent text-emerald-100 hover:bg-emerald-400/8' : 'bg-emerald-500 text-white hover:bg-emerald-400' }} px-6 py-4 text-sm font-semibold transition">
                                        {{ $plan['free_plan'] ? __('Start for Free') : __('Choose Plan') }}
                                    </a>
                                </div>

                                <div class="nova-soft-surface mt-8 rounded-[1.5rem] border border-white/7 bg-white/[0.03] p-4">
                                    <p class="text-sm font-semibold text-white">{{ __('Included in this plan') }}</p>
                                    <p class="mt-2 text-xs leading-6 text-white/46">{{ __('Workspace access, scheduling depth, plan permissions, and automation controls configured for this tier.') }}</p>
                                </div>

                                <div class="mt-7 flex-1 space-y-3">
                                    @foreach (($plan['features'] ?? []) as $feature)
                                        @continue(is_string($feature['label'] ?? null) && (stripos($feature['label'], 'approval') !== false || stripos($feature['label'], 'approvals') !== false))
                                        <div class="nova-soft-surface flex items-center justify-between gap-4 rounded-[1.15rem] border border-white/7 bg-white/[0.025] px-4 py-3.5">
                                            <div class="flex min-w-0 items-center gap-3">
                                                <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full {{ $feature['check'] ? 'bg-emerald-400/12 text-emerald-100' : 'bg-white/[0.05] text-white/36' }}">
                                                    <i class="fa-light {{ $feature['check'] ? 'fa-check' : 'fa-minus' }} text-[11px]"></i>
                                                </span>
                                                <span class="truncate text-sm font-medium text-white/78">{{ $feature['label'] }}</span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                @if(($feature['display'] ?? null) !== null && ($feature['display'] ?? '') !== '')
                                                    <span class="inline-flex items-center rounded-full bg-white/[0.05] px-2.5 py-1 text-[11px] font-semibold text-white/52">
                                                        {{ $feature['display'] }}
                                                    </span>
                                                @endif
                                                @if (!empty($feature['subfeature']))
                                                    <div x-data="{ open: false }" class="relative">
                                                        <button type="button" x-on:mouseenter="open = true" x-on:mouseleave="open = false" class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-white/[0.05] text-white/54 transition hover:bg-white/[0.09] hover:text-white">
                                                            <i class="fa-light fa-info text-[11px]"></i>
                                                        </button>
                                                        <div x-show="open" x-transition.opacity.duration.150ms x-on:mouseenter="open = true" x-on:mouseleave="open = false" class="nova-scrollbar nova-popover-surface absolute right-0 top-full z-[120] mt-3 max-h-[22rem] w-[20rem] overflow-y-auto overflow-x-hidden rounded-[1.25rem] border border-white/10 bg-[#0d1120] p-4 pr-3 shadow-[0_30px_80px_-40px_rgba(0,0,0,0.9)]" style="display: none; scrollbar-gutter: stable;">
                                                            @foreach (($feature['subfeature'] ?? []) as $tabGroup)
                                                                <div class="mb-4 last:mb-0">
                                                                    <p class="mb-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-white/42">{{ $tabGroup['tab_name'] }}</p>
                                                                    <div class="space-y-2">
                                                                        @foreach ($tabGroup['items'] as $sub)
                                                                            <div class="nova-popover-soft flex items-center justify-between gap-3 rounded-[0.95rem] border border-white/7 bg-white/[0.03] px-3 py-2.5">
                                                                                <div class="flex min-w-0 items-center gap-2">
                                                                                    <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full {{ $sub['check'] ? 'bg-emerald-400/12 text-emerald-100' : 'bg-white/[0.05] text-white/36' }}">
                                                                                        <i class="fa-light {{ $sub['check'] ? 'fa-check' : 'fa-minus' }} text-[10px]"></i>
                                                                                    </span>
                                                                                    <span class="truncate text-xs font-medium text-white/78">{{ $sub['label'] }}</span>
                                                                                </div>
                                                                                @if(($sub['display'] ?? null) !== null && ($sub['display'] ?? '') !== '')
                                                                                    <span class="inline-flex items-center rounded-full bg-white/[0.05] px-2 py-1 text-[10px] font-semibold text-white/52">{{ $sub['display'] }}</span>
                                                                                @endif
                                                                            </div>
                                                                        @endforeach
                                                                    </div>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="guest-marketing-shell mx-auto px-6 pb-10 pt-4 lg:px-12">
        <p class="text-center text-sm font-medium text-white/40">{{ __('Trusted by secure payment services') }}</p>
        <div class="mt-6 flex flex-wrap justify-center gap-4">
            @foreach ([
                ['name' => 'Stripe', 'icon' => 'fa-brands fa-stripe'],
                ['name' => 'PayPal', 'icon' => 'fa-brands fa-paypal'],
                ['name' => 'Visa', 'icon' => 'fa-brands fa-cc-visa'],
                ['name' => 'Mastercard', 'icon' => 'fa-brands fa-cc-mastercard'],
                ['name' => 'Apple Pay', 'icon' => 'fa-brands fa-cc-apple-pay'],
            ] as $brand)
                <div class="nova-soft-surface inline-flex items-center gap-3 rounded-[1.15rem] border border-white/8 bg-white/[0.03] px-4 py-3 text-white/72">
                    <i class="{{ $brand['icon'] }} text-xl"></i>
                    <span class="text-sm font-semibold">{{ $brand['name'] }}</span>
                </div>
            @endforeach
        </div>
    </section>

    <section class="guest-marketing-shell guest-section-space mx-auto px-6 pt-0 lg:px-12">
        <div class="grid gap-4 lg:grid-cols-3">
            @foreach ($featuredFaqs->take(3) as $faq)
                <div class="nova-card-surface rounded-[1.8rem] border border-white/8 bg-[linear-gradient(180deg,rgba(14,17,31,0.92)_0%,rgba(10,13,25,0.82)_100%)] p-6">
                    <p class="text-sm font-semibold text-white">{{ $faq->titleForLocale() }}</p>
                    <p class="mt-3 text-sm leading-7 text-white/58">{{ $faq->contentPreview(150) }}</p>
                </div>
            @endforeach
        </div>
    </section>
@endcomponent
