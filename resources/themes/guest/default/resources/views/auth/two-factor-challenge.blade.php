@component(theme_view('layouts.auth', 'guest'), ['title' => __('Two-factor authentication')])
    <div class="flex flex-col gap-6">
        <div
            class="relative w-full h-auto"
            x-cloak
            x-data="{
                showRecoveryInput: @js($errors->has('recovery_code')),
                code: '',
                recovery_code: '',
                toggleInput() {
                    this.showRecoveryInput = !this.showRecoveryInput;
                    this.code = '';
                    this.recovery_code = '';
                    $dispatch('clear-2fa-auth-code');
                    $nextTick(() => {
                        this.showRecoveryInput
                            ? this.$refs.recovery_code?.focus()
                            : $dispatch('focus-2fa-auth-code');
                    });
                },
            }"
        >
            <div x-show="!showRecoveryInput">
                <div class="space-y-2 text-center">
                    <h1 class="text-2xl font-semibold tracking-tight text-white">{{ __('Authentication code') }}</h1>
                    <p class="text-sm text-white/72">{{ __('Enter the authentication code provided by your authenticator application.') }}</p>
                </div>
            </div>

            <div x-show="showRecoveryInput">
                <div class="space-y-2 text-center">
                    <h1 class="text-2xl font-semibold tracking-tight text-white">{{ __('Recovery code') }}</h1>
                    <p class="text-sm text-white/72">{{ __('Please confirm access to your account by entering one of your emergency recovery codes.') }}</p>
                </div>
            </div>

            <form method="POST" action="{{ route('two-factor.login.store') }}">
                @csrf

                <div class="space-y-5 text-center">
                    <div x-show="!showRecoveryInput">
                        <div class="my-5">
                            <x-ui.input
                                type="text"
                                name="code"
                                x-model="code"
                                maxlength="6"
                                inputmode="numeric"
                                autocomplete="one-time-code"
                                :label="__('Authentication code')"
                                :error="$errors->first('code')"
                            />
                        </div>
                    </div>

                    <div x-show="showRecoveryInput">
                        <div class="my-5">
                            <x-ui.input
                                type="text"
                                name="recovery_code"
                                x-ref="recovery_code"
                                x-bind:required="showRecoveryInput"
                                autocomplete="one-time-code"
                                x-model="recovery_code"
                                :label="__('Recovery code')"
                                :error="$errors->first('recovery_code')"
                            />
                        </div>
                    </div>

                    <x-ui.button type="submit" class="w-full">
                        {{ __('Continue') }}
                    </x-ui.button>
                </div>

                <div class="mt-5 space-x-0.5 text-center text-sm leading-5 text-white/70">
                    <span>{{ __('or you can') }}</span>
                    <div class="inline cursor-pointer font-medium underline transition hover:opacity-90" style="color: var(--theme-link-color);" onmouseover="this.style.color='var(--theme-link-hover-color)'" onmouseout="this.style.color='var(--theme-link-color)'">
                        <span x-show="!showRecoveryInput" @click="toggleInput()">{{ __('login using a recovery code') }}</span>
                        <span x-show="showRecoveryInput" @click="toggleInput()">{{ __('login using an authentication code') }}</span>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endcomponent
