@component(theme_view('layouts.auth', 'guest'), ['title' => __('Confirm password')])
    <div class="flex flex-col gap-6">
        <div class="space-y-2 text-center">
            <h1 class="text-3xl font-extrabold tracking-[-0.045em] text-slate-950">{{ __('Confirm password') }}</h1>
            <p class="text-sm text-slate-500">{{ __('This is a secure area of the application. Please confirm your password before continuing.') }}</p>
        </div>

        @if (session('status'))
            <p class="text-center text-sm font-medium text-emerald-600">{{ session('status') }}</p>
        @endif

        <form method="POST" action="{{ route('password.confirm.store') }}" class="flex flex-col gap-6">
            @csrf

            <x-ui.input
                name="password"
                :label="__('Password')"
                type="password"
                required
                autocomplete="current-password"
                :placeholder="__('Password')"
                :error="$errors->first('password')"
            />

            <x-ui.button type="submit" class="w-full" data-test="confirm-password-button">
                {{ __('Confirm') }}
            </x-ui.button>
        </form>
    </div>
@endcomponent
