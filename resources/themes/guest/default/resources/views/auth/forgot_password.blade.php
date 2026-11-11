<section class="relative w-screen min-h-screen flex items-stretch overflow-hidden bg-white overflow-x-hidden">
    @include("partials/login-screen", ["name" => __("Forgot password")])

    <div class="flex flex-col justify-center flex-1 px-6 md:px-8 py-16 bg-blueGray-100 z-10" style="background-image: url({{ theme_public_asset('images/pattern-light-big.svg') }}); background-position: center;">
        <div class="absolute inset-0 pointer-events-none" style="background:
            radial-gradient(circle at 25% 30%, rgba(79,70,229,.08) 0%, rgba(79,70,229,0) 22%),
            radial-gradient(circle at 78% 75%, rgba(59,130,246,.08) 0%, rgba(59,130,246,0) 24%);"></div>
        <div class="relative w-full max-w-lg mx-auto">
        <div class="show-on-mobile mb-6 text-center">
            <a class="inline-block" href="{{ url('') }}">
                <img class="h-10" src="{{ url(get_option('website_logo_brand_dark', 'public/img/logo-brand-dark.png')) }}" alt="">
            </a>
        </div>
        <form class="actionForm relative w-full space-y-5 bg-white rounded-3xl border border-white shadow-2xl p-8 md:p-12" action="{{ module_url('do_forgot_password') }}" method="POST">
            <div class="mb-10 text-center">
                <span class="inline-flex items-center px-4 py-2 mb-5 text-xs font-semibold uppercase tracking-widest rounded-full" style="background-color:#eef2ff;color:#4338ca; letter-spacing:0.22em;">
                    {{ __("Password recovery") }}
                </span>
                <h1 class="mb-4 font-bold font-heading tracking-tight text-gray-900" style="font-size:2.5rem; line-height:1.1;">{{ __("Reset your password") }}</h1>
                <p class="text-lg leading-8 text-gray-600">{{ __("Enter your account email and we will send you a secure reset link.") }}</p>
            </div>

            <div>
                <label for="email" class="block text-gray-700 font-semibold mb-2">{{ __("Email Address") }}</label>
                <input type="email" id="email" name="email" class="w-full px-4 py-3.5 text-gray-700 font-medium bg-white border border-gray-300 rounded-lg focus:ring focus:ring-indigo-300 outline-none" placeholder="{{ __('Enter your email') }}" required autofocus>
            </div>

            <div class="mb-3">
                {{ captcha_render() }}
            </div>

            <div class="msg-error mb-4"></div>

            <button type="submit" class="mb-6 py-4 px-9 w-full text-white text-lg font-semibold border border-indigo-700 rounded-2xl shadow-4xl focus:ring focus:ring-indigo-300 bg-indigo-600 hover:bg-indigo-700 transition ease-in-out duration-200">
                {{ __("Send Reset Link") }}
            </button>

            <p class="text-center pt-4 text-gray-600">
                <a href="{{ url('auth/login') }}" class="text-indigo-600 hover:text-indigo-700 font-medium">
                    <i class="fa fa-arrow-left mr-1"></i>{{ __("Back to login") }}
                </a>
            </p>
        </form>
        </div>
    </div>
</section>
