@php
    $selectedTimezone = (string) $timezone;
@endphp

<div class="mx-auto w-full max-w-3xl">
    <div class="flex flex-col gap-6">
        <div class="space-y-4 text-center">
            <span class="lb-pill inline-flex items-center rounded-full px-4 py-2 text-xs font-black uppercase tracking-[0.2em]">
                {{ __('Create workspace') }}
            </span>
            <div class="space-y-3">
                <h1 class="lb-serif text-5xl leading-none">{{ __('Create your account') }}</h1>
                <p class="lb-copy mx-auto max-w-2xl text-base">
                    {{ __('Launch social content, publishing queues, AI copy, and growth reporting from one SaaS workspace.') }}
                </p>
            </div>
        </div>

        @if (session('status') && session('status') !== __('Language switched successfully.'))
            <p class="text-center text-sm font-medium text-emerald-600">{{ session('status') }}</p>
        @endif

        <form wire:submit.prevent="register" class="flex flex-col gap-6">
            <div class="grid gap-5 md:grid-cols-2">
                <x-ui.input
                    class="md:col-span-2"
                    wire:model.defer="name"
                    name="name"
                    :label="__('Full Name')"
                    type="text"
                    required
                    autofocus
                    autocomplete="name"
                    :placeholder="__('Enter your full name')"
                    :error="$errors->first('name')"
                />

                <x-ui.input
                    wire:model.defer="email"
                    name="email"
                    :label="__('Email Address')"
                    type="email"
                    required
                    autocomplete="email"
                    :placeholder="__('Enter your email address')"
                    :error="$errors->first('email')"
                />

                <x-ui.input
                    wire:model.defer="username"
                    name="username"
                    :label="__('Username')"
                    type="text"
                    required
                    autocomplete="username"
                    :placeholder="__('Choose a username')"
                    :error="$errors->first('username')"
                />

                <x-ui.input
                    wire:model.defer="password"
                    name="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="new-password"
                    :placeholder="__('Enter your password')"
                    :error="$errors->first('password')"
                />

                <x-ui.input
                    wire:model.defer="password_confirmation"
                    name="password_confirmation"
                    :label="__('Confirm Password')"
                    type="password"
                    required
                    autocomplete="new-password"
                    :placeholder="__('Confirm your password')"
                    :error="$errors->first('password_confirmation')"
                />

                <x-ui.select
                    class="md:col-span-2"
                    wire:model.defer="timezone"
                    name="timezone"
                    :label="__('Timezone')"
                    :error="$errors->first('timezone')"
                >
                    <option value="">{{ __('Select your timezone') }}</option>
                    @foreach (timezone_select_options() as $timezoneOption)
                        <option value="{{ $timezoneOption['value'] }}" @selected($selectedTimezone === $timezoneOption['value'])>
                            {{ $timezoneOption['label'] }}
                        </option>
                    @endforeach
                </x-ui.select>
            </div>

            <x-ui.checkbox wire:model.defer="accept_terms" name="accept_terms" value="1" :checked="$accept_terms" labelClass="text-slate-600">
                {{ __('I agree to the') }}
                <a href="{{ route('guest.terms-of-use') }}" class="font-semibold transition hover:opacity-90" style="color: var(--theme-link-color);" onmouseover="this.style.color='var(--theme-link-hover-color)'" onmouseout="this.style.color='var(--theme-link-color)'" wire:navigate>{{ __('Terms & Conditions') }}</a>
            </x-ui.checkbox>
            @if ($errors->first('accept_terms'))
                <p class="-mt-3 text-sm font-medium" style="color: var(--theme-danger-color);">{{ $errors->first('accept_terms') }}</p>
            @endif

            @if (function_exists('captcha_enabled') && captcha_enabled())
                @include(theme_view('livewire.auth.partials.captcha', 'guest'))
            @endif

            <div class="pt-1">
                <button type="submit" class="lb-button inline-flex h-12 w-full items-center justify-center px-5 text-sm font-black" data-test="register-user-button" wire:loading.attr="disabled" wire:target="register">
                    <span wire:loading.remove wire:target="register">{{ __('Sign Up') }}</span>
                    <span wire:loading wire:target="register">{{ __('Creating account...') }}</span>
                </button>
            </div>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-slate-500">
            <span>{{ __('Already have an account?') }}</span>
            <a href="{{ route('login') }}" class="font-semibold transition hover:opacity-90" style="color: var(--theme-link-color);" onmouseover="this.style.color='var(--theme-link-hover-color)'" onmouseout="this.style.color='var(--theme-link-color)'" wire:navigate>{{ __('Sign in') }}</a>
        </div>
    </div>
</div>
