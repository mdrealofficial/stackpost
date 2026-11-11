<section class="w-full">
    <div class="sr-only">{{ __('Security settings') }}</div>

    <x-settings.layout :heading="__('Update password')" :subheading="__('Ensure your account is using a long, random password to stay secure')">
        <form method="POST" wire:submit="updatePassword" class="mt-6 space-y-6">
            <x-ui.input
                wire:model="current_password"
                :label="__('Current password')"
                type="password"
                required
                autocomplete="current-password"
                :error="$errors->first('current_password')"
            />
            <x-ui.input
                wire:model="password"
                :label="__('New password')"
                type="password"
                required
                autocomplete="new-password"
                :error="$errors->first('password')"
            />
            <x-ui.input
                wire:model="password_confirmation"
                :label="__('Confirm password')"
                type="password"
                required
                autocomplete="new-password"
                :error="$errors->first('password_confirmation')"
            />

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <x-ui.button type="submit" class="w-full" data-test="update-password-button">{{ __('Save') }}</x-ui.button>
                </div>

                <x-action-message class="me-3" on="password-updated">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </form>

        @if ($canManageTwoFactor)
            <section class="mt-12">
                <h3 class="text-lg font-semibold text-zinc-950 dark:text-white">{{ __('Two-factor authentication') }}</h3>
                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Manage your two-factor authentication settings') }}</p>

                <div class="mx-auto flex w-full flex-col space-y-6 text-sm" wire:cloak>
                    @if ($twoFactorEnabled)
                        <div class="space-y-4">
                            <p class="text-sm leading-6 text-zinc-600 dark:text-zinc-400">
                                {{ __('You will be prompted for a secure, random pin during login, which you can retrieve from the TOTP-supported application on your phone.') }}
                            </p>

                            <div class="flex justify-start">
                                <x-ui.button variant="danger" wire:click="disable">
                                    {{ __('Disable 2FA') }}
                                </x-ui.button>
                            </div>

                            @livewire(\Modules\AdminSettings\Livewire\TwoFactor\RecoveryCodes::class, ['requiresConfirmation' => $requiresConfirmation])
                        </div>
                    @else
                        <div class="space-y-4">
                            <p class="text-sm leading-6 text-zinc-600 dark:text-zinc-400">
                                {{ __('When you enable two-factor authentication, you will be prompted for a secure pin during login. This pin can be retrieved from a TOTP-supported application on your phone.') }}
                            </p>

                            <x-ui.button wire:click="enable">
                                {{ __('Enable 2FA') }}
                            </x-ui.button>
                        </div>
                    @endif
                </div>
            </section>

            <div
                x-cloak
                x-data="{ open: $wire.entangle('showModal') }"
                x-show="open"
                class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/60 p-4"
                x-on:keydown.escape.window="$wire.closeModal()"
            >
                <div class="w-full max-w-2xl rounded-[1.75rem] border border-zinc-200 bg-white p-6 shadow-2xl dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="space-y-6">
                        <div class="flex flex-col items-center space-y-4">
                            <div class="flex h-20 w-20 items-center justify-center rounded-full border border-zinc-200 bg-zinc-100 text-sm font-semibold text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                                QR
                            </div>

                            <div class="space-y-2 text-center">
                                <h4 class="text-xl font-semibold text-zinc-950 dark:text-white">{{ $this->modalConfig['title'] }}</h4>
                                <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $this->modalConfig['description'] }}</p>
                            </div>
                        </div>

                        @if ($showVerificationStep)
                            <div class="space-y-6">
                                <div class="flex flex-col items-center justify-center space-y-3">
                                    <div class="w-full max-w-xs">
                                        <x-ui.input
                                            name="code"
                                            wire:model="code"
                                            maxlength="6"
                                            inputmode="numeric"
                                            :label="__('Authentication code')"
                                            :error="$errors->first('code')"
                                        />
                                    </div>
                                </div>

                                <div class="flex items-center space-x-3">
                                    <x-ui.button variant="outline" class="flex-1" wire:click="resetVerification">
                                        {{ __('Back') }}
                                    </x-ui.button>

                                    <x-ui.button class="flex-1" wire:click="confirmTwoFactor" x-bind:disabled="$wire.code.length < 6">
                                        {{ __('Confirm') }}
                                    </x-ui.button>
                                </div>
                            </div>
                        @else
                            @error('setupData')
                                <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-300">
                                    {{ $message }}
                                </div>
                            @enderror

                            <div class="flex justify-center">
                                <div class="relative aspect-square w-64 overflow-hidden rounded-lg border border-stone-200 dark:border-stone-700">
                                    @empty($qrCodeSvg)
                                        <div class="absolute inset-0 flex items-center justify-center bg-white dark:bg-stone-700">
                                            <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Loading...') }}</span>
                                        </div>
                                    @else
                                        <div class="flex h-full items-center justify-center p-4">
                                            <div class="rounded bg-white p-3">
                                                {!! $qrCodeSvg !!}
                                            </div>
                                        </div>
                                    @endempty
                                </div>
                            </div>

                            <div>
                                <x-ui.button :disabled="$errors->has('setupData')" class="w-full" wire:click="showVerificationIfNecessary">
                                    {{ $this->modalConfig['buttonText'] }}
                                </x-ui.button>
                            </div>

                            <div class="space-y-4">
                                <div class="relative flex items-center justify-center">
                                    <div class="absolute inset-0 top-1/2 h-px bg-stone-200 dark:bg-stone-600"></div>
                                    <span class="relative bg-white px-2 text-sm text-stone-600 dark:bg-stone-900 dark:text-stone-400">
                                        {{ __('or, enter the code manually') }}
                                    </span>
                                </div>

                                <div
                                    class="flex items-center space-x-2"
                                    x-data="{
                                        copied: false,
                                        async copy() {
                                            try {
                                                await navigator.clipboard.writeText('{{ $manualSetupKey }}');
                                                this.copied = true;
                                                setTimeout(() => this.copied = false, 1500);
                                            } catch (e) {}
                                        }
                                    }"
                                >
                                    <div class="flex w-full items-stretch rounded-xl border dark:border-stone-700">
                                        @empty($manualSetupKey)
                                            <div class="flex w-full items-center justify-center bg-stone-100 p-3 dark:bg-stone-700">
                                                <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Loading...') }}</span>
                                            </div>
                                        @else
                                            <input
                                                type="text"
                                                readonly
                                                value="{{ $manualSetupKey }}"
                                                class="w-full bg-transparent p-3 text-stone-900 outline-none dark:text-stone-100"
                                            />

                                            <button
                                                type="button"
                                                @click="copy()"
                                                class="border-l px-3 transition-colors dark:border-stone-600"
                                            >
                                                <span x-show="!copied">{{ __('Copy') }}</span>
                                                <span x-show="copied" class="text-emerald-600">{{ __('Copied') }}</span>
                                            </button>
                                        @endempty
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </x-settings.layout>
</section>
