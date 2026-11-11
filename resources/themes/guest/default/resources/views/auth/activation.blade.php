<div class="min-h-screen flex items-center justify-center px-6 py-16 bg-blueGray-100" style="background-image: url({{ theme_public_asset('images/pattern-light-big.svg') }}); background-position: center;">
    <div class="absolute inset-0 pointer-events-none" style="background:
        radial-gradient(circle at 25% 30%, rgba(79,70,229,.08) 0%, rgba(79,70,229,0) 22%),
        radial-gradient(circle at 78% 75%, rgba(59,130,246,.08) 0%, rgba(59,130,246,0) 24%);"></div>
    <div class="relative w-full max-w-lg bg-white rounded-3xl border border-white shadow-2xl p-8 md:p-12 text-center">
        <div class="inline-flex items-center justify-center w-20 h-20 mb-6 rounded-full {{ $status ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600' }}">
            <i class="fas {{ $status ? 'fa-check-circle' : 'fa-times-circle' }} text-4xl"></i>
        </div>
        <span class="inline-flex items-center px-4 py-2 mb-4 text-xs font-semibold uppercase tracking-widest rounded-full" style="background-color:#eef2ff;color:#4338ca; letter-spacing:0.22em;">
            {{ $status ? __('Account activated') : __('Activation issue') }}
        </span>
        <h2 class="mb-4 font-bold font-heading tracking-tight text-gray-900" style="font-size:2.5rem; line-height:1.1;">
            {{ $status ? __('Activation Successful!') : __('Activation Failed') }}
        </h2>
        <p class="max-w-lg mx-auto text-lg leading-8 text-gray-600">
            {{ $message ?? ($status
                ? __('Your account has been activated. You can now login.')
                : __('The activation link is invalid, expired or your account was already activated.')) }}
        </p>
        <div class="mt-8">
            <a href="{{ url('auth/login') }}" class="inline-block py-4 px-9 text-white text-lg font-semibold border border-indigo-700 rounded-2xl shadow-4xl focus:ring focus:ring-indigo-300 bg-indigo-600 hover:bg-indigo-700 transition ease-in-out duration-200 text-center" style="min-width:220px;">
                <i class="fa fa-arrow-left mr-2"></i>
                {{ __("Back to Login") }}
            </a>
        </div>
    </div>
</div>
