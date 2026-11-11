<div class="hide-on-mobile relative flex flex-col justify-center flex-1 px-14 py-16 overflow-hidden" style="background: linear-gradient(180deg, #f5f7ff 0%, #eef2ff 40%, #ffffff 100%);">
    <div class="absolute top-0 left-0 w-full h-full pointer-events-none" style="background:
        radial-gradient(circle at 14% 18%, rgba(79,70,229,.12) 0%, rgba(79,70,229,0) 26%),
        radial-gradient(circle at 72% 72%, rgba(59,130,246,.10) 0%, rgba(59,130,246,0) 28%),
        linear-gradient(90deg, rgba(255,255,255,0) 0%, rgba(255,255,255,.35) 100%);"></div>
    <div class="relative max-w-2xl mx-auto w-full">
        <a class="inline-flex items-center mb-14" href="{{ url('') }}">
            <img class="h-10" src="{{ url(get_option('website_logo_brand_dark', 'public/img/logo-brand-dark.png')) }}" alt="">
        </a>

        <div class="max-w-xl">
            <span class="inline-flex items-center px-4 py-2 mb-6 text-xs font-semibold uppercase tracking-widest rounded-full" style="background-color:#e0e7ff;color:#4338ca; letter-spacing:0.22em;">
                {{ __("Modern workspace") }}
            </span>
        <h2 class="mb-5 text-6xl font-bold font-heading tracking-tight leading-tight text-gray-900">
            {{ $name ?? __("Welcome Back") }}
        </h2>
            <p class="mb-10 text-xl leading-9 text-gray-600">
                {{ __("A calmer way to manage publishing, collaboration, and reporting for modern marketing teams.") }}
            </p>
        </div>

        <div class="grid grid-cols-1 gap-5">
            <div class="p-6 bg-white/90 rounded-3xl border border-white shadow-sm backdrop-blur">
                <div class="flex items-start">
                    <span class="inline-flex items-center justify-center w-12 h-12 mr-4 rounded-2xl text-indigo-600 bg-indigo-50">
                        <i class="fa-regular fa-circle-check text-lg"></i>
                    </span>
                    <div>
                        <h3 class="mb-2 text-xl font-semibold text-gray-900">{{ __("Focused execution") }}</h3>
                        <p class="text-gray-600 leading-7">{{ __("Keep planning, publishing, and review cycles in one place instead of chasing status across disconnected tools.") }}</p>
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-5">
                <div class="p-6 bg-white/90 rounded-3xl border border-white shadow-sm backdrop-blur">
                    <p class="mb-2 text-sm uppercase tracking-widest text-indigo-600 font-semibold" style="letter-spacing:0.18em;">{{ __("Visibility") }}</p>
                    <p class="text-3xl font-bold text-gray-900 mb-2">24/7</p>
                    <p class="text-gray-600 leading-7">{{ __("Track workflow health and campaign progress without friction.") }}</p>
                </div>
                <div class="p-6 bg-white/90 rounded-3xl border border-white shadow-sm backdrop-blur">
                    <p class="mb-2 text-sm uppercase tracking-widest text-indigo-600 font-semibold" style="letter-spacing:0.18em;">{{ __("Control") }}</p>
                    <p class="text-3xl font-bold text-gray-900 mb-2">{{ __("One place") }}</p>
                    <p class="text-gray-600 leading-7">{{ __("Publishing queues, team coordination, and reporting stay aligned.") }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
