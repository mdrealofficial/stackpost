@php
    $socialProviders = collect([
        [
            'key' => 'google',
            'label' => __('Continue with Google'),
            'icon' => '<svg class="h-4 w-4" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="#EA4335" d="M12 10.2v3.9h5.5c-.2 1.2-.9 2.3-1.9 3.1l3 2.3c1.8-1.6 2.9-4 2.9-6.8 0-.7-.1-1.4-.2-2H12z" /><path fill="#34A853" d="M12 22c2.6 0 4.8-.9 6.4-2.5l-3-2.3c-.8.6-1.9 1-3.4 1-2.6 0-4.8-1.7-5.6-4.1l-3.1 2.4C4.9 19.8 8.2 22 12 22z" /><path fill="#4A90E2" d="M6.4 14.1c-.2-.6-.3-1.3-.3-2.1s.1-1.4.3-2.1L3.3 7.5C2.5 9 2 10.4 2 12s.5 3 1.3 4.5l3.1-2.4z" /><path fill="#FBBC05" d="M12 5.8c1.4 0 2.6.5 3.6 1.4l2.7-2.7C16.8 3.1 14.6 2 12 2 8.2 2 4.9 4.2 3.3 7.5l3.1 2.4C7.2 7.5 9.4 5.8 12 5.8z" /></svg>',
            'enabled' => (string) get_option('auth_google_login_status', '0') === '1'
                && filled((string) get_option('auth_google_login_client_id', ''))
                && filled((string) get_option('auth_google_login_client_secret', '')),
        ],
        [
            'key' => 'facebook',
            'label' => __('Continue with Facebook'),
            'icon' => '<i class="fa-brands fa-square-facebook text-lg" style="color:#1877F2;"></i>',
            'enabled' => (string) get_option('auth_facebook_login_status', '0') === '1'
                && filled((string) get_option('auth_facebook_login_app_id', ''))
                && filled((string) get_option('auth_facebook_login_app_secret', '')),
        ],
        [
            'key' => 'x',
            'label' => __('Continue with X'),
            'icon' => '<i class="fa-brands fa-x-twitter text-lg" style="color:#111827;"></i>',
            'enabled' => (string) get_option('auth_x_login_status', '0') === '1'
                && filled((string) get_option('auth_x_login_client_id', ''))
                && filled((string) get_option('auth_x_login_client_secret', '')),
        ],
    ])->where('enabled')->values();
@endphp

