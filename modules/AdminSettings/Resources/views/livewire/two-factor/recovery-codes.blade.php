<div
    class="py-6 space-y-6 border shadow-sm rounded-xl border-zinc-200 dark:border-white/10"
    wire:cloak
    x-data="{ showRecoveryCodes: false }"
>
    <div class="px-6 space-y-2">
        <div class="flex items-center gap-2">
            <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">2FA</span>
            <h3 class="text-lg font-semibold text-zinc-950 dark:text-white">{{ __('2FA recovery codes') }}</h3>
        </div>
        <p class="text-sm text-zinc-600 dark:text-zinc-400">
            {{ __('Recovery codes let you regain access if you lose your 2FA device. Store them in a secure password manager.') }}
        </p>
    </div>

    <div class="px-6">
        <div class="flex flex-wrap items-center gap-3">
            <x-ui.button
                variant="secondary"
                @click="showRecoveryCodes = !showRecoveryCodes"
                x-bind:aria-expanded="showRecoveryCodes"
                aria-controls="recovery-codes-section"
            >
                <span x-show="!showRecoveryCodes">{{ __('View recovery codes') }}</span>
                <span x-show="showRecoveryCodes" x-cloak>{{ __('Hide recovery codes') }}</span>
            </x-ui.button>

            @if (filled($recoveryCodes))
                <x-ui.button
                    x-show="showRecoveryCodes"
                    variant="outline"
                    wire:click="regenerateRecoveryCodes"
                >
                    {{ __('Regenerate codes') }}
                </x-ui.button>
            @endif
        </div>

        <div
            x-show="showRecoveryCodes"
            x-transition
            id="recovery-codes-section"
            class="relative overflow-hidden"
            x-bind:aria-hidden="!showRecoveryCodes"
        >
            <div class="mt-3 space-y-3">
                @error('recoveryCodes')
                    <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-300">{{ $message }}</div>
                @enderror

                @if (filled($recoveryCodes))
                    <div
                        class="grid gap-1 p-4 font-mono text-sm rounded-lg bg-zinc-100 dark:bg-white/5"
                        role="list"
                        aria-label="{{ __('Recovery codes') }}"
                    >
                        @foreach($recoveryCodes as $code)
                            <div
                                role="listitem"
                                class="select-text"
                                wire:loading.class="opacity-50 animate-pulse"
                            >
                                {{ $code }}
                            </div>
                        @endforeach
                    </div>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __('Each recovery code can be used once to access your account and will be removed after use. If you need more, click Regenerate codes above.') }}
                    </p>
                @endif
            </div>
        </div>
    </div>
</div>
