<div class="flex flex-col gap-6">
    <div class="space-y-2 text-center">
        <h1 class="text-2xl font-semibold tracking-tight text-white">{{ __('Forgot password') }}</h1>
        <p class="text-sm text-white/72">{{ __('Enter your email to receive a password reset link') }}</p>
    </div>

    @if (session('status') && session('status') !== __('Language switched successfully.'))
        <p class="text-center text-sm font-medium text-emerald-600">{{ session('status') }}</p>
    @endif

    <form wire:submit.prevent="sendResetLink" class="flex flex-col gap-6">
        <x-ui.input
            wire:model.defer="email"
            name="email"
            :label="__('Email address')"
            type="email"
            required
            autofocus
            placeholder="email@example.com"
            :error="$errors->first('email')"
        />

        @if (function_exists('captcha_enabled') && captcha_enabled())
            @include(theme_view('livewire.auth.partials.captcha', 'guest'))
        @endif

        <x-ui.button type="submit" class="w-full" data-test="email-password-reset-link-button" wire:loading.attr="disabled" wire:target="sendResetLink">
            <span wire:loading.remove wire:target="sendResetLink">{{ __('Email password reset link') }}</span>
            <span wire:loading wire:target="sendResetLink">{{ __('Sending reset link...') }}</span>
        </x-ui.button>
    </form>

    <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-white/70">
        <span>{{ __('Or, return to') }}</span>
        <a href="{{ route('login') }}" class="font-medium transition hover:opacity-90" style="color: var(--theme-link-color);" onmouseover="this.style.color='var(--theme-link-hover-color)'" onmouseout="this.style.color='var(--theme-link-color)'" wire:navigate>{{ __('log in') }}</a>
    </div>
</div>