<div class="flex flex-col gap-6">
    <div class="space-y-2 text-center">
        <h1 class="lb-serif text-4xl leading-none">{{ __('Log in to your account') }}</h1>
        <p class="lb-copy text-sm">{{ __('Continue managing social profiles, scheduled posts, AI captions, analytics, and reports.') }}</p>
    </div>

    @if (session('status') && session('status') !== __('Language switched successfully.'))
        <div class="mb-5 rounded-2xl border px-4 py-3 text-sm font-medium" style="border-color: rgba(16, 185, 129, 0.22); background: rgba(16, 185, 129, 0.08); color: #6ee7b7;">
            {{ session('status') }}
        </div>
    @endif

    <form x-data class="flex flex-col gap-6" x-on:submit.prevent="$wire.login()">
        <div class="space-y-2.5">
            <label for="login" class="block text-sm font-medium" style="color: var(--theme-header-text-color);">{{ __('Username or email') }}</label>
            <input
                id="login"
                wire:model.defer="identifier"
                name="login"
                type="text"
                required
                autofocus
                autocomplete="username"
                placeholder="{{ __('Enter username or email') }}"
                class="flex h-11 w-full border px-4 text-sm font-medium shadow-[0_1px_2px_rgba(15,23,42,0.04)] outline-none transition duration-200 placeholder:font-normal placeholder:text-[var(--theme-input-placeholder)] focus:border-[var(--theme-accent)] focus:ring-4 focus:ring-[color:rgba(var(--theme-accent-rgb),0.10)]"
                style="border-radius: var(--theme-input-radius, 0.75rem); border-color: var(--theme-border-color); background-color: var(--theme-input-surface); color: var(--theme-input-text);"
            >
            @error('identifier')
                <div class="text-sm font-medium" style="color: var(--theme-danger-color);">{{ $message }}</div>
            @enderror
        </div>

        <div class="relative">
            <div class="space-y-2.5">
                <label for="password" class="block text-sm font-medium" style="color: var(--theme-header-text-color);">{{ __('Password') }}</label>
                <input
                    id="password"
                    wire:model.defer="password"
                    name="password"
                    type="password"
                    required
                    autocomplete="current-password"
                    placeholder="{{ __('Password') }}"
                    class="flex h-11 w-full border px-4 text-sm font-medium shadow-[0_1px_2px_rgba(15,23,42,0.04)] outline-none transition duration-200 placeholder:font-normal placeholder:text-[var(--theme-input-placeholder)] focus:border-[var(--theme-accent)] focus:ring-4 focus:ring-[color:rgba(var(--theme-accent-rgb),0.10)]"
                    style="border-radius: var(--theme-input-radius, 0.75rem); border-color: var(--theme-border-color); background-color: var(--theme-input-surface); color: var(--theme-input-text);"
                >
                @error('password')
                    <div class="text-sm font-medium" style="color: var(--theme-danger-color);">{{ $message }}</div>
                @enderror
            </div>

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="absolute top-0 end-0 text-sm font-medium transition hover:opacity-90" style="color: var(--theme-link-color);" onmouseover="this.style.color='var(--theme-link-hover-color)'" onmouseout="this.style.color='var(--theme-link-color)'" wire:navigate>
                    {{ __('Forgot your password?') }}
                </a>
            @endif
        </div>

        <label for="remember-1" class="group inline-flex cursor-pointer items-start gap-3">
            <input id="remember-1" type="checkbox" class="peer sr-only" wire:model.defer="remember" name="remember" value="1">

            <span class="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-[0.4rem] border border-slate-300 bg-white text-white shadow-[0_1px_2px_rgba(15,23,42,0.06)] transition-all duration-150 group-hover:border-[color:rgba(var(--theme-accent-rgb),0.45)] peer-checked:border-[color:var(--theme-accent)] peer-checked:bg-[color:var(--theme-accent)] peer-checked:text-white peer-checked:shadow-[0_0_0_3px_rgba(var(--theme-accent-rgb),0.14)] peer-checked:[&_svg]:opacity-100 dark:border-slate-700 dark:bg-slate-950">
                <svg viewBox="0 0 16 16" aria-hidden="true" class="h-3.5 w-3.5 opacity-0 transition-opacity duration-150" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2">
                    <path d="M3.5 8.5 6.5 11.5 12.5 4.5"></path>
                </svg>
            </span>

            <span class="min-w-0 pt-0.5">
                <span class="block text-sm font-medium text-slate-200" style="color: var(--theme-header-text-color);">{{ __('Remember me') }}</span>
            </span>
        </label>

        @if (function_exists('captcha_enabled') && captcha_enabled())
            @include(theme_view('livewire.auth.partials.captcha', 'guest'))
        @endif

        <div class="flex items-center justify-end">
            <button
                type="submit"
                class="lb-button inline-flex h-12 w-full cursor-pointer items-center justify-center gap-2 whitespace-nowrap px-5 text-sm font-black disabled:pointer-events-none disabled:opacity-50"
                data-test="login-button"
                wire:loading.attr="disabled"
                wire:target="login"
            >
                <span wire:loading.remove wire:target="login">{{ __('Log in') }}</span>
                <span wire:loading wire:target="login">{{ __('Logging in...') }}</span>
            </button>
        </div>
    </form>

    @if ($socialProviders->isNotEmpty())
        <div class="space-y-4">
            <div class="flex items-center gap-3 text-xs uppercase tracking-[0.22em] text-neutral-400">
                <span class="h-px flex-1 bg-slate-200"></span>
                <span>{{ __('Or continue with') }}</span>
                <span class="h-px flex-1 bg-slate-200"></span>
            </div>

            <div class="grid w-full gap-3 grid-cols-1">
                @foreach ($socialProviders as $provider)
                    <a
                        href="{{ url('auth/login/'.$provider['key']) }}"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-full border bg-white px-4 py-3 text-sm font-bold whitespace-nowrap transition hover:bg-neutral-50"
                        style="border-color: var(--lb-line);"
                    >
                        {!! $provider['icon'] !!}
                        <span>{{ $provider['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    @if (auth_signup_enabled() && Route::has('register'))
        <div class="space-x-1 text-sm text-center rtl:space-x-reverse text-neutral-500">
            <span>{{ __("Don't have an account?") }}</span>
            <a href="{{ route('register') }}" class="font-medium transition hover:opacity-90" style="color: var(--theme-link-color);" onmouseover="this.style.color='var(--theme-link-hover-color)'" onmouseout="this.style.color='var(--theme-link-color)'" wire:navigate>{{ __('Sign up') }}</a>
        </div>
    @endif
</div>
