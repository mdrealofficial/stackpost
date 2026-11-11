@php
    $faqs = Home::getFaqs();
@endphp

<section class="relative py-24 bg-blueGray-50 overflow-hidden">
    <div class="absolute inset-0 pointer-events-none" style="background: radial-gradient(circle at 20% 20%, rgba(99,102,241,.08) 0%, rgba(99,102,241,0) 32%), radial-gradient(circle at 80% 40%, rgba(59,130,246,.08) 0%, rgba(59,130,246,0) 28%);"></div>
    <div class="relative z-10 container px-4 mx-auto">
        <div class="grid gap-10 lg:grid-cols-2 lg:items-start">
            <div class="lg:sticky lg:top-28">
                <span class="inline-flex items-center px-4 py-2 mb-6 text-xs font-semibold uppercase tracking-widest rounded-full" style="background-color:#e0e7ff;color:#4338ca; letter-spacing:0.22em;">
                    {{ __("Answers that move fast") }}
                </span>
                <h2 class="mb-6 text-5xl md:text-6xl font-bold font-heading tracking-tight leading-tight text-gray-900">
                    {{ __("Frequently Asked Questions") }}
                </h2>
                <p class="mb-8 max-w-xl text-lg leading-8 text-gray-600">
                    {{ __("Everything teams usually ask before switching to the platform, from onboarding and collaboration to billing and support.") }}
                </p>

                <div class="grid gap-4 sm:grid-cols-2 mb-6">
                    <div class="rounded-3xl border border-white bg-white/90 px-6 py-5 shadow-sm">
                        <p class="text-sm uppercase tracking-widest text-indigo-600 font-semibold mb-2" style="letter-spacing:0.18em;">{{ __("Coverage") }}</p>
                        <p class="text-3xl font-bold text-gray-900 mb-2">{{ __("Billing") }}</p>
                        <p class="text-gray-600 leading-7">{{ __("Pricing, subscriptions, and what is included in each plan.") }}</p>
                    </div>
                    <div class="rounded-3xl border border-white bg-white/90 px-6 py-5 shadow-sm">
                        <p class="text-sm uppercase tracking-widest text-indigo-600 font-semibold mb-2" style="letter-spacing:0.18em;">{{ __("Support") }}</p>
                        <p class="text-3xl font-bold text-gray-900 mb-2">{{ __("Setup") }}</p>
                        <p class="text-gray-600 leading-7">{{ __("Onboarding, workflow guidance, and answers for day-to-day usage.") }}</p>
                    </div>
                </div>

                <div class="p-7 bg-white rounded-[2rem] border border-indigo-100 shadow-[0_20px_60px_rgba(79,70,229,0.08)]">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm uppercase tracking-widest text-indigo-600 font-semibold mb-3" style="letter-spacing:0.22em;">{{ __("Still need help?") }}</p>
                            <h3 class="text-2xl font-bold text-gray-900 mb-3">{{ __("Talk to our team directly") }}</h3>
                            <p class="text-gray-600 leading-7 mb-5">{{ __("If you need a specific answer about your workflow, billing, or setup, our team can help directly.") }}</p>
                            <a class="inline-flex items-center text-indigo-600 hover:text-indigo-700 font-semibold" href="{{ url('contact') }}">
                                {{ __("Contact us") }}
                                <i class="fa-solid fa-arrow-right ml-2 text-sm"></i>
                            </a>
                        </div>
                        <span class="hidden sm:inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-indigo-50 text-indigo-600 flex-shrink-0">
                            <i class="fa-regular fa-message-lines text-xl"></i>
                        </span>
                    </div>
                </div>
            </div>
            <div class="space-y-4" x-data="{ open: {{ optional($faqs->first())->id ?? 'null' }} }">
                @foreach($faqs as $faq)
                    <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
                        <button
                            type="button"
                            class="w-full px-7 py-6 text-left"
                            x-on:click="open === {{ $faq->id }} ? open = null : open = {{ $faq->id }}"
                        >
                            <div class="flex items-start justify-between gap-6">
                                <h3 class="text-xl font-semibold leading-8 text-gray-900">
                                    {{ $faq->title }}
                                </h3>
                                <span class="inline-flex items-center justify-center w-11 h-11 rounded-2xl flex-shrink-0"
                                      :class="open === {{ $faq->id }} ? 'bg-indigo-600 text-white' : 'bg-indigo-50 text-indigo-600'">
                                    <i class="fa-solid" :class="open === {{ $faq->id }} ? 'fa-minus' : 'fa-plus'"></i>
                                </span>
                            </div>
                        </button>
                        <div
                            x-ref="container_{{ $faq->id }}"
                            :style="open === {{ $faq->id }} ? 'height: ' + $refs['container_{{ $faq->id }}'].scrollHeight + 'px' : ''"
                            class="h-0 overflow-hidden duration-500"
                        >
                            <div class="px-7 pb-7 text-gray-600 leading-8">
                                {!! $faq->content !!}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>
