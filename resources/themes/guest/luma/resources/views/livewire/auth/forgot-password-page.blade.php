<div class="flex flex-col gap-6">
    <div class="space-y-2 text-center">
        <h1 class="lb-serif text-4xl leading-none">{{ __('Forgot password') }}</h1>
        <p class="lb-copy text-sm">{{ __('Enter your email to receive a password reset link') }}</p>
    </div>

    @if (session('status') && session('status') !== __('Language switched successfully.'))
        <div class="rounded-2xl border px-4 py-3 text-center text-sm font-medium" style="border-color: rgba(16, 185, 129, 0.22); background: rgba(16, 185, 129, 0.08); color: var(--theme-success-color);">
            {{ session('status') }}
        </div>
    @endif

    <form wire:submit.prevent="sendResetLink" class="flex flex-col gap-6">
        <div class="space-y-2.5">
            <label for="email" class="block text-sm font-medium" style="color: var(--theme-header-text-color);">{{ __('Email address') }}</label>
            <input
                id="email"
                wire:model.defer="email"
                name="email"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="email@example.com"
                class="flex h-11 w-full border px-4 text-sm font-medium shadow-[0_1px_2px_rgba(15,23,42,0.04)] outline-none transition duration-200 placeholder:font-normal placeholder:text-[var(--theme-input-placeholder)] focus:border-[var(--theme-accent)] focus:ring-4 focus:ring-[color:rgba(var(--theme-accent-rgb),0.10)]"
                style="border-radius: var(--theme-input-radius, 0.75rem); border-color: var(--theme-border-color); background-color: var(--theme-input-surface); color: var(--theme-input-text);"
            >
            @error('email')
                <div class="text-sm font-medium" style="color: var(--theme-danger-color);">{{ $message }}</div>
            @enderror
        </div>

        @if (function_exists('captcha_enabled') && captcha_enabled())
            @include(theme_view('livewire.auth.partials.captcha', 'guest'))
        @endif

        <button type="submit" class="lb-button inline-flex h-12 w-full items-center justify-center px-5 text-sm font-black" data-test="email-password-reset-link-button" wire:loading.attr="disabled" wire:target="sendResetLink">
            <span wire:loading.remove wire:target="sendResetLink">{{ __('Email password reset link') }}</span>
            <span wire:loading wire:target="sendResetLink">{{ __('Sending reset link...') }}</span>
        </button>
    </form>

    <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-slate-500">
        <span>{{ __('Or, return to') }}</span>
        <a href="{{ route('login') }}" class="font-medium transition hover:opacity-90" style="color: var(--theme-link-color);" onmouseover="this.style.color='var(--theme-link-hover-color)'" onmouseout="this.style.color='var(--theme-link-color)'" wire:navigate>{{ __('log in') }}</a>
    </div>
</div>
