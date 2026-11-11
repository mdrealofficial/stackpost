<div class="flex flex-col gap-6">
    <div class="space-y-2 text-center">
        <h1 class="lb-serif text-4xl leading-none">{{ __('Reset password') }}</h1>
        <p class="lb-copy text-sm">{{ __('Please enter your new password below') }}</p>
    </div>

    @if (session('status') && session('status') !== __('Language switched successfully.'))
        <div class="rounded-2xl border px-4 py-3 text-center text-sm font-medium" style="border-color: rgba(16, 185, 129, 0.22); background: rgba(16, 185, 129, 0.08); color: var(--theme-success-color);">
            {{ session('status') }}
        </div>
    @endif

    <form wire:submit.prevent="resetPassword" class="flex flex-col gap-6">
        <input type="hidden" wire:model="token">

        <div class="space-y-2.5">
            <label for="reset-email" class="block text-sm font-medium" style="color: var(--theme-header-text-color);">{{ __('Email') }}</label>
            <input
                id="reset-email"
                wire:model.defer="email"
                name="email"
                type="email"
                required
                autocomplete="email"
                class="flex h-11 w-full border px-4 text-sm font-medium shadow-[0_1px_2px_rgba(15,23,42,0.04)] outline-none transition duration-200 placeholder:font-normal placeholder:text-[var(--theme-input-placeholder)] focus:border-[var(--theme-accent)] focus:ring-4 focus:ring-[color:rgba(var(--theme-accent-rgb),0.10)]"
                style="border-radius: var(--theme-input-radius, 0.75rem); border-color: var(--theme-border-color); background-color: var(--theme-input-surface); color: var(--theme-input-text);"
            >
            @error('email')
                <div class="text-sm font-medium" style="color: var(--theme-danger-color);">{{ $message }}</div>
            @enderror
        </div>

        <div class="space-y-2.5">
            <label for="reset-password" class="block text-sm font-medium" style="color: var(--theme-header-text-color);">{{ __('Password') }}</label>
            <input
                id="reset-password"
                wire:model.defer="password"
                name="password"
                type="password"
                required
                autocomplete="new-password"
                placeholder="{{ __('Password') }}"
                class="flex h-11 w-full border px-4 text-sm font-medium shadow-[0_1px_2px_rgba(15,23,42,0.04)] outline-none transition duration-200 placeholder:font-normal placeholder:text-[var(--theme-input-placeholder)] focus:border-[var(--theme-accent)] focus:ring-4 focus:ring-[color:rgba(var(--theme-accent-rgb),0.10)]"
                style="border-radius: var(--theme-input-radius, 0.75rem); border-color: var(--theme-border-color); background-color: var(--theme-input-surface); color: var(--theme-input-text);"
            >
            @error('password')
                <div class="text-sm font-medium" style="color: var(--theme-danger-color);">{{ $message }}</div>
            @enderror
        </div>

        <div class="space-y-2.5">
            <label for="reset-password-confirmation" class="block text-sm font-medium" style="color: var(--theme-header-text-color);">{{ __('Confirm password') }}</label>
            <input
                id="reset-password-confirmation"
                wire:model.defer="password_confirmation"
                name="password_confirmation"
                type="password"
                required
                autocomplete="new-password"
                placeholder="{{ __('Confirm password') }}"
                class="flex h-11 w-full border px-4 text-sm font-medium shadow-[0_1px_2px_rgba(15,23,42,0.04)] outline-none transition duration-200 placeholder:font-normal placeholder:text-[var(--theme-input-placeholder)] focus:border-[var(--theme-accent)] focus:ring-4 focus:ring-[color:rgba(var(--theme-accent-rgb),0.10)]"
                style="border-radius: var(--theme-input-radius, 0.75rem); border-color: var(--theme-border-color); background-color: var(--theme-input-surface); color: var(--theme-input-text);"
            >
            @error('password_confirmation')
                <div class="text-sm font-medium" style="color: var(--theme-danger-color);">{{ $message }}</div>
            @enderror
        </div>

        @if (function_exists('captcha_enabled') && captcha_enabled())
            @include(theme_view('livewire.auth.partials.captcha', 'guest'))
        @endif

        <div class="flex items-center justify-end">
            <button type="submit" class="lb-button inline-flex h-12 w-full items-center justify-center px-5 text-sm font-black" data-test="reset-password-button" wire:loading.attr="disabled" wire:target="resetPassword">
                <span wire:loading.remove wire:target="resetPassword">{{ __('Reset password') }}</span>
                <span wire:loading wire:target="resetPassword">{{ __('Resetting password...') }}</span>
            </button>
        </div>
    </form>
</div>
