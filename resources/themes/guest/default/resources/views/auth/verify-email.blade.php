@component(theme_view('layouts.auth', 'guest'), ['title' => __('Email verification')])
    <div class="mt-4 flex flex-col gap-6">
        <p class="text-center text-sm leading-6 text-white/72">
            {{ __('Please verify your email address by clicking on the link we just emailed to you.') }}
        </p>

        @if (session('status') == 'verification-link-sent')
            <p class="text-center text-sm font-medium text-emerald-600">
                {{ __('A new verification link has been sent to the email address you provided during registration.') }}
            </p>
        @endif

        <div class="flex flex-col items-center justify-between space-y-3">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <x-ui.button type="submit" class="w-full">
                    {{ __('Resend verification email') }}
                </x-ui.button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <x-ui.button variant="ghost" type="submit" class="cursor-pointer text-sm text-white/72" data-test="logout-button">
                    {{ __('Log out') }}
                </x-ui.button>
            </form>
        </div>
    </div>
@endcomponent
